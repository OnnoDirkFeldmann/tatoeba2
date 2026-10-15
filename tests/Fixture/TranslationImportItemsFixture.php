<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TranslationImportItemsFixture
 */
class TranslationImportItemsFixture extends TestFixture
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
                'translation_import_id' => 1,
                'row_number' => 1,
                'sentence_id' => 1,
                'source_text' => 'The dog is sleeping.',
                'translation_text' => 'Le chien dort.',
                'translation_id' => null,
                'status' => 'ok',
                'message' => null,
                'created' => '2026-10-15 12:00:00',
                'modified' => '2026-10-15 12:00:00',
            ],
            [
                'id' => 2,
                'translation_import_id' => 1,
                'row_number' => 2,
                'sentence_id' => 2,
                'source_text' => 'The cat is sleeping.',
                'translation_text' => 'Le chat dort.',
                'translation_id' => null,
                'status' => 'already_linked',
                'message' => null,
                'created' => '2026-10-15 12:00:00',
                'modified' => '2026-10-15 12:00:00',
            ],
            [
                'id' => 3,
                'translation_import_id' => 1,
                'row_number' => 3,
                'sentence_id' => null,
                'source_text' => null,
                'translation_text' => null,
                'translation_id' => null,
                'status' => 'invalid',
                'message' => 'invalid_sentence_id',
                'created' => '2026-10-15 12:00:00',
                'modified' => '2026-10-15 12:00:00',
            ],
        ];
        parent::init();
    }
}
