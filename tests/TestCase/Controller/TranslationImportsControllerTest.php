<?php
namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Controller\TatoebaControllerTestTrait;
use Cake\TestSuite\TestCase;

class TranslationImportsControllerTest extends TestCase
{
    use TatoebaControllerTestTrait;

    public $fixtures = [
        'app.users',
        'app.groups',
        'app.translation_imports',
        'app.translation_import_items',
        'app.queued_jobs',
    ];

    public function accessesProvider()
    {
        return [
            // index
            [null, 'index', '/en/translation_imports', false],
            ['contributor', 'index', '/en/translation_imports', true],
            ['advanced_contributor', 'index', '/en/translation_imports', true],
            ['corpus_maintainer', 'index', '/en/translation_imports', true],
            ['admin', 'index', '/en/translation_imports', true],

            // upload
            [null, 'upload', '/en/translation_imports/upload', false],
            ['contributor', 'upload', '/en/translation_imports/upload', true],
            ['admin', 'upload', '/en/translation_imports/upload', true],

            // preview (own import)
            [null, 'preview', '/en/translation_imports/preview/1', false],
            ['contributor', 'preview', '/en/translation_imports/preview/1', false], // owner is user 4
            ['advanced_contributor', 'preview', '/en/translation_imports/preview/1', false],
            [4, 'preview', '/en/translation_imports/preview/1', true], // owner
            ['admin', 'preview', '/en/translation_imports/preview/1', true], // admin can view

            // view (own import)
            [null, 'view', '/en/translation_imports/view/1', false],
            [4, 'view', '/en/translation_imports/view/1', true],
            ['admin', 'view', '/en/translation_imports/view/1', true],

            // report (JSON)
            [null, 'report', '/en/translation_imports/report/1?format=json', false],
            [4, 'report', '/en/translation_imports/report/1?format=json', true],
            ['admin', 'report', '/en/translation_imports/report/1?format=json', true],

            // report (CSV)
            [null, 'report', '/en/translation_imports/report/1?format=csv', false],
            [4, 'report', '/en/translation_imports/report/1?format=csv', true],
            ['admin', 'report', '/en/translation_imports/report/1?format=csv', true],

            // delete (POST required) - access check via GET is disallowed or redirects; test as GET
            [null, 'delete', '/en/translation_imports/delete/1', false],
            [4, 'delete', '/en/translation_imports/delete/1', false], // GET not POST, returns method not allowed (4xx) but access-wise controller blocks by method; TatoebaControllerTestTrait treats as access
            ['admin', 'delete', '/en/translation_imports/delete/1', false],
        ];
    }
}
