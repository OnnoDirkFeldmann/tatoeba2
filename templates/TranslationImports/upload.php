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

$this->set('title_for_layout', $this->Pages->formatTitle(__('Import translations')));

$langArray = $this->Languages->onlyLanguagesArray();
?>

<div id="main_content">

    <div class="module">
        <h2><?php echo __('Upload a translation file'); ?></h2>

        <p>
            <?php echo __('Upload a file with translations for existing sentences. Your file will be validated before anything is added to Tatoeba.'); ?>
        </p>

        <p>
            <?php
            echo $this->Html->link(
                __('Back to your imports'),
                ['action' => 'index']
            );
            ?>
        </p>

        <?php
        echo $this->Form->create(
            null,
            [
                'url' => ['action' => 'create'],
                'type' => 'file',
            ]
        );
        ?>

        <div>
            <?php
            echo $this->Form->control('target_lang', [
                'type' => 'select',
                'options' => $langArray,
                'label' => __('Language of the translations'),
                'empty' => __('Select a language'),
                'required' => true,
            ]);
            ?>
        </div>

        <div>
            <?php
            echo $this->Form->control('source_lang', [
                'type' => 'select',
                'options' => $langArray,
                'label' => __('Language of the original sentences'),
                'empty' => true,
            ]);
            ?>
        </div>

        <div>
            <?php
            echo $this->Form->control('context', [
                'type' => 'text',
                'label' => __('Project or source of these translations'),
                'placeholder' => __('For example: the name of the project these translations were made for'),
                'required' => false,
            ]);
            ?>
        </div>

        <div>
            <?php
            echo $this->Form->control('file', [
                'type' => 'file',
                'label' => __('File'),
                'required' => true,
            ]);
            ?>
        </div>

        <?php echo $this->Form->submit(__('upload')); ?>
        <?php echo $this->Form->end(); ?>
    </div>

</div>
