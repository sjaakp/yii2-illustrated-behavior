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

class CropperAsset extends AssetBundle {
    public $depends = [
        'yii\jui\JuiAsset',
    ];

    public $publishOptions = [
        'only' => [ 'js/*', 'css/*' ]
    ];

    public $baseUrl = '//unpkg.com/@sjaakp/cropper';
    public $js = [ 'js/jquery.cropper.min.js' ];
    public $css = [ 'css/jquery.cropper.min.css' ];
}