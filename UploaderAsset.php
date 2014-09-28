<?php
/**
 * Created by PhpStorm.
 * User: Sjaak
 * Date: 25-5-14
 * Time: 15:24
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