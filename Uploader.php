<?php
/**
 * Created by PhpStorm.
 * User: Sjaak
 * Date: 25-5-14
 * Time: 15:24
 *
 * Jcrop control by Deep Liquid:
 * http://deepliquid.com/content/Jcrop.html
 *
 */

namespace sjaakp\illustrated;

use yii\web\JsExpression;
use yii\helpers\Json;
use yii\widgets\InputWidget;
use yii\base\InvalidConfigException;
use Yii;


class Uploader extends InputWidget {

    /**
     * @var float|string
     * If float: the fixed aspect ratio of the image; it is not saved in the database.
     *       Example: aspectRatio = .75 for portrait format (3x4).
     * If string: name of aspect attribute in the model. The variable aspect ratio is stored in the database.
     * Default: 1.0.
     */
    public $aspectRatio = 1.0;

    /**
     * @var int
     * Size of the (largest) cropped image in pixels. Default: 240.
     */
    public $cropSize = 240;

    /**
     * @var boolean|array $aspectOptions
     * If false: aspect ratio is fixed (default).
     * If array: list of available aspect ratios.
     *          Keys are aspect ratio multiplied by 1000, values are description of aspect ratio's.
     *          One of the array keys should correspond to aspectRatio (i.e.: be 1000 x aspectRatio).
     *
     * Example of array value:
     *     $this->aspectOptions = [     // 1000 times real aspect ratio
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
     * If $aspectOptions is false, $defaultAspect is ignored.
     */
    public $defaultAspect = 1.0;

    /**
     * @var bool
     * - false (default): the aspect ratio can be chosen from a drop down list
     * - true           : the aspect ratio can be chosen in a radio button list
     * Only applies when $aspectOptions != false.
     */
    public $radio = false;

    /**
     * @var array $stylefileOptions
     * Extra client options for the stylefile file input control.
     * See: https://github.com/sjaakp/stylefile
     */
    public $stylefileOptions = [];

    /**
     * @var array $cropperOptions
     * Extra client options for the cropper control.
     * See: https://github.com/sjaakp/cropper
     */
    public $cropperOptions = [];


    public function run()    {
        if (! $this->hasModel() || ! $this->attribute)    {
            throw new InvalidConfigException('Uploader widget must have model and attribute.');
        }
        if (! is_numeric($this->aspectRatio) && $this->model->isNewRecord) $this->model->setAttribute($this->aspectRatio, $this->defaultAspect);

        $widget = $this->getViewPath() . DIRECTORY_SEPARATOR . 'widget.php';
        echo $this->getView()->renderFile($widget, [
            'uploader' => $this
        ]);

        $this->registerClientScript();
    }

    public function registerClientScript()    {
        $view = $this->getView();

        $asset = new UploaderAsset();
        $asset->register($view);

        $id = $this->getId();

        $aspect = is_numeric($this->aspectRatio) ? $this->aspectRatio : $this->defaultAspect;
        $this->cropperOptions['aspectRatio'] = $aspect;
        $this->cropperOptions['minSize'] = $this->cropSize;
        $this->cropperOptions['change'] = new JsExpression('function(evt, c) {
            $("[id$=_x]").val(c.x);
            $("[id$=_y]").val(c.y);
            $("[id$=_w]").val(c.w);
            $("[id$=_h]").val(c.h);
        }');

        $cropOpts = !empty($this->cropperOptions) ? Json::encode($this->cropperOptions) : '{}';
        $stylefileOpts = !empty($this->stylefileOptions) ? Json::encode($this->stylefileOptions) : '{}';

        $initAsp = '';
        if ($this->aspectOptions) {
            $aspect *= 1000;
            if ($this->radio)   {
                $view->registerJs("$('#$id [type=radio]').on('change',function(){ $id.cropper('option','aspectRatio',this.value/1000);})");
                $initAsp = "$('#$id [type=radio]').removeAttr('disabled').filter('[value='+$aspect+']').attr('checked','checked');";
            }
            else    {
                $view->registerJs("$('#$id .uploader-aspect').on('change',function(){ $id.cropper('option','aspectRatio',this.value/1000);})");
                $initAsp = "$('#$id .uploader-aspect').removeAttr('disabled').val($aspect);";
            }
        }

        $view->registerJs("var $id=$('#$id .cropper').cropper($cropOpts);");
        $view->registerJs("$('#$id [type=file]').stylefile($stylefileOpts).on('change',function(evt){ $initAsp$id.cropper('loadImage',evt.target.files);});");
    }
}