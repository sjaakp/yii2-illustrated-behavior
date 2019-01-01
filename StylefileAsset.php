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

class StylefileAsset extends AssetBundle {
    public $depends = [
        'yii\jui\JuiAsset',
    ];

    public $publishOptions = [
        'only' => [ '*.js' ]
    ];

//    public $sourcePath = '@bower/stylefile';

    public $baseUrl = '//unpkg.com/@sjaakp/stylefile';
    public $js = [ 'jquery.stylefile.min.js' ];

/*    public function init()    {
        parent::init();

        $this->js[] = YII_DEBUG ? 'jquery.stylefile.js' : 'jquery.stylefile.min.js';
    }*/
}
