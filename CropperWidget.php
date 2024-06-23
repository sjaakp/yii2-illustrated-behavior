<?php

/**
 * MIT licence
 * Version 2.0.0
 * Sjaak Priester, Amsterdam 07-07-2014 ... 23-06-2024.
 *
 * Add illustrations to ActiveRecord in Yii 2.0 framework
 *
 * @link https://github.com/sjaakp/yii2-illustrated-behavior
 * @link http://sjaakpriester.nl/software/illustrated
 *
 */

namespace sjaakp\illustrated;

use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\InputWidget;

class CropperWidget extends InputWidget
{
    /**
     * @var array $options
     * Client options for the cropper control (without "data-" prefix).
     * @link https://github.com/sjaakp/cropper
     */
    public $options = [];

    /**
     * @var array $translations
     * Translations for the cropper control.
     * @link https://github.com/sjaakp/cropper
     */
    public $translations = [];


    /**
     * @throws InvalidConfigException
     * $this->attribute is the attribute name of the stored and cropped file name
     */
    public function run()
    {
        if (!$this->hasModel() || !$this->attribute) {
            throw new InvalidConfigException('Uploader widget must have model and attribute.');
        }

        $view = $this->view;
        $id = $this->id;

        $view->registerAssetBundle(CropperAsset::class);

        $encTranslations = $this->encode($this->translations);

        $current = $this->model->getSrc($this->attribute);
        $load = $current ? ".loadImage('$current',false)" : '.enable(false)';

        $view->registerJs("cropper($encTranslations);document.getElementById('$id').cropper$load;");

        echo Html::activeFileInput($this->model, $this->attribute, [ 'id' => $id, 'accept' => 'image/*', 'data' => $this->options ]);
    }

    protected function encode($val) : string {
        return empty($val) ? '{}' : Json::encode($val);
    }
}
