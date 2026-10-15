<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TranslationImportsFixture
 */
class TranslationImportsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'user_id' => 4,
                'queued_job_id' => null,
                'source_lang' => 'eng',
                'target_lang' => 'fra',
                'context' => 'Some offline project',
                'filename' => 'translations.csv',
                'status' => 'previewed',
                'total_items' => 3,
                'valid_items' => 1,
                'imported_items' => 0,
                'skipped_items' => 1,
                'error_items' => 1,
                'created' => '2026-10-15 12:00:00',
                'modified' => '2026-10-15 12:00:00',
            ],
        ];
        parent::init();
    }
}
