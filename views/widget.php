<?php
use yii\helpers\Html;

$model = $uploader->model;
$attr = $uploader->attribute;
$aspect = $uploader->aspectRatio;

if (! is_numeric($aspect)) {
    $model->setAttribute($aspect, 1000 * $model->getAttribute($aspect));
}
?>

<div id="<?= $uploader->id ?>" class="uploader">
    <div class="cropper"></div>
    <?php
    if ($uploader->aspectOptions)   {
        echo $uploader->radio ? Html::activeRadioList($model, $aspect, $uploader->aspectOptions,
                ['class' => 'uploader-aspect', 'itemOptions' => ['disabled' => 'disabled'] ])
            : Html::activeDropDownList($model, $aspect, $uploader->aspectOptions,
                ['class' => 'uploader-aspect', 'disabled' => 'disabled']);
    }
    ?>
    <div class="uploader-control">
        <?= Html::activeFileInput($model, $attr); ?>
        <?= Html::activeHiddenInput($model, $attr . '_x') ?>
        <?= Html::activeHiddenInput($model, $attr . '_y') ?>
        <?= Html::activeHiddenInput($model, $attr . '_w') ?>
        <?= Html::activeHiddenInput($model, $attr . '_h') ?>
    </div>
</div>