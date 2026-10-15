<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * TranslationImport Entity
 *
 * Represents one bulk (mass) translation import: an uploaded file together
 * with the context in which the translations were made.
 *
 * See https://github.com/Tatoeba/tatoeba2/issues/3270
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $queued_job_id
 * @property string|null $source_lang
 * @property string $target_lang
 * @property string|null $context
 * @property string|null $filename
 * @property string $status
 * @property int $total_items
 * @property int $valid_items
 * @property int $imported_items
 * @property int $skipped_items
 * @property int $error_items
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 */
class TranslationImport extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array
     */
    protected $_accessible = [
        'user_id' => true,
        'queued_job_id' => true,
        'source_lang' => true,
        'target_lang' => true,
        'context' => true,
        'filename' => true,
        'status' => true,
        'total_items' => true,
        'valid_items' => true,
        'imported_items' => true,
        'skipped_items' => true,
        'error_items' => true,
        'created' => true,
        'modified' => true,
    ];
}
