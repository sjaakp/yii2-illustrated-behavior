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

use yii\base\Object;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;
use yii\imagine\Image;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\FileHelper;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Point;

class Illustration extends Object   {

    /**
     * @var Illustrated
     */
    public $owner;

    /**
     * @var string Name of the source file attribute
     */
    public $attribute;

    /**
     * @var float|string
     * If float: the fixed aspect ratio of the image; it is not saved in the database.
     * If string: name of aspect attribute in the model. The variable aspect ratio is saved in the database.
     * Default: 1.0.
     */
    public $aspectRatio = 1.0;

    /**
     * @var int
     * Size of the largest side of the cropped image, in pixels.
     * For portrait format (aspect ratio < 1.0) it is the height, for landscape format the width.
     * Default: 240.
     */
    public $cropSize = 240;

    /**
     * @var int
     * Number of size variants of the cropped image. Each variant has half the size of the previous one.
     * Example: if $cropSize = 1280, and $sizeSteps = 5, variants will be saved with
     *      sizes 1280, 640, 320, 160, and 80 pixels, each in it's own subdirectory.
     * If $sizeSteps = 0 (default), only one cropped image is saved, with size $cropSize.
     */
    public $sizeSteps = 0;

    /**
     * @var bool If true, images smaller than $this->cropSize are accepted.
     * They are not cropped, but sized to fit in the target size.
     */
    public $allowTooSmall = false;

    /**
     * @var string
     * Error message for images that are too small to crop. Parameters: original file name, width, and height.
     * If $allowTooSmall is false, this setting is not used.
     */
    public $tooSmallMsg = 'Image "%s" is too small (%d×%d).';

    /**
     * @var UploadedFile
     */
    public $file;

    /**
     * @var string Json-encoded crop values
     */
    public $crop;

    /**
     * @var bool Whether or not to delete current image
     */
    public $delete;

    /**
     * @var ImageInterface
     */
    protected $_image;

    /**
     * @var ActiveRecord
     */
    protected $_model;

    public function init()  {
        parent::init();
        $this->_model = $this->owner->owner;
    }

    public function getSafeAttributes() {
        $r = [
            "__{$this->attribute}_crop__",
            "__{$this->attribute}_delete__",
        ];
        if (! is_numeric($this->aspectRatio)) $r[] = $this->aspectRatio;
        return $r;
    }

    /**
     * @param $event
     * Try to apply crop before validate, because it may generate errors
     */
    public function beforeValidate($event)  {
        $file = $this->file = UploadedFile::getInstance($this->_model, "__{$this->attribute}_file__");
        if ($file && ! $file->hasError) {

            $asp = $this->propValue('aspectRatio');

            if ($asp > 30) {        // Uploader widget sets aspect ratio at 1000 x real value
                $asp /= 1000;       // compensate
                $this->_model->{$this->aspectRatio} = $asp;
            }

            // convert crop data from Json to array
            $crop = Json::decode($this->crop);

            // open image
            $image = $this->_image = Image::getImagine()->open($file->tempName);
            $imgSize = $image->getSize();
            $ww = $imgSize->getWidth();
            $hh = $imgSize->getHeight();

            $error = ! $this->allowTooSmall;

            $cropSize = $this->propValue('cropSize');
            
            // Apply crop, if possible
            if ($crop['w'] > 0 && $crop['h'] > 0)    {
                $error = $asp > 1 ? ($crop['w'] < $cropSize) : ($crop['h'] < $cropSize);
                if (! $error) $image->crop(new Point($crop['x'], $crop['y']), new Box($crop['w'], $crop['h']));
            }
            else {
                $asp = $ww / $hh;
                if (! is_numeric($this->aspectRatio)) $this->_model->{$this->aspectRatio} = $asp;
            }

            if ($error)    {       // set error in model
                $this->_model->addError($this->attribute,
                    sprintf($this->tooSmallMsg, $file->name, $ww, $hh));
                $event->isValid = false;
            }
        }
    }

    /**
     * @param $event
     * Set file name and size before model is saved
     */
    public function beforeSave($event)    {

        $file = $this->file;

        if ($file && ! $file->hasError) {
            $this->deleteFiles();       // in case we are updating, delete old image files

            $ext = strtolower($file->extension);
            if ($ext == 'jpeg') $ext = 'jpg';

            $fileName = $this->_model->{$this->attribute} = $this->randomName() . '.' . $ext;

            /** @var ImageInterface $image */
            $image = $this->_image;
            $size = $this->cropSize;
            $aspect = $this->propValue('aspectRatio');

            if ($aspect > 1.0)  {
                $wTarget = $size;
                $hTarget = $size / $aspect;
            }
            else    {
                $wTarget = $size * $aspect;
                $hTarget = $size;
            }

            if ($this->sizeSteps && $size > 0)    {

                $minSize = $size >> ($this->sizeSteps - 1);
                while ($size >= $minSize)  {
                    $this->resize($wTarget, $hTarget);
                    $dir = $this->imgBaseDir . DIRECTORY_SEPARATOR . $size;
                    FileHelper::createDirectory($dir);
                    $image->save($dir . DIRECTORY_SEPARATOR . $fileName);

                    $size >>= 1;
                    $wTarget >>= 1;
                    $hTarget >>= 1;
                }
            }
            else    {
                if ($size > 0) $this->resize($wTarget, $hTarget);
                $dir = $this->imgBaseDir;
                FileHelper::createDirectory($dir);
                $image->save($dir . DIRECTORY_SEPARATOR . $fileName);
            }
        }
        else if ($this->delete) $this->deleteFiles();
    }

    protected function resize($wTarget, $hTarget)   {
        $image = $this->_image;
        $imgSize = $image->getSize();
        $ww = $imgSize->getWidth();
        $hh = $imgSize->getHeight();

        if ($ww > 0 && $hh > 0) {
            $horScale = $wTarget / $ww;
            $vertScale = $hTarget / $hh;
            $scale = min($horScale, $vertScale);

            // don't enlarge
            if ($scale < 1) {
                $image->resize(new Box($scale * $ww, $scale * $hh));
            }
        }
    }

    /**
     * Delete image files and clear imgAttribute
     */
    public function deleteFiles()    {
        $fileName = $this->_model->{$this->attribute};
        if (! empty($fileName)) {

            $size = $this->cropSize;

            if ($this->sizeSteps && $size > 0)    {
                $minSize = $size >> ($this->sizeSteps - 1);
                while ($size >= $minSize)  {
                    $path = $this->imgBaseDir . DIRECTORY_SEPARATOR . $size . DIRECTORY_SEPARATOR . $fileName;
                    if (file_exists($path)) unlink($path);

                    $size >>= 1;
                }
            }
            else    {
                $path = $this->imgBaseDir . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($path)) unlink($path);
            }
            $this->_model->{$this->attribute} = null;
            if (! is_numeric($this->aspectRatio)) $this->_model->{$this->aspectRatio} = null;
        }
    }

    public function getImgHtml($size = 0, $forceSize = true, $options = [])  {
        $url = $this->getImgSrc($size);
        if (! $url) return $this->owner->noImage;

        if ($forceSize && $size > 0) {
            $style = isset($options['style']) ? $options['style'] : '';
            $style .= "max-width:{$size}px;max-height:{$size}px;";
            $options['style'] = $style;
        }

        return Html::img($url, $options);
    }

    public function getImgSrc($size = 0)    {
        $fName = $this->_model->{$this->attribute};
        if (empty($fName)) return false;

        $bUrl = $this->owner->imgRootUrl . "/{$this->attribute}/";

        $s = $this->cropSize;
        if ($this->sizeSteps && $s > 0)    {
            if ($size == 0)
                $url = $bUrl . $s . '/';
            else    {
                $imgSize = $s >> ($this->sizeSteps - 1);
                $step = 0;
                while ($imgSize < $size && $step < $this->sizeSteps) $imgSize <<= 1;

                $url = $bUrl . $imgSize . '/';
            }
        }
        else    {
            $url = $bUrl;
        }
        return $url . $fName;
    }

    public function getImgBaseDir() {
        return $this->owner->imgRootDir . DIRECTORY_SEPARATOR . $this->attribute;
    }

    protected function propValue($property)   {
        $r = $this->{$property};
        if (! is_numeric($r))   {
            $r = $this->_model->{$r};
        }
        return $r;
    }

    /**
     * @return string
     * Create random file name. Override this if you need another name generation.
     * This function returns a random combination of six number characters and lower case letters,
     * allowing for 2 billion file names.
     */
    protected function randomName() {
       do {
            $r = base_convert(mt_rand(60466176 , mt_getrandmax()), 10, 36);  // 60466176 = 36^5; ensure six characters
        } while ($this->_model->findOne(['like', $this->attribute, $r . '%', false]));  // ensure unique
        return $r;
    }
}
