<?php
/**
 * MIT licence
 * Version 1.1.0
 * Sjaak Priester, Amsterdam 07-07-2014 ... 12-11-2015.
 *
 * Add illustrations to ActiveRecord in Yii 2.0 framework
 *
 * @link https://github.com/sjaakp/yii2-illustrated-behavior
 * @link http://www.sjaakpriester.nl/software/illustrated
 */

namespace sjaakp\illustrated;

use yii\web\AssetBundle;

class UploaderAsset extends AssetBundle {
    public $depends = [
        'yii\jui\JuiAsset',
    ];

    public function init()    {
        parent::init();

        $this->sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
        $this->js[] = YII_DEBUG ? 'js/jquery.stylefile.js' : 'js/jquery.stylefile.min.js';
        $this->js[] = YII_DEBUG ? 'js/jquery.cropper.js' : 'js/jquery.cropper.min.js';
        $this->css[] = 'css/uploader.css';
    }
}