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
 */

use App\Model\Table\TranslationImportsTable;

$this->set('title_for_layout', $this->Pages->formatTitle(__('Import translations')));

$statusLabels = TranslationImportsTable::statusLabels();
?>

<div id="main_content">

    <div class="module">
        <h2><?php echo __('Import translations'); ?></h2>

        <p>
            <?php echo __('You can bulk import translations for existing sentences by uploading a file.'); ?>
            <?php
            echo $this->Html->link(
                __('Upload a new file'),
                ['action' => 'upload']
            );
            ?>
        </p>
    </div>

    <div class="module">
        <?php $total = $this->Paginator->param('count'); ?>
        <h2>
            <?php
            echo __('Your imports');
            if ($total) {
                echo ' ' . format(__('(total {total})'), ['total' => $total]);
            }
            ?>
        </h2>

        <?php if (count($imports) === 0): ?>
            <p><?php echo __('You have not imported any translations yet.'); ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo __('File'); ?></th>
                        <th><?php echo __('Languages'); ?></th>
                        <th><?php echo __('Context'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th><?php echo __('Imported'); ?></th>
                        <th><?php echo __('Date'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($imports as $import): ?>
                    <tr>
                        <td><?php echo h($import->filename); ?></td>
                        <td>
                            <?php
                            echo h($import->source_lang ? $import->source_lang . ' → ' : '→ ');
                            echo h($import->target_lang);
                            ?>
                        </td>
                        <td><?php echo h($import->context); ?></td>
                        <td>
                            <?php
                            echo h($statusLabels[$import->status] ?? $import->status);
                            switch ($import->status) {
                                case TranslationImportsTable::STATUS_PREVIEWED:
                                    echo ' ' . $this->Html->link(
                                        __('preview'),
                                        ['action' => 'preview', $import->id]
                                    );
                                    break;
                                case TranslationImportsTable::STATUS_PENDING:
                                case TranslationImportsTable::STATUS_VALIDATING:
                                case TranslationImportsTable::STATUS_IMPORTING:
                                case TranslationImportsTable::STATUS_COMPLETED:
                                case TranslationImportsTable::STATUS_FAILED:
                                    echo ' ' . $this->Html->link(
                                        __('view'),
                                        ['action' => 'view', $import->id]
                                    );
                                    break;
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            echo format(
                                __('{imported} / {total}'),
                                [
                                    'imported' => $import->imported_items,
                                    'total' => $import->total_items,
                                ]
                            );
                            ?>
                        </td>
                        <td><?php echo $import->created ? h($import->created->format('Y-m-d H:i')) : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php $this->Pagination->display(); ?>
        <?php endif; ?>
    </div>

</div>
