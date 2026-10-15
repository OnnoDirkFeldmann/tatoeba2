<?php
namespace App\Model\Table;

use App\Model\CurrentUser;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Exception;

/**
 * TranslationImports Table
 *
 * Stores one row per bulk (mass) translation import, i.e. per uploaded file.
 *
 * See https://github.com/Tatoeba/tatoeba2/issues/3270
 */
class TranslationImportsTable extends Table
{
    /**
     * Import is created but not processed yet.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * The file is being parsed and validated.
     */
    public const STATUS_VALIDATING = 'validating';

    /**
     * The file has been validated and can be previewed.
     */
    public const STATUS_PREVIEWED = 'previewed';

    /**
     * Valid items are being imported.
     */
    public const STATUS_IMPORTING = 'importing';

    /**
     * The import has been fully processed.
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * The import failed irrecoverably.
     */
    public const STATUS_FAILED = 'failed';

    /**
     * The import was cancelled by the user.
     */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Statuses for which the processing is still (or may be) going on.
     */
    public const IN_PROGRESS_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VALIDATING,
        self::STATUS_IMPORTING,
    ];

    /**
     * Sub-directory of tmp/ where uploaded files are stored while processed.
     */
    public const UPLOAD_DIR = 'translation_imports';

    /**
     * Maximum number of data rows accepted in a single file.
     *
     * Kept low enough so that parsing, validating and inserting all items of
     * a file still fits comfortably into a single queue job run.
     */
    public const MAX_ROWS = 50000;

    /**
     * Maximum length of a sentence text (matches sentences.text).
     */
    public const MAX_TEXT_LENGTH = 1500;

    /**
     * Maximum number of ids fetched in a single lookup query.
     */
    public const ID_LOOKUP_CHUNK = 1000;

    /**
     * Maximum number of sentence texts fetched in a single lookup query.
     */
    public const TEXT_LOOKUP_CHUNK = 200;

    /**
     * Number of items inserted in a single batch while validating.
     */
    public const SAVE_CHUNK = 500;

    /**
     * Number of items imported per queue job run.
     *
     * Imports are processed in batches so that a large file does not need to
     * be handled within the lifetime of a single job. When items remain after
     * a batch, another job is queued.
     */
    public const IMPORT_BATCH_SIZE = 100;

    /**
     * Accepted file extensions for uploads.
     */
    public const ALLOWED_EXTENSIONS = ['csv', 'tsv', 'txt'];

    /**
     * Human readable, translatable labels for each import status.
     *
     * @return array<string,string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => __('pending'),
            self::STATUS_VALIDATING => __('validating'),
            self::STATUS_PREVIEWED => __('previewed'),
            self::STATUS_IMPORTING => __('importing'),
            self::STATUS_COMPLETED => __('completed'),
            self::STATUS_FAILED => __('failed'),
            self::STATUS_CANCELLED => __('cancelled'),
        ];
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('translation_imports');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users');
        $this->belongsTo('Queue.QueuedJobs');
        $this->hasMany('TranslationImportItems', [
            'foreignKey' => 'translation_import_id',
            'dependent' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', 'create');

        $validator
            ->integer('user_id')
            ->allowEmptyString('user_id');

        $validator
            ->integer('queued_job_id')
            ->allowEmptyString('queued_job_id');

        $validator
            ->scalar('source_lang')
            ->maxLength('source_lang', 4)
            ->allowEmptyString('source_lang');

        $validator
            ->scalar('target_lang')
            ->maxLength('target_lang', 4)
            ->requirePresence('target_lang', 'create')
            ->notEmptyString('target_lang');

        $validator
            ->scalar('context')
            ->maxLength('context', 255)
            ->allowEmptyString('context');

        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->allowEmptyString('filename');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        foreach (['total_items', 'valid_items', 'imported_items', 'skipped_items', 'error_items'] as $field) {
            $validator
                ->integer($field)
                ->allowEmptyString($field);
        }

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));
        $rules->add($rules->existsIn(['queued_job_id'], 'QueuedJobs'));

        return $rules;
    }

    /**
     * Finder restricting the query to imports owned by the given user.
     *
     * @param \Cake\ORM\Query $query The query to modify.
     * @param array $options Options, expects a `user_id` key.
     *
     * @return \Cake\ORM\Query
     */
    public function findOwnedBy(Query $query, array $options): Query
    {
        return $query->where([
            $this->aliasField('user_id') => $options['user_id'],
        ]);
    }

    /**
     * Path where the uploaded file of an import is temporarily stored.
     *
     * @param int $importId Id of the import.
     *
     * @return string
     */
    public static function filePath(int $importId): string
    {
        return TMP . self::UPLOAD_DIR . DS . $importId . '.csv';
    }

    /**
     * Create a new, unprocessed import for the given user.
     *
     * @param int $userId The user the import is attributed to.
     * @param array $data Import data (target_lang, source_lang, context, filename).
     *
     * @return \App\Model\Entity\TranslationImport|false
     */
    public function createImport(int $userId, array $data)
    {
        $import = $this->newEntity([
            'user_id' => $userId,
            'source_lang' => $data['source_lang'] ?? null,
            'target_lang' => $data['target_lang'] ?? null,
            'context' => $data['context'] ?? null,
            'filename' => $data['filename'] ?? null,
            'status' => self::STATUS_PENDING,
        ]);

        if ($import->hasErrors()) {
            return false;
        }

        return $this->save($import);
    }

    /**
     * Move an uploaded file to the temporary storage location of an import.
     *
     * @param \App\Model\Entity\TranslationImport $import The import.
     * @param \Psr\Http\Message\UploadedFileInterface $file The uploaded file.
     *
     * @return bool
     */
    public function storeUploadedFile($import, $file): bool
    {
        $dir = TMP . self::UPLOAD_DIR . DS;
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }

        try {
            $file->moveTo(self::filePath($import->id));
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete the temporarily stored file of an import, if any.
     *
     * @param int $importId Id of the import.
     *
     * @return void
     */
    public function deleteUploadedFile(int $importId): void
    {
        $path = self::filePath($importId);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Delete an import together with its items and stored file.
     *
     * @param int $importId Id of the import.
     *
     * @return bool
     */
    public function deleteImport(int $importId): bool
    {
        $this->deleteUploadedFile($importId);
        try {
            $import = $this->get($importId);
        } catch (RecordNotFoundException $e) {
            return false;
        }

        return (bool)$this->delete($import);
    }

    /**
     * Queue the job that parses and validates the uploaded file.
     *
     * @param int $importId Id of the import.
     * @param int|null $userId Owner of the import, used as job group.
     *
     * @return \Queue\Model\Entity\QueuedJob
     */
    public function enqueueValidationJob(int $importId, ?int $userId = null)
    {
        return $this->enqueueJob('TranslationImportValidate', $importId, $userId);
    }

    /**
     * Queue the job that imports the validated items.
     *
     * @param int $importId Id of the import.
     * @param int|null $userId Owner of the import, used as job group.
     *
     * @return \Queue\Model\Entity\QueuedJob
     */
    public function enqueueImportJob(int $importId, ?int $userId = null)
    {
        return $this->enqueueJob('TranslationImport', $importId, $userId);
    }

    /**
     * @param string $type Queue task type.
     * @param int $importId Id of the import.
     * @param int|null $userId Owner of the import.
     *
     * @return \Queue\Model\Entity\QueuedJob
     */
    private function enqueueJob(string $type, int $importId, ?int $userId = null)
    {
        $options = $userId ? ['group' => $userId] : [];
        $job = $this->QueuedJobs->createJob($type, ['import_id' => $importId], $options);
        $this->QueuedJobs->WorkerProcesses->wakeUpWorkers();
        return $job;
    }

    /**
     * Store the given user as the current user.
     *
     * Queue jobs run without a logged in user. Importing must be attributed to
     * the user who uploaded the file, so we restore that user's context here.
     *
     * @param int|null $userId Id of the user.
     *
     * @return void
     */
    public function becomeUser(?int $userId): void
    {
        if (!$userId) {
            return;
        }

        $user = $this->Users->get($userId);
        CurrentUser::store($user->toArray());
    }

    /**
     * Get another table through the table locator.
     *
     * @param string $alias Table alias, e.g. "Sentences".
     *
     * @return \Cake\ORM\Table
     */
    private function lookupTable(string $alias): Table
    {
        return FactoryLocator::get('Table')->get($alias);
    }

    /**
     * Parse and validate the uploaded file into translation_import_items.
     *
     * @param int $importId The import to validate.
     *
     * @return bool
     */
    public function runValidation(int $importId): bool
    {
        try {
            $import = $this->get($importId);
        } catch (RecordNotFoundException $e) {
            return false;
        }

        if (!in_array($import->status, [self::STATUS_PENDING, self::STATUS_VALIDATING, self::STATUS_FAILED], true)) {
            return false;
        }

        $import->status = self::STATUS_VALIDATING;
        $this->save($import);

        try {
            $rows = $this->readUploadedFile($importId);
            $items = $this->validateRows($import, $rows);
            $this->saveItems($import, $items);
            $this->refreshCounters($import);
            $import->status = self::STATUS_PREVIEWED;
            $this->save($import);
            $ok = true;
        } catch (Exception $e) {
            $import->status = self::STATUS_FAILED;
            $this->save($import);
            $ok = false;
        }

        if ($ok) {
            $this->deleteUploadedFile($importId);
        }

        return $ok;
    }

    /**
     * Import the validated items of an import, in batches.
     *
     * @param int $importId The import to run.
     *
     * @return bool
     */
    public function runImport(int $importId): bool
    {
        try {
            $import = $this->get($importId);
        } catch (RecordNotFoundException $e) {
            return false;
        }

        if (!in_array($import->status, [self::STATUS_PREVIEWED, self::STATUS_IMPORTING], true)) {
            return false;
        }

        $this->becomeUser($import->user_id);

        if ($import->status !== self::STATUS_IMPORTING) {
            $import->status = self::STATUS_IMPORTING;
            $this->save($import);
        }

        $Items = $this->TranslationImportItems;
        $items = $Items->find()
            ->where([
                'translation_import_id' => $importId,
                'status' => TranslationImportItemsTable::STATUS_OK,
            ])
            ->orderAsc('row_number')
            ->limit(self::IMPORT_BATCH_SIZE)
            ->all()
            ->toList();

        $sentenceIds = [];
        foreach ($items as $item) {
            if ($item->sentence_id) {
                $sentenceIds[] = $item->sentence_id;
            }
        }
        $sources = $this->fetchSentencesByIds(array_unique($sentenceIds));
        $Sentences = $this->lookupTable('Sentences');

        foreach ($items as $item) {
            try {
                $source = $sources[$item->sentence_id] ?? null;
                if (!$source) {
                    $item->status = TranslationImportItemsTable::STATUS_SENTENCE_NOT_FOUND;
                    $item->message = TranslationImportItemsTable::MSG_SENTENCE_NOT_FOUND;
                } elseif ($import->source_lang && $source['lang'] !== $import->source_lang) {
                    $item->status = TranslationImportItemsTable::STATUS_SOURCE_LANG_MISMATCH;
                    $item->message = TranslationImportItemsTable::MSG_SOURCE_LANG_MISMATCH;
                } elseif ($item->source_text !== null && $source['text'] !== $item->source_text) {
                    $item->status = TranslationImportItemsTable::STATUS_SOURCE_MODIFIED;
                    $item->message = TranslationImportItemsTable::MSG_SOURCE_MODIFIED;
                } else {
                    $translation = $Sentences->saveTranslation(
                        $item->sentence_id,
                        $source['lang'],
                        $item->translation_text,
                        $import->target_lang
                    );
                    if ($translation) {
                        $item->translation_id = $translation->id;
                        $item->status = TranslationImportItemsTable::STATUS_IMPORTED;
                        $item->message = null;
                    } else {
                        $item->status = TranslationImportItemsTable::STATUS_ERROR;
                        $item->message = TranslationImportItemsTable::MSG_IMPORT_FAILED;
                    }
                }
            } catch (Exception $e) {
                $item->status = TranslationImportItemsTable::STATUS_ERROR;
                $item->message = TranslationImportItemsTable::MSG_IMPORT_FAILED;
            }

            $Items->save($item);
        }

        $import = $this->get($importId);
        $this->refreshCounters($import);

        $remaining = $Items->find()
            ->where([
                'translation_import_id' => $importId,
                'status' => TranslationImportItemsTable::STATUS_OK,
            ])
            ->count();

        if ($remaining > 0) {
            $this->save($import);
            $this->enqueueImportJob($importId, $import->user_id);
        } else {
            $import->status = self::STATUS_COMPLETED;
            $this->save($import);
            $this->deleteUploadedFile($importId);
        }

        return true;
    }

    /**
     * Read the uploaded file and normalize its rows.
     *
     * @param int $importId Id of the import.
     *
     * @return array
     * @throws \Exception When the file cannot be read.
     */
    private function readUploadedFile(int $importId): array
    {
        $path = self::filePath($importId);
        if (!is_file($path)) {
            throw new Exception('Uploaded file not found');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new Exception('Unable to open uploaded file');
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if ($firstLine !== false) {
            $tabCount = substr_count($firstLine, "\t");
            $commaCount = substr_count($firstLine, ',');
            if ($tabCount > $commaCount) {
                $delimiter = "\t";
            }
        }

        $rows = [];
        $rowNumber = 0;
        if ($delimiter === "\t") {
            // Tab-separated files are not quoted: split lines literally so
            // that double quotes inside the text are preserved as-is.
            while (($line = fgets($handle)) !== false) {
                $rowNumber++;

                if ($rowNumber === 1) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                }

                $line = rtrim($line, "\r\n");
                if (trim($line) === '') {
                    continue;
                }

                $rows[] = $this->makeRow($rowNumber, explode("\t", $line), $delimiter);

                if (count($rows) > self::MAX_ROWS) {
                    fclose($handle);
                    throw new Exception('Too many rows');
                }
            }
        } else {
            while (($fields = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($rowNumber === 1 && isset($fields[0])) {
                    $fields[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$fields[0]);
                }

                if (count($fields) === 1 && ($fields[0] === null || trim($fields[0]) === '')) {
                    continue;
                }

                $rows[] = $this->makeRow($rowNumber, $fields, $delimiter);

                if (count($rows) > self::MAX_ROWS) {
                    fclose($handle);
                    throw new Exception('Too many rows');
                }
            }
        }
        fclose($handle);

        // Skip an optional header row (first column is not a sentence id).
        if ($rows) {
            $first = $rows[0];
            $firstIdRaw = trim($first['sentence_id_raw'] ?? '');
            $isHeader = false;
            if ($firstIdRaw === '') {
                $isHeader = true;
            } elseif (!ctype_digit($firstIdRaw)) {
                $isHeader = true;
            } else {
                $lowerFirst = strtolower($firstIdRaw);
                if (in_array($lowerFirst, ['id', 'sentence_id', 'sent_id', 'sentenceid'], true)) {
                    $isHeader = true;
                }
            }
            if ($isHeader) {
                array_shift($rows);
            }
        }

        return $rows;
    }

    /**
     * Normalize one parsed record into a row array.
     *
     * @param int $rowNumber Number of the record in the file.
     * @param array $fields Fields of the record.
     * @param string $delimiter Delimiter used to join extra fields.
     *
     * @return array
     */
    private function makeRow(int $rowNumber, array $fields, string $delimiter): array
    {
        return [
            'row_number' => $rowNumber,
            'malformed' => count($fields) < 3,
            'sentence_id_raw' => isset($fields[0]) ? (string)$fields[0] : '',
            'source_text' => isset($fields[1]) ? (string)$fields[1] : '',
            'translation_text' => count($fields) > 2
                ? implode($delimiter, array_slice($fields, 2))
                : '',
        ];
    }

    /**
     * Compute the status of every row of a file.
     *
     * @param \App\Model\Entity\TranslationImport $import The import.
     * @param array $rows Normalized rows.
     *
     * @return array Rows ready to be saved as items.
     */
    private function validateRows($import, array $rows): array
    {
        $sourceLang = $import->source_lang;
        $targetLang = $import->target_lang;

        $ids = [];
        $texts = [];
        foreach ($rows as $row) {
            $sentenceId = $this->parseSentenceId($row['sentence_id_raw']);
            if ($sentenceId) {
                $ids[$sentenceId] = $sentenceId;
            }
            if ($row['translation_text'] !== '') {
                $texts[$row['translation_text']] = $row['translation_text'];
            }
        }

        $sentences = $this->fetchSentencesByIds(array_values($ids));
        $translations = $this->fetchSentenceIdsByTexts($targetLang, array_values($texts));
        $links = $this->fetchExistingLinks($rows, $translations);

        $items = [];
        $seen = [];
        foreach ($rows as $row) {
            $status = TranslationImportItemsTable::STATUS_OK;
            $message = null;
            $sentenceId = null;
            $translationId = null;

            $sentenceId = $this->parseSentenceId($row['sentence_id_raw']);

            if ($row['malformed']) {
                $status = TranslationImportItemsTable::STATUS_INVALID;
                $message = TranslationImportItemsTable::MSG_MALFORMED_ROW;
            } elseif (!$sentenceId) {
                $status = TranslationImportItemsTable::STATUS_INVALID;
                $message = TranslationImportItemsTable::MSG_INVALID_SENTENCE_ID;
            } elseif (trim($row['source_text']) === '') {
                $status = TranslationImportItemsTable::STATUS_INVALID;
                $message = TranslationImportItemsTable::MSG_EMPTY_SOURCE;
            } elseif (strlen($row['source_text']) > self::MAX_TEXT_LENGTH) {
                $status = TranslationImportItemsTable::STATUS_INVALID;
                $message = TranslationImportItemsTable::MSG_SOURCE_TOO_LONG;
            } elseif (trim($row['translation_text']) === '') {
                $status = TranslationImportItemsTable::STATUS_INVALID;
                $message = TranslationImportItemsTable::MSG_EMPTY_TRANSLATION;
            } elseif (strlen($row['translation_text']) > self::MAX_TEXT_LENGTH) {
                $status = TranslationImportItemsTable::STATUS_INVALID;
                $message = TranslationImportItemsTable::MSG_TRANSLATION_TOO_LONG;
            } else {
                $key = $sentenceId . '|' . $row['translation_text'];
                if (isset($seen[$key])) {
                    $status = TranslationImportItemsTable::STATUS_DUPLICATE_TRANSLATION;
                    $message = TranslationImportItemsTable::MSG_DUPLICATE_IN_FILE;
                } else {
                    $seen[$key] = true;
                    $source = $sentences[$sentenceId] ?? null;
                    if (!$source) {
                        $status = TranslationImportItemsTable::STATUS_SENTENCE_NOT_FOUND;
                        $message = TranslationImportItemsTable::MSG_SENTENCE_NOT_FOUND;
                    } elseif ($sourceLang && $source['lang'] !== $sourceLang) {
                        $status = TranslationImportItemsTable::STATUS_SOURCE_LANG_MISMATCH;
                        $message = TranslationImportItemsTable::MSG_SOURCE_LANG_MISMATCH;
                    } elseif ($source['text'] !== $row['source_text']) {
                        $status = TranslationImportItemsTable::STATUS_SOURCE_MODIFIED;
                        $message = TranslationImportItemsTable::MSG_SOURCE_MODIFIED;
                    } else {
                        $existingId = $translations[$row['translation_text']] ?? null;
                        if ($existingId) {
                            $translationId = $existingId;
                            if (isset($links[$sentenceId . ':' . $existingId])) {
                                $status = TranslationImportItemsTable::STATUS_ALREADY_LINKED;
                                $message = TranslationImportItemsTable::MSG_ALREADY_LINKED;
                            }
                        }
                    }
                }
            }

            $items[] = [
                'translation_import_id' => $import->id,
                'row_number' => $row['row_number'],
                'sentence_id' => $sentenceId,
                'source_text' => $row['source_text'],
                'translation_text' => $row['translation_text'],
                'translation_id' => $translationId,
                'status' => $status,
                'message' => $message,
            ];
        }

        return $items;
    }

    /**
     * @param string $raw Raw sentence id from the file.
     *
     * @return int|null
     */
    private function parseSentenceId(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }
        $id = (int)$raw;
        return $id > 0 ? $id : null;
    }

    /**
     * Fetch sentences by id, in chunks.
     *
     * @param array $ids Sentence ids.
     *
     * @return array Map of id => ['text' => ..., 'lang' => ...].
     */
    private function fetchSentencesByIds(array $ids): array
    {
        $map = [];
        $Sentences = $this->lookupTable('Sentences');
        foreach (array_chunk($ids, self::ID_LOOKUP_CHUNK) as $chunk) {
            $sentences = $Sentences->find()
                ->select(['id', 'text', 'lang'])
                ->where(['id IN' => $chunk])
                ->all();
            foreach ($sentences as $sentence) {
                $map[(int)$sentence->id] = [
                    'text' => $sentence->text,
                    'lang' => $sentence->lang,
                ];
            }
        }
        return $map;
    }

    /**
     * Fetch existing sentences of a given language by their text, in chunks.
     *
     * @param string $lang Target language.
     * @param array $texts Sentence texts.
     *
     * @return array Map of text => sentence id.
     */
    private function fetchSentenceIdsByTexts(string $lang, array $texts): array
    {
        $map = [];
        $Sentences = $this->lookupTable('Sentences');
        foreach (array_chunk($texts, self::TEXT_LOOKUP_CHUNK) as $chunk) {
            $sentences = $Sentences->find()
                ->select(['id', 'text'])
                ->where(['lang' => $lang, 'text IN' => $chunk])
                ->all();
            foreach ($sentences as $sentence) {
                $map[$sentence->text] = (int)$sentence->id;
            }
        }
        return $map;
    }

    /**
     * Find which of the (sentence, translation) pairs already exist as links.
     *
     * @param array $rows Normalized rows.
     * @param array $translations Map of text => sentence id.
     *
     * @return array Set of "sentenceId:translationId" pairs.
     */
    private function fetchExistingLinks(array $rows, array $translations): array
    {
        $pairs = [];
        foreach ($rows as $row) {
            $sentenceId = $this->parseSentenceId($row['sentence_id_raw']);
            $translationId = $translations[$row['translation_text']] ?? null;
            if ($sentenceId && $translationId) {
                $pairs[$sentenceId . ':' . $translationId] = [$sentenceId, $translationId];
            }
        }

        $links = [];
        if (!$pairs) {
            return $links;
        }

        $Links = $this->lookupTable('Links');
        foreach (array_chunk($pairs, 100) as $chunk) {
            $conditions = ['OR' => []];
            foreach ($chunk as $pair) {
                $conditions['OR'][] = [
                    'sentence_id' => $pair[0],
                    'translation_id' => $pair[1],
                ];
            }
            $existing = $Links->find()
                ->select(['sentence_id', 'translation_id'])
                ->where($conditions)
                ->all();
            foreach ($existing as $link) {
                $links[$link->sentence_id . ':' . $link->translation_id] = true;
            }
        }

        return $links;
    }

    /**
     * Replace the items of an import with the given ones.
     *
     * @param \App\Model\Entity\TranslationImport $import The import.
     * @param array $items Items to save.
     *
     * @return void
     */
    private function saveItems($import, array $items): void
    {
        $Items = $this->TranslationImportItems;
        $Items->deleteAll(['translation_import_id' => $import->id]);

        foreach (array_chunk($items, self::SAVE_CHUNK) as $chunk) {
            $entities = $Items->newEntities($chunk, ['validate' => false]);
            // Items are already validated above: skip per-row validation and
            // rules to keep the insertion of large files fast.
            $Items->saveMany($entities, ['validate' => false, 'checkRules' => false]);
        }
    }

    /**
     * Recompute the counters of an import from its items.
     *
     * @param \App\Model\Entity\TranslationImport $import The import.
     *
     * @return void
     */
    private function refreshCounters($import): void
    {
        $Items = $this->TranslationImportItems;
        $query = $Items->find();
        $counts = $query
            ->select(['status', 'count' => $query->func()->count('*')])
            ->where(['translation_import_id' => $import->id])
            ->group(['status'])
            ->all();

        $byStatus = [];
        foreach ($counts as $row) {
            $byStatus[$row->status] = (int)$row->count;
        }

        $sum = function (array $statuses) use ($byStatus): int {
            $result = 0;
            foreach ($statuses as $status) {
                $result += $byStatus[$status] ?? 0;
            }
            return $result;
        };

        $import->total_items = array_sum($byStatus);
        $import->valid_items = $byStatus[TranslationImportItemsTable::STATUS_OK] ?? 0;
        $import->imported_items = $byStatus[TranslationImportItemsTable::STATUS_IMPORTED] ?? 0;
        $import->skipped_items = $sum(TranslationImportItemsTable::SKIPPED_STATUSES);
        $import->error_items = $sum(TranslationImportItemsTable::ERROR_STATUSES);
    }
}
