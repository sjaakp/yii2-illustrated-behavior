<?php
/**
 * MIT licence
 * Version 2.0.0
 * Sjaak Priester, Amsterdam 07-07-2014 ... 17-05-2024.
 *
 * Add illustrations to ActiveRecord in Yii 2.0 framework
 *
 * @link https://github.com/sjaakp/yii2-illustrated-behavior
 * @link http://www.sjaakpriester.nl/software/illustrated
 */

namespace sjaakp\illustrated;

use yii\web\AssetBundle;

class CropperAsset extends AssetBundle {
    public $sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
    public $js = [ 'cropper.js' ];
}