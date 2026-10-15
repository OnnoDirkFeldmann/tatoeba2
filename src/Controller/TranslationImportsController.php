<?php
namespace App\Controller;

use App\Lib\LanguagesLib;
use App\Model\CurrentUser;
use App\Model\Table\TranslationImportItemsTable;
use App\Model\Table\TranslationImportsTable;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;

/**
 * Controller for bulk (mass) translation imports.
 *
 * Allows a member to upload a file of translations for existing sentences,
 * preview how the import would look, and then import them. Every import is
 * attributed to the user performing the upload, since only that person can
 * confirm the translations are being imported correctly.
 *
 * See https://github.com/Tatoeba/tatoeba2/issues/3270
 */
class TranslationImportsController extends AppController
{
    public $name = 'TranslationImports';

    protected $defaultTable = 'TranslationImports';

    /**
     * Restrict an import to its owner (or an admin).
     *
     * @param int $id Id of the import.
     *
     * @return \App\Model\Entity\TranslationImport
     */
    private function getOwnImport(int $id)
    {
        try {
            $import = $this->TranslationImports->get($id);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new NotFoundException();
        }

        if ($import->user_id != CurrentUser::get('id') && !CurrentUser::isAdmin()) {
            throw new ForbiddenException();
        }

        return $import;
    }

    /**
     * Query the items of an import, optionally filtered by status.
     *
     * @param \App\Model\Entity\TranslationImport $import The import.
     *
     * @return \Cake\ORM\Query
     */
    private function itemsQuery($import)
    {
        $status = $this->request->getQuery('status');
        $query = $this->TranslationImports->TranslationImportItems
            ->find()
            ->where(['translation_import_id' => $import->id])
            ->orderAsc('row_number');

        if ($status && array_key_exists($status, TranslationImportItemsTable::statusLabels())) {
            $query->where(['status' => $status]);
        }

        return $query;
    }

    /**
     * List the current user's imports.
     *
     * @return void
     */
    public function index()
    {
        $query = $this->TranslationImports
            ->find()
            ->find('ownedBy', ['user_id' => (int)CurrentUser::get('id')])
            ->orderDesc('TranslationImports.created');

        $imports = $this->paginate($query);
        $this->set(compact('imports'));
    }

    /**
     * Show the upload form.
     *
     * @return void
     */
    public function upload()
    {
    }

    /**
     * Handle an uploaded file: create the import and start validation.
     *
     * @return \Cake\Http\Response|null
     */
    public function create()
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }

        $data = $this->request->getData();
        $targetLang = $data['target_lang'] ?? null;
        $sourceLang = !empty($data['source_lang']) ? $data['source_lang'] : null;
        $context = isset($data['context']) ? trim((string)$data['context']) : '';
        $file = $data['file'] ?? null;

        if (!$this->isValidLanguage($targetLang)) {
            $this->Flash->error(__('Please select a valid language for the translations.'));
            return $this->redirect(['action' => 'upload']);
        }

        if (!$this->isValidLanguage($sourceLang, true)) {
            $this->Flash->error(__('Please select a valid language for the original sentences.'));
            return $this->redirect(['action' => 'upload']);
        }

        if (!$this->isValidUploadedFile($file)) {
            $this->Flash->error(__('The uploaded file is missing, invalid or too large.'));
            return $this->redirect(['action' => 'upload']);
        }

        $import = $this->TranslationImports->createImport((int)CurrentUser::get('id'), [
            'target_lang' => $targetLang,
            'source_lang' => $sourceLang,
            'context' => $context !== '' ? $context : null,
            'filename' => $file->getClientFilename(),
        ]);

        if (!$import) {
            $this->Flash->error(__('The import could not be created.'));
            return $this->redirect(['action' => 'upload']);
        }

        if (!$this->TranslationImports->storeUploadedFile($import, $file)) {
            $this->TranslationImports->deleteImport($import->id);
            $this->Flash->error(__('The uploaded file could not be stored.'));
            return $this->redirect(['action' => 'upload']);
        }

        $this->TranslationImports->enqueueValidationJob($import->id, $import->user_id);
        $this->Flash->success(__('Your file is being validated. This may take a moment.'));

        return $this->redirect(['action' => 'preview', $import->id]);
    }

    /**
     * Show a preview of how the import would look like.
     *
     * @param int $id Id of the import.
     *
     * @return void
     */
    public function preview($id = null)
    {
        $import = $this->getOwnImport((int)$id);
        $items = $this->paginate($this->itemsQuery($import), ['limit' => 50]);
        $statusLabels = TranslationImportItemsTable::statusLabels();
        $messageLabels = TranslationImportItemsTable::messageLabels();
        $status = (string)$this->request->getQuery('status');

        $this->set(compact('import', 'items', 'statusLabels', 'messageLabels', 'status'));
    }

    /**
     * Start importing the validated items.
     *
     * @param int $id Id of the import.
     *
     * @return \Cake\Http\Response|null
     */
    public function import($id = null)
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }

        $import = $this->getOwnImport((int)$id);

        if (in_array($import->status, [TranslationImportsTable::STATUS_PREVIEWED, TranslationImportsTable::STATUS_IMPORTING], true)) {
            $this->TranslationImports->enqueueImportJob($import->id, $import->user_id);
        }

        return $this->redirect(['action' => 'view', $import->id]);
    }

    /**
     * Show the result of an import.
     *
     * @param int $id Id of the import.
     *
     * @return void
     */
    public function view($id = null)
    {
        $import = $this->getOwnImport((int)$id);
        $items = $this->paginate($this->itemsQuery($import), ['limit' => 50]);
        $statusLabels = TranslationImportItemsTable::statusLabels();
        $messageLabels = TranslationImportItemsTable::messageLabels();
        $status = (string)$this->request->getQuery('status');

        $this->set(compact('import', 'items', 'statusLabels', 'messageLabels', 'status'));
    }

    /**
     * Download the machine readable report of an import.
     *
     * @param int $id Id of the import.
     *
     * @return \Cake\Http\Response|null
     */
    public function report($id = null)
    {
        $import = $this->getOwnImport((int)$id);
        $items = $this->TranslationImports->TranslationImportItems
            ->find()
            ->where(['translation_import_id' => $import->id])
            ->orderAsc('row_number')
            ->all();

        $format = strtolower((string)$this->request->getQuery('format', 'json'));

        if ($format === 'csv') {
            return $this->reportAsCsv($import, $items);
        }

        return $this->reportAsJson($import, $items);
    }

    /**
     * @param \App\Model\Entity\TranslationImport $import The import.
     * @param iterable $items Items of the import.
     *
     * @return \Cake\Http\Response
     */
    private function reportAsCsv($import, $items)
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'row_number',
            'sentence_id',
            'status',
            'message',
            'source_text',
            'translation_text',
            'translation_id',
        ]);

        foreach ($items as $item) {
            fputcsv($handle, [
                $item->row_number,
                $item->sentence_id,
                $item->status,
                $item->message,
                $item->source_text,
                $item->translation_text,
                $item->translation_id,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $filename = 'translation_import_' . $import->id . '.csv';
        return $this->response
            ->withType('text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody("\xEF\xBB\xBF" . $content);
    }

    /**
     * @param \App\Model\Entity\TranslationImport $import The import.
     * @param iterable $items Items of the import.
     *
     * @return \Cake\Http\Response|null
     */
    private function reportAsJson($import, $items)
    {
        $report = [
            'import' => [
                'id' => (int)$import->id,
                'source_lang' => $import->source_lang,
                'target_lang' => $import->target_lang,
                'context' => $import->context,
                'filename' => $import->filename,
                'status' => $import->status,
                'total_items' => (int)$import->total_items,
                'valid_items' => (int)$import->valid_items,
                'imported_items' => (int)$import->imported_items,
                'skipped_items' => (int)$import->skipped_items,
                'error_items' => (int)$import->error_items,
            ],
            'items' => [],
        ];

        foreach ($items as $item) {
            $report['items'][] = [
                'row_number' => (int)$item->row_number,
                'sentence_id' => $item->sentence_id !== null ? (int)$item->sentence_id : null,
                'translation_id' => $item->translation_id !== null ? (int)$item->translation_id : null,
                'status' => $item->status,
                'message' => $item->message,
                'source_text' => $item->source_text,
                'translation_text' => $item->translation_text,
            ];
        }

        $this->set(compact('report'));
        $this->viewBuilder()
            ->setOption('serialize', ['report'])
            ->setClassName('Json');

        return null;
    }

    /**
     * Delete an import.
     *
     * @param int $id Id of the import.
     *
     * @return \Cake\Http\Response|null
     */
    public function delete($id = null)
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }

        $import = $this->getOwnImport((int)$id);
        $this->TranslationImports->deleteImport($import->id);
        $this->Flash->success(__('The import has been deleted.'));

        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param string|null $lang Language code.
     * @param bool $allowEmpty Whether an empty value is valid.
     *
     * @return bool
     */
    private function isValidLanguage($lang, bool $allowEmpty = false): bool
    {
        if ($lang === null || $lang === '') {
            return $allowEmpty;
        }

        return array_key_exists($lang, LanguagesLib::languagesInTatoeba());
    }

    /**
     * @param mixed $file Uploaded file.
     *
     * @return bool
     */
    private function isValidUploadedFile($file): bool
    {
        if (!$file || !is_object($file) || !method_exists($file, 'getError')) {
            return false;
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return false;
        }

        $maxSize = (int)Configure::read('TranslationImports.maxFileSize', 10 * 1024 * 1024);
        $size = $file->getSize();
        if ($size === null || $size <= 0 || $size > $maxSize) {
            return false;
        }

        $name = $file->getClientFilename();
        if ($name) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($extension !== '' && !in_array($extension, TranslationImportsTable::ALLOWED_EXTENSIONS, true)) {
                return false;
            }
        }

        return true;
    }
}
