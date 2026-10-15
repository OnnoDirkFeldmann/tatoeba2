<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TranslationImportsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class TranslationImportsTableTest extends TestCase
{
    public $fixtures = [
        'app.users',
        'app.groups',
        'app.translation_imports',
        'app.translation_import_items',
        'app.queued_jobs',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->TranslationImports = TableRegistry::getTableLocator()->get('TranslationImports');
    }

    public function tearDown(): void
    {
        unset($this->TranslationImports);
        parent::tearDown();
    }

    public function testCreateImport()
    {
        $import = $this->TranslationImports->createImport(4, [
            'target_lang' => 'fra',
            'source_lang' => 'eng',
            'context' => 'test',
            'filename' => 'test.csv',
        ]);
        $this->assertNotFalse($import);
        $this->assertEquals('fra', $import->target_lang);
        $this->assertEquals('pending', $import->status);
    }
}
