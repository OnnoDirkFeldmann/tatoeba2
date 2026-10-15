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
 * Renders the items of a translation import.
 *
 * @var \App\Model\Entity\TranslationImport $import
 * @var iterable $items
 * @var array $statusLabels
 * @var array $messageLabels
 * @var string $status
 */

$action = $this->request->getParam('action');
$filters = ['' => __('All')] + $statusLabels;
?>

<div class="module">
    <h2><?php echo __('Items'); ?></h2>

    <p class="import-filters">
        <?php foreach ($filters as $key => $label): ?>
            <?php if ((string)$key === (string)$status): ?>
                <strong><?php echo h($label); ?></strong>
            <?php else: ?>
                <?php
                echo $this->Html->link(
                    $label,
                    ['action' => $action, $import->id, '?' => ['status' => $key]]
                );
                ?>
            <?php endif; ?>
            &nbsp;
        <?php endforeach; ?>
    </p>

    <?php if (count($items) === 0): ?>
        <p><?php echo __('No item matches this filter.'); ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?php echo __('Line'); ?></th>
                    <th><?php echo __('Sentence'); ?></th>
                    <th><?php echo __('Translation'); ?></th>
                    <th><?php echo __('Status'); ?></th>
                    <th><?php echo __('Details'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo h($item->row_number); ?></td>
                    <td>
                        <?php if ($item->sentence_id): ?>
                            <?php
                            echo $this->Html->link(
                                $item->sentence_id,
                                ['controller' => 'sentences', 'action' => 'show', $item->sentence_id]
                            );
                            ?>
                            <br>
                        <?php endif; ?>
                        <?php echo h($item->source_text); ?>
                    </td>
                    <td><?php echo h($item->translation_text); ?></td>
                    <td>
                        <?php echo h($statusLabels[$item->status] ?? $item->status); ?>
                    </td>
                    <td>
                        <?php if ($item->message): ?>
                            <?php echo h($messageLabels[$item->message] ?? $item->message); ?>
                        <?php endif; ?>
                        <?php if ($item->translation_id): ?>
                            <br>
                            <?php
                            echo $this->Html->link(
                                format(__('translation #{id}'), ['id' => $item->translation_id]),
                                ['controller' => 'sentences', 'action' => 'show', $item->translation_id]
                            );
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php $this->Pagination->display(); ?>
    <?php endif; ?>
</div>
