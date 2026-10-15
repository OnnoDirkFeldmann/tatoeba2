<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * TranslationImportItem Entity
 *
 * Represents one line of an uploaded translation import file, together with
 * the validation/import result for that line. The stored `source_text` is a
 * snapshot of the sentence as it appeared in the file; it is used to detect
 * whether the original sentence was modified since the translation was made.
 *
 * See https://github.com/Tatoeba/tatoeba2/issues/3270
 *
 * @property int $id
 * @property int $translation_import_id
 * @property int $row_number
 * @property int|null $sentence_id
 * @property string|null $source_text
 * @property string|null $translation_text
 * @property int|null $translation_id
 * @property string $status
 * @property string|null $message
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 */
class TranslationImportItem extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array
     */
    protected $_accessible = [
        'translation_import_id' => true,
        'row_number' => true,
        'sentence_id' => true,
        'source_text' => true,
        'translation_text' => true,
        'translation_id' => true,
        'status' => true,
        'message' => true,
        'created' => true,
        'modified' => true,
    ];
}
