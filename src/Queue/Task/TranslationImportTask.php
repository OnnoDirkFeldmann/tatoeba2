<?php

namespace App\Queue\Task;

use Queue\Queue\Task;

/**
 * Imports the validated items of a translation import.
 *
 * Items are processed in batches: if items remain after this run, another
 * job of the same type is queued.
 *
 * See https://github.com/Tatoeba/tatoeba2/issues/3270
 */
class TranslationImportTask extends Task {
    public $retries = 0;

    public function run(array $config, int $jobId): void {
        $this->fetchTable('TranslationImports')->runImport($config['import_id']);
    }
}
