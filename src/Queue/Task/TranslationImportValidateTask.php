<?php

namespace App\Queue\Task;

use Queue\Queue\Task;

/**
 * Parses and validates the file uploaded for a translation import.
 *
 * See https://github.com/Tatoeba/tatoeba2/issues/3270
 */
class TranslationImportValidateTask extends Task {
    public $retries = 1;

    public function run(array $config, int $jobId): void {
        $this->fetchTable('TranslationImports')->runValidation($config['import_id']);
    }
}
