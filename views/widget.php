<?php
/**
 * MIT licence
 * Version 1.1.5
 * Sjaak Priester, Amsterdam 07-07-2014 ... 12-11-2015.
 *
 * Add illustrations to ActiveRecord in Yii 2.0 framework
 *
 * @link https://github.com/sjaakp/yii2-illustrated-behavior
 * @link http://www.sjaakpriester.nl/software/illustrated
 */

use yii\helpers\Html;
use yii\db\ActiveRecord;
use sjaakp\illustrated\Uploader;

/**
 * @var $uploader Uploader
 * @var $model ActiveRecord
 */
$model = $uploader->model;
$attr = $uploader->attribute;
$cfg = $uploader->illustration;
$aspect = $cfg->aspectRatio;

if (! is_numeric($aspect)) {
    $model->setAttribute($aspect, 1000 * $model->getAttribute($aspect));
}
?>

<div id="<?= $uploader->id ?>" class="uploader">
    <div class="cropper"></div>
    <div class="uploader-aspect">
        <?php
        if (! is_numeric($aspect) && $uploader->aspectOptions)   {
            echo $uploader->radio ? Html::activeRadioList($model, $aspect, $uploader->aspectOptions,
                    [
                        'class' => 'uploader-select',
                        'separator' => '<br />',
                        'itemOptions' => ['disabled' => 'disabled']
                    ])
                : Html::activeDropDownList($model, $aspect, $uploader->aspectOptions,
                    [
                        'class' => 'form-control uploader-select',
                        'disabled' => 'disabled'
                    ]);
        }
        ?>
        <?= $uploader->deleteOptions ? Html::activeCheckbox($model, "__{$attr}_delete__", array_merge($uploader->deleteOptions, [
            'disabled' => ! $uploader->current,
            'class' => 'del-switch'
        ])) : ''; ?>
    </div>
    <div class="uploader-control">
        <?= Html::activeFileInput($model, "__{$attr}_file__"); ?>
        <?= Html::activeHiddenInput($model, "__{$attr}_crop__"); ?>
    </div>
</div>