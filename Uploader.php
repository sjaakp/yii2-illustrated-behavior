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

use yii\helpers\Html;
use yii\web\JsExpression;
use yii\helpers\Json;
use yii\widgets\InputWidget;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;


class Uploader extends InputWidget {

    /**
     * @var array
     * List of available aspect ratios.
     *          Keys are aspect ratio multiplied by 1000, values are description of aspect ratio's.
     * If Illustration::aspectRatio is numeric, this setting is ignored.
     *
     * Example of array value:
     *
     *     $uploader->aspectOptions = [     // 1000 times real aspect ratio
     *          429 => 'tower (9×21)',      // aspect ratio is 0.429
     *          563 => 'high (9×16)',
     *          750 => 'portrait (3×4)',
     *          1000 => 'square (1×1)',      // aspect ratio is 1.0
     *          1333 => 'landscape (4×3)',
     *          1778 => 'wide (16×9)',
     *          2333 => 'cinema (21×9)'     // aspect ratio is 2.333
     *     ];
     */
    public $aspectOptions = false;

    /**
     * @var float
     * Default selection of the aspect options. Note that there should be a corresponding key in the
     *      $aspectOptions array. F.i.: if $defaultAspect is 0.75, one of the keys in the array
     *      should be 1000 x .75 = 750.
     * If Illustration::aspectRatio is numeric, this setting is ignored.
     */
    public $defaultAspect = 1.0;

    /**
     * @var bool
     * - false (default): the aspect ratio can be chosen from a drop down list
     * - true           : the aspect ratio can be chosen in a radio button list
     * If Illustration::aspectRatio is numeric, this setting is ignored.
     */
    public $radio = false;

    /**
     * @var array $stylefileOptions
     * Extra client options for the stylefile file input control.
     * @link https://github.com/sjaakp/stylefile
     */
    public $stylefileOptions = [];

    /**
     * @var array $cropperOptions
     * Extra client options for the cropper control.
     * @link https://github.com/sjaakp/cropper
     */
    public $cropperOptions = [];

    /**
     * @var bool|array
     * Options for delete checkbox
     * If false, no delete checkbox is rendered.
     * @link http://www.yiiframework.com/doc-2.0/yii-helpers-basehtml.html#activeCheckbox()-detail
     */
    public $deleteOptions = [
        'label' => 'Delete image'
    ];


    protected $_current = false;

    protected $_illustration;

    /**
     * @throws InvalidConfigException
     *
     * $this->attribute is the attribute name of the stored and cropped file name
     * Other attributes are derived from that:
     * __<attr>_file__
     * __<attr>_crop__
     * __<attr>_delete__
     *
     */
    public function run()    {
        if (! $this->hasModel() || ! $this->attribute)    {
            throw new InvalidConfigException('Uploader widget must have model and attribute.');
        }

        $ill = $this->illustration;
        $view = $this->view;
        $id = $this->id;

        /** @var ActiveRecord $model */
        $model = $this->model;

        if (! is_numeric($ill->aspectRatio) && ! $model->{$ill->aspectRatio}) $model->{$ill->aspectRatio} = $this->defaultAspect;

        $view->registerAssetBundle(CropperAsset::class);
        $view->registerAssetBundle(StylefileAsset::class);

        $cropVal = Html::getInputId($model, "__{$this->attribute}_crop__");
        $delSwitch = Html::getInputId($model, "__{$this->attribute}_delete__");

        $aspect = is_numeric($ill->aspectRatio) ? $ill->aspectRatio : $this->defaultAspect;
        $this->cropperOptions['aspectRatio'] = $aspect;
        $this->cropperOptions['minSize'] = $ill->cropSize;
        $this->cropperOptions['change'] = new JsExpression("function(e,c) { $('#$cropVal').val(JSON.stringify(c)); }");

        $cropOpts = $this->encode($this->cropperOptions);
        $stylefileOpts = $this->encode($this->stylefileOptions);

        $initAsp = '';
        if ($this->aspectOptions) {
            $aspect *= 1000;
            if ($this->radio)   {
                $view->registerJs("$('#$id [type=radio]').on('change',function(){ $id.cropper('option','aspectRatio',this.value/1000);})");
                $initAsp = "$('#$id [type=radio]').prop('disabled',false)/*.filter('[value='+$aspect+']').prop('checked',true)*/;";
            }
            else    {
                $view->registerJs("$('#$id .uploader-select').on('change',function(){ $id.cropper('option','aspectRatio',this.value/1000);})");
                $initAsp = "$('#$id .uploader-select').prop('disabled',false).val($aspect);";
            }
        }

        $view->registerJs("var $id=$('#$id .cropper').cropper($cropOpts);");
        $view->registerJs("$('#$id [type=file]').stylefile($stylefileOpts).on('change',function(evt){ $initAsp $id.cropper('loadImage',evt.target.files);$(this).closest('.uploader').find('.del-switch').prop('checked',false).prop('disabled',true);});");
        $view->registerJs("$('#$delSwitch').change(function(){ $(this).closest('.uploader').find('img').fadeTo('slow', $(this).prop('checked') ? .2 : 1);});");

        $current = $this->_current = $ill->imgSrc;
        if ($current)   {
            $view->registerJs("$id.cropper('loadImage', '$current');");
        }

        $widget = $this->viewPath . DIRECTORY_SEPARATOR . 'widget.php';
        echo $view->renderFile($widget, [
            'uploader' => $this,
        ]);
    }

    public function getIllustration() {
        if (is_null($this->_illustration))    {
            /** @var ActiveRecord $model  */
            $model = $this->model;

            $behavior = null;

            foreach ($model->behaviors as $b)   {
                if ($b instanceof Illustrated) {
                    $behavior = $b;
                    break;
                }
            }

            if (! $behavior)    {
                throw new InvalidConfigException('Uploader: model must have Illustrated behavior.');
            }

            $this->_illustration = $behavior->attributes[$this->attribute];
        }
        return $this->_illustration;
    }

    public function getCurrent()    {
        return $this->_current;
    }

    protected function encode($val) {
        return empty($val) ? '{}' : Json::encode($val);
    }
}
