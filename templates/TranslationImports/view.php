<?php
/**
 * Tatoeba Project, free collaborative creation of multilingual corpuses project
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @category PHP
 * @package  Tatoeba
 * @license  Affero General Public License
 * @link     https://tatoeba.org
 *
 * @var \App\Model\Entity\TranslationImport $import
 * @var iterable $items
 * @var array $statusLabels
 * @var array $messageLabels
 * @var string $status
 */

use App\Model\Table\TranslationImportsTable;

$this->set('title_for_layout', $this->Pages->formatTitle(__('Import result')));

$inProgress = in_array($import->status, [
    TranslationImportsTable::STATUS_PENDING,
    TranslationImportsTable::STATUS_VALIDATING,
    TranslationImportsTable::STATUS_IMPORTING,
], true);
?>

<div id="main_content">

    <div class="module">
        <h2><?php echo __('Import result'); ?></h2>

        <p>
            <?php
            echo format(
                __('File: {filename} ({target_lang})'),
                ['filename' => h($import->filename), 'target_lang' => h($import->target_lang)]
            );
            ?>
        </p>

        <?php if ($import->context): ?>
            <p>
                <?php
                echo format(
                    __('Project or source: {context}'),
                    ['context' => h($import->context)]
                );
                ?>
            </p>
        <?php endif; ?>

        <p>
            <?php echo format(__('Status: {status}'), ['status' => h(TranslationImportsTable::statusLabels()[$import->status] ?? $import->status)]); ?>
        </p>

        <ul>
            <li><?php echo format(__('Total: {count}'), ['count' => $import->total_items]); ?></li>
            <li><?php echo format(__('Imported: {count}'), ['count' => $import->imported_items]); ?></li>
            <li><?php echo format(__('Skipped: {count}'), ['count' => $import->skipped_items]); ?></li>
            <li><?php echo format(__('Errors: {count}'), ['count' => $import->error_items]); ?></li>
            <?php if ($import->valid_items > 0): ?>
                <li><?php echo format(__('Still to import: {count}'), ['count' => $import->valid_items]); ?></li>
            <?php endif; ?>
        </ul>

        <?php if ($inProgress): ?>
            <script>
                setTimeout(function () { window.location.reload(); }, 5000);
            </script>
        <?php endif; ?>

        <p>
            <?php
            echo $this->Html->link(
                format(__('Download the report ({format})'), ['format' => 'CSV']),
                ['action' => 'report', $import->id, '?' => ['format' => 'csv']]
            );
            ?>
            <?php
            echo $this->Html->link(
                format(__('Download the report ({format})'), ['format' => 'JSON']),
                ['action' => 'report', $import->id, '?' => ['format' => 'json']]
            );
            ?>
        </p>

        <?php if ($import->status === TranslationImportsTable::STATUS_PREVIEWED && $import->valid_items > 0): ?>
            <?php
            echo $this->Form->create(null, [
                'url' => ['action' => 'import', $import->id],
            ]);
            echo $this->Form->submit(__('Import these translations'));
            echo $this->Form->end();
            ?>
        <?php endif; ?>

        <?php
        echo $this->Form->create(null, [
            'url' => ['action' => 'delete', $import->id],
        ]);
        echo $this->Form->submit(__('Delete this import'));
        echo $this->Form->end();
        ?>

        <p>
            <?php echo $this->Html->link(__('Back to your imports'), ['action' => 'index']); ?>
        </p>
    </div>

    <?php
    echo $this->element('translation_import_items', [
        'import' => $import,
        'items' => $items,
        'statusLabels' => $statusLabels,
        'messageLabels' => $messageLabels,
        'status' => $status,
    ]);
    ?>

</div>
