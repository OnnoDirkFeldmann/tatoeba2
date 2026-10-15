<?php
namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * TranslationImportItems Table
 *
 * Stores one row per line of an uploaded translation import file, together
 * with the validation/import result for that line.
 *
 * See https://github.com/Tatoeba/tatoeba2/issues/3270
 */
class TranslationImportItemsTable extends Table
{
    /**
     * Not processed yet.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Valid, ready to be imported (or already looked up successfully).
     */
    public const STATUS_OK = 'ok';

    /**
     * The translation sentence was created and linked successfully.
     */
    public const STATUS_IMPORTED = 'imported';

    /**
     * The link already existed, nothing had to be done.
     */
    public const STATUS_ALREADY_LINKED = 'already_linked';

    /**
     * The referenced sentence does not exist.
     */
    public const STATUS_SENTENCE_NOT_FOUND = 'sentence_not_found';

    /**
     * The original sentence was modified since the translation was made.
     */
    public const STATUS_SOURCE_MODIFIED = 'source_modified';

    /**
     * The language of the original sentence does not match the expected one.
     */
    public const STATUS_SOURCE_LANG_MISMATCH = 'source_lang_mismatch';

    /**
     * A sentence with the same text and language already exists.
     */
    public const STATUS_DUPLICATE_TRANSLATION = 'duplicate_translation';

    /**
     * The row is malformed (missing/empty/invalid fields).
     */
    public const STATUS_INVALID = 'invalid';

    /**
     * An unexpected error occurred while processing the row.
     */
    public const STATUS_ERROR = 'error';

    /**
     * Statuses for which an item is considered "importable".
     */
    public const IMPORTABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_OK,
    ];

    /**
     * Statuses that do not count as errors but as "nothing to do".
     */
    public const SKIPPED_STATUSES = [
        self::STATUS_ALREADY_LINKED,
        self::STATUS_SENTENCE_NOT_FOUND,
        self::STATUS_SOURCE_MODIFIED,
        self::STATUS_SOURCE_LANG_MISMATCH,
        self::STATUS_DUPLICATE_TRANSLATION,
    ];

    /**
     * Statuses that count as errors.
     */
    public const ERROR_STATUSES = [
        self::STATUS_INVALID,
        self::STATUS_ERROR,
    ];

    /**
     * Machine readable message codes explaining an item status.
     *
     * These are stored as-is in the database and translated when displayed,
     * so that the stored report stays stable and language independent.
     */
    public const MSG_MALFORMED_ROW = 'malformed_row';
    public const MSG_INVALID_SENTENCE_ID = 'invalid_sentence_id';
    public const MSG_EMPTY_SOURCE = 'empty_source';
    public const MSG_SOURCE_TOO_LONG = 'source_too_long';
    public const MSG_EMPTY_TRANSLATION = 'empty_translation';
    public const MSG_TRANSLATION_TOO_LONG = 'translation_too_long';
    public const MSG_SENTENCE_NOT_FOUND = 'sentence_not_found';
    public const MSG_SOURCE_MODIFIED = 'source_modified';
    public const MSG_SOURCE_LANG_MISMATCH = 'source_lang_mismatch';
    public const MSG_ALREADY_LINKED = 'already_linked';
    public const MSG_DUPLICATE_IN_FILE = 'duplicate_in_file';
    public const MSG_IMPORT_FAILED = 'import_failed';
    public const MSG_TOO_MANY_ROWS = 'too_many_rows';

    /**
     * Human readable, translatable labels for each status.
     *
     * @return array<string,string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => __('pending'),
            self::STATUS_OK => __('ok'),
            self::STATUS_IMPORTED => __('imported'),
            self::STATUS_ALREADY_LINKED => __('already linked'),
            self::STATUS_SENTENCE_NOT_FOUND => __('sentence not found'),
            self::STATUS_SOURCE_MODIFIED => __('source sentence was modified'),
            self::STATUS_SOURCE_LANG_MISMATCH => __('source language mismatch'),
            self::STATUS_DUPLICATE_TRANSLATION => __('duplicate translation'),
            self::STATUS_INVALID => __('invalid'),
            self::STATUS_ERROR => __('error'),
        ];
    }

    /**
     * Human readable, translatable labels for each message code.
     *
     * @return array<string,string>
     */
    public static function messageLabels(): array
    {
        return [
            self::MSG_MALFORMED_ROW => __('The row does not have the expected three columns.'),
            self::MSG_INVALID_SENTENCE_ID => __('The sentence id is missing or is not a number.'),
            self::MSG_EMPTY_SOURCE => __('The source sentence text is missing.'),
            self::MSG_SOURCE_TOO_LONG => __('The source sentence text is too long.'),
            self::MSG_EMPTY_TRANSLATION => __('The translation text is missing.'),
            self::MSG_TRANSLATION_TOO_LONG => __('The translation text is too long.'),
            self::MSG_SENTENCE_NOT_FOUND => __('No sentence with this id exists.'),
            self::MSG_SOURCE_MODIFIED => __('The source sentence was modified after the translation was made.'),
            self::MSG_SOURCE_LANG_MISMATCH => __('The language of the source sentence is not the expected one.'),
            self::MSG_ALREADY_LINKED => __('This translation is already linked to the sentence.'),
            self::MSG_DUPLICATE_IN_FILE => __('This translation appears more than once for the same sentence in the file.'),
            self::MSG_IMPORT_FAILED => __('The translation could not be imported.'),
            self::MSG_TOO_MANY_ROWS => __('The file contains too many rows.'),
        ];
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('translation_import_items');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('TranslationImports', [
            'foreignKey' => 'translation_import_id',
        ]);
        $this->belongsTo('Sentences', [
            'foreignKey' => 'sentence_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', 'create');

        $validator
            ->integer('translation_import_id')
            ->requirePresence('translation_import_id', 'create')
            ->notEmptyString('translation_import_id');

        $validator
            ->integer('row_number')
            ->allowEmptyString('row_number');

        $validator
            ->integer('sentence_id')
            ->allowEmptyString('sentence_id');

        $validator
            ->scalar('source_text')
            ->allowEmptyString('source_text');

        $validator
            ->scalar('translation_text')
            ->allowEmptyString('translation_text');

        $validator
            ->integer('translation_id')
            ->allowEmptyString('translation_id');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $validator
            ->scalar('message')
            ->maxLength('message', 255)
            ->allowEmptyString('message');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['translation_import_id'], 'TranslationImports'));

        return $rules;
    }
}
