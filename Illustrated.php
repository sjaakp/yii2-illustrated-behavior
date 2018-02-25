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
 *
 * FAQ
 * I'm getting an error like 'Trying to get property of non-object'
 * - You probably didn't give the form the option 'enctype' => 'multipart/form-data'.
 */

namespace sjaakp\illustrated;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\validators\FileValidator;
use yii\validators\SafeValidator;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

class Illustrated extends Behavior  {

    /**
     * @var array list of illustration attributes
     * key is the name of the attribute that stores the file name of the resulting cropped image.
     * value is an array with the following members:
     * - cropSize:  int - Size of the largest side of the cropped image, in pixels.
     *              For portrait format (aspect ratio < 1.0) it is the height, for landscape format the width.
     *              If noet set, the default is taken. Default: 240.
     * - aspectRatio: float|string - If float: the fixed aspect ratio of the image; it is not saved in the database.
     *              If string: name of aspect attribute in the model. The variable aspect ratio is saved in the database.
     *              If not set, the default is taken Default: 1.0.
     * - sizeSteps: int - Number of size variants of the cropped image. Each variant has half the size of the previous one.
     *              Example: if cropSize = 1280, and sizeSteps = 5, variants will be saved with
     *                  sizes 1280, 640, 320, 160, and 80 pixels, each in it's own subdirectory.
     *              If sizeSteps = 0 or is not set, only one cropped image is saved, with size cropSize.
     * - allowTooSmall: bool - If true, images which are too small to crop are accepted. They will be sized to fit
     *              in the target size, defined bij cropSize and aspectRatio.
     */
    public $attributes = [];

    /**
     * @var null|string
     * Directory or alias where cropped images are saved.
     * If null (default), the directory will be '@webroot/<$illustrationDirectory>/<table name>', where table name is
     *  the table name of the model.
     */
    public $directory;

    /**
     * @var null|string
     * Base URL or alias where cropped images reside.
     * If null (default), the URL will be '@web/<$illustrationDirectory>/<table name>', where table name is
     *  the table name of the model.
     */
    public $baseUrl;

    /**
     * @var string
     * Name of subdirectory under '@webroot' where cropped images are stored.
     */
    public $illustrationDirectory = 'illustrations';

    /**
     * @var string
     * HTML returned if $imgAttribute is empty.
     */
    public $noImage = '';

    /**
     * @var array
     * Extra parameters for the validation of the file attribute. By default, only the file types and max files are tested.
     *      You may add things like maximum file size here. @link http://www.yiiframework.com/doc-2.0/yii-validators-filevalidator.html .
     */
    public $fileValidation = [];


    /** Each attribute is associated with a few virtual attributes
     * __<attr>_file__      UploadedFile
     * __<attr>_image__     ImageInterface
     * __<attr>_crop__      Json encoded crop values
     * __<attr>_delete__    bool
     * They are stored in $this->attributes under their base name ('file', 'image' etc.
     */

    // Magic functions to handle 'subattributes'.
    // Attributes of the form '__<a>_<b>__' are delegated to $this->attributes[<a>]-><b>
    public function __get($name)    {
        $matches = null;
        if (preg_match('/__(\\w+)_(\\w+)__/', $name, $matches))    {
            $attr = $matches[1];
            $vattr = $matches[2];
            if (isset($this->attributes[$attr])) return $this->attributes[$attr]->{$vattr};
        }
        return parent::__get($name);
    }

    public function __set($name, $value)    {
        $matches = null;
        if (preg_match('/__(\\w+)_(\\w+)__/', $name, $matches))    {
            $attr = $matches[1];
            $vattr = $matches[2];
            if (isset($this->attributes[$attr])) $this->attributes[$attr]->$vattr = $value;
        }
        else parent::__set($name, $value);
    }

    public function canGetProperty($name, $checkVars = true)    {
        $matches = null;
        if (preg_match('/__(\\w+)_(\\w+)__/', $name, $matches))    {
            $attr = $matches[1];
            $vattr = $matches[2];
            if (isset($this->attributes[$attr]))    {
                /** @var Illustration $cfg */
                $cfg = $this->attributes[$attr];
                return $checkVars ? $cfg->hasProperty($vattr) : true;
            }
        }
        return parent::canGetProperty($name, $checkVars);
    }

    public function canSetProperty($name, $checkVars = true)    {
        $matches = null;
        if (preg_match('/__(\\w+)_(\\w+)__/', $name, $matches))    {
            $attr = $matches[1];
            $vattr = $matches[2];
            if (isset($this->attributes[$attr]))    {
                /** @var Illustration $cfg */
                $cfg = $this->attributes[$attr];
                return $checkVars ? $cfg->hasProperty($vattr) : true;
            }
        }
        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function events()    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * @param $owner ActiveRecord
     */
    public function attach($owner)    {
        parent::attach($owner);

        $attrs = [];
        $fileAttrs = [];
        $safeAttrs = [];
        foreach ($this->attributes as $attr => $illustration)    {
            if (is_numeric($attr) && is_string($illustration))  {
                $attr = $illustration;
                $illustration = [];
            }
            if (is_array($illustration)) $illustration = Yii::createObject(array_merge([
                'class' => Illustration::class,
                'owner' => $this,
                'attribute' => $attr
            ], $illustration));

            $fileAttrs[] = "__{$attr}_file__";
            $safeAttrs = array_merge($safeAttrs, $illustration->safeAttributes);

            $attrs[$attr] = $illustration;
        }
        $this->attributes = $attrs;

        // add validation rules to model
        $vals = $owner->getValidators();
        $vals[] = new FileValidator(array_merge($this->fileValidation, [
            'attributes' => $fileAttrs,
            'extensions' => 'jpg, jpeg, gif, png',
            'skipOnEmpty' => true,
            'on' => $owner->scenario
        ]));
        $vals[] = new SafeValidator([
            'attributes' => $safeAttrs,
            'on' => $owner->scenario
        ]);
    }

    /**
     * @param $event
     */
    public function beforeValidate($event)  {
        foreach ($this->attributes as $attr => $cfg)    {
            /** @var Illustration $cfg */
            $cfg->beforeValidate($event);
        }
    }

    /**
     * @param $event
     */
    public function beforeSave($event)  {
        foreach ($this->attributes as $attr => $cfg)    {
            /** @var Illustration $cfg */
            $cfg->beforeSave($event);
        }
    }

    /**
     * @param $event
     */
    public function beforeDelete($event)  {
        foreach ($this->attributes as $attr => $cfg) {
            /** @var Illustration $cfg */
            $cfg->deleteFiles();
        }
    }

    /**
     *
     * @param $attribute string
     * @param int $size
     *      The largest side in pixels.
     *      If $sizeSteps > 0, getImgHtml returns the smallest crop variant equal to or bigger than $size.
     *      If $size == 0 (default) the biggest variant is returned.
     * @param bool $forceSize
     *      If true (default), the element CSS is set to $size.
     * @param array $options
     *      HTML-options of the img-tag; @link http://www.yiiframework.com/doc-2.0/yii-helpers-basehtml.html#img()-detail .
     * @return string Override this function to specialize it.
     * Override this function to specialize it.
     */
    public function getImgHtml($attribute, $size = 0, $forceSize = true, $options = [])  {
        return $this->getImgHtmlInternal($attribute, $size, $forceSize, $options);
    }

    public function getImgHtmlInternal($attribute, $size, $forceSize, $options)  {
        /** @var Illustration $cfg */
        $cfg = $this->attributes[$attribute];
        return $cfg->getImgHtml($size, $forceSize, $options);
    }

    /**
     *
     * @param $attribute string
     * @param int $size
     *      The largest side in pixels.
     *      If $sizeSteps > 0, getImgHtml returns the smallest crop variant equal to or bigger than $size.
     *      If $size == 0 (default) the biggest variant is returned.
     * @return string The url of the image source, or false if not set.
     */
    public function getImgSrc($attribute, $size = 0)  {
        /** @var Illustration $cfg */
        $cfg = $this->attributes[$attribute];
        return $cfg->getImgSrc($size);
    }

    protected function getImgRootDir()  {
        $r = $this->directory ?: '@webroot/' . $this->illustrationDirectory . '/' . $this->baseName;
        return Yii::getAlias($r);
    }

    protected function getImgRootUrl()  {
        $r = $this->baseUrl ?: '@web/' . $this->illustrationDirectory . '/' . $this->baseName;
        return Yii::getAlias($r);
    }

    protected function getBaseName()    {
        return Inflector::camel2id(StringHelper::basename($this->owner->className()), '_');
    }
}
