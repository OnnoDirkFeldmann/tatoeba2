<?php
use Migrations\AbstractMigration;

class CreateTranslationImports extends AbstractMigration
{
    /**
     * Create the tables used to store bulk (mass) translation imports.
     *
     * `translation_imports` holds one row per upload and stores the import
     * context (who uploaded it, for which project, which languages).
     *
     * `translation_import_items` holds one row per line of the uploaded file,
     * together with the validation/import result for that line.
     *
     * See https://github.com/Tatoeba/tatoeba2/issues/3270
     *
     * @return void
     */
    public function change()
    {
        $imports = $this->table('translation_imports');
        $imports->addColumn('user_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $imports->addColumn('queued_job_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $imports->addColumn('source_lang', 'string', [
            'default' => null,
            'limit' => 4,
            'null' => true,
        ]);
        $imports->addColumn('target_lang', 'string', [
            'default' => null,
            'limit' => 4,
            'null' => false,
        ]);
        $imports->addColumn('context', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $imports->addColumn('filename', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $imports->addColumn('status', 'string', [
            'default' => 'pending',
            'limit' => 20,
            'null' => false,
        ]);
        $imports->addColumn('total_items', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
        ]);
        $imports->addColumn('valid_items', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
        ]);
        $imports->addColumn('imported_items', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
        ]);
        $imports->addColumn('skipped_items', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
        ]);
        $imports->addColumn('error_items', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
        ]);
        $imports->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $imports->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $imports->addIndex('user_id');
        $imports->addIndex('queued_job_id');
        $imports->addIndex('status');
        $imports->create();

        $items = $this->table('translation_import_items');
        $items->addColumn('translation_import_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $items->addColumn('row_number', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
        ]);
        $items->addColumn('sentence_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $items->addColumn('source_text', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $items->addColumn('translation_text', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $items->addColumn('translation_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $items->addColumn('status', 'string', [
            'default' => 'pending',
            'limit' => 30,
            'null' => false,
        ]);
        $items->addColumn('message', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $items->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $items->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $items->addIndex(['translation_import_id', 'status']);
        $items->addIndex('sentence_id');
        $items->addIndex('status');
        $items->create();
    }
}
