<?php
/**
 * Created by PhpStorm.
 * User: Sjaak
 * Date: 2-6-14
 * Time: 12:16
 */

/**
 * FAQ
 *
 * I'm getting an error like 'Trying to get property of non-object'
 * - You probably didn't give the form the option 'enctype' => 'multipart/form-data'.
 *
 * The image is distorted after upload.
 * - Maybe the owner model is used under a scenario. This scenario should declare the crop attributes
 *      ('<file>_x', '<file>_y', '<file>_w', '<file>_h') in the attribute list.
 *
 * I'm getting an error message: one of the crop dimensions appears to be 0. aspectRatio is a string.
 * - The owner model may be used under a scenario. This scenario should declare the aspect ratio attribute ('aspect') in the attribute list.
 */

namespace sjaakp\illustrated;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\validators\FileValidator;
use yii\validators\SafeValidator;
use yii\web\UploadedFile;
use yii\helpers\Html;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Point;

class Illustrated extends Behavior  {

    /**
     * @var string
     * Name of the file input attribute in the model.
     */
    public $fileAttribute = 'file';

    /**
     * @var string
     * Name of the attribute in the model where the img file name of the resulting cropped image is stored.
     */
    public $imgAttribute = 'img';

    /**
     * @var null|string
     * If null (default): $cropSize is strict, and the crop size is not stored in model.
     * If string: name of the attribute in the model where size is stored. Lower crop size than $cropSize may be accepted.
     */
    public $sizeAttribute;

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
     * @var null|string
     * Directory where cropped images are saved.
     * If null (default), the directory will be '@webroot/<$illustrationDirectory>/<table name>', where table name is
     *  the table name of the model.
     */
    public $directory;

    /**
     * @var string
     * Name of subdirectory under '@webroot' where cropped images are stored.
     */
    public $illustrationDirectory = 'illustrations';

    /**
     * @var string
     * Error message for images that are too small to crop. Parameters: original file name, width, and height.
     */
    public $tooSmallMsg = 'Image "%s" is too small (%d×%d).';

    /**
     * @var array
     * Extra parameters for the validation of the file attribute. By default, only the file types and max files are tested.
     *      You may add things like maximum file size here. See yii\validators\FileValidator.
     */
    public $fileValidation = [];


    protected $_file;
    protected $_image;
    protected $_cropAttributeNames;
    protected $_x;
    protected $_y;
    protected $_w;
    protected $_h;

    // Magic functions to handle file attributes and crop attributes.
    public function __get($name)    {
        if ($name == $this->fileAttribute) return $this->_file;
        $s = array_search($name, $this->_cropAttributeNames);
        if ($s !== false) return $this->$s;
        return parent::__get($name);
    }

    public function __set($name, $value)    {
        if ($name == $this->fileAttribute) $this->_file = $value;
        else    {
            $s = array_search($name, $this->_cropAttributeNames);
            if ($s !== false) $this->$s = $value;
            else parent::__set($name, $value);
        }
    }

    public function canGetProperty($name, $checkVars = true)    {
        if ($name == $this->fileAttribute) return true;
        if (in_array($name, $this->_cropAttributeNames)) return true;
        return parent::canGetProperty($name, $checkVars);
    }

    public function canSetProperty($name, $checkVars = true)    {
        if ($name == $this->fileAttribute) return true;
        if (in_array($name, $this->_cropAttributeNames)) return true;
        return parent::canSetProperty($name, $checkVars);
    }


    public function events()    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * @param $owner ActiveRecord
     */
    public function attach($owner)    {
        parent::attach($owner);

        // cache crop attribute names ('file_x' etc.).
        foreach([ '_x', '_y', '_w', '_h' ] as $d ) $this->_cropAttributeNames[$d] = $this->fileAttribute . $d;

        // determine safe attributes
        $safeAttr = array_values($this->_cropAttributeNames);
        if (! is_numeric($this->aspectRatio)) $safeAttr[] = $this->aspectRatio;

        // add validation rules to model
        $vals = $owner->getValidators();
        $vals[] = new FileValidator(array_merge($this->fileValidation, [
            'attributes' => [ $this->fileAttribute ],
            'extensions'=>'jpg, jpeg, gif, png',
            'skipOnEmpty' => true
        ]));
        $vals[] = new SafeValidator([
            'attributes' => $safeAttr
        ]);
    }

    public function beforeValidate($event)  {
        $upl = new UploadedFile();
        $this->_file = $upl->getInstance($this->owner, $this->fileAttribute);
        if ($this->_file && ! $this->_file->hasError)   {

            /**
             * @var $owner ActiveRecord
             */
            $owner = $this->owner;

            // Determine aspect ratio
            if (is_numeric($this->aspectRatio))   {
                $asp = $this->aspectRatio;
            }
            else    {
                $asp = $owner->getAttribute($this->aspectRatio);
                if ($asp > 30) {        // Uploader widget sets aspect ratio at 1000 x real value
                    $asp /= 1000;       // compensate
                    $owner->setAttribute($this->aspectRatio, $asp);
                }
            }

            // Open image
            $this->_image = Image::getImagine()->open($this->_file->tempName);
            $imgSize = $this->_image->getSize();
            $ww = $imgSize->getWidth();
            $hh = $imgSize->getHeight();

            $error = true;  // presume error

            // Apply crop, if possible
            if ($this->_w > 0 && $this->_h > 0)    {
                $cropSize = $this->cropSize; 
                if ($this->sizeAttribute && $this->sizeSteps) $cropSize >>= ($this->sizeSteps - 1);     // crop size not strict
                $error = $asp > 1 ? ($this->_w < $cropSize) : ($this->_h < $cropSize);
                if (! $error) $this->_image->crop(new Point($this->_x, $this->_y), new Box($this->_w, $this->_h));
            }

            if ($error)    {       // set error in model
                $owner->addError($this->fileAttribute,
                    sprintf($this->tooSmallMsg, $this->_file->name, $ww, $hh));
                $event->isValid = false;
            }
        }
    }

    public function beforeSave($event)  {
        if (! $this->_file->hasError)  {
            $this->deleteFiles();       // in case we are updating, delete old image files

            /**
             * @var $owner ActiveRecord
             */
            $owner = $this->owner;

            $ext = $this->_file->extension;
            if ($ext == 'jpeg') $ext = 'jpg';

            $owner->setAttribute($this->imgAttribute, $this->randomName() . '.' . $ext);

            if ($this->sizeAttribute && $this->sizeSteps)   {   // if crop size not strict
                $ww = $this->_w;
                $hh = $this->_h;
                $largest = $ww > $hh ? $ww : $hh;
    
                // Determine maximum possible img size
                $minSize = $this->cropSize >> ($this->sizeSteps - 1);
                if ($largest >= $minSize)    {
                    for ($s = $this->cropSize; $s > $largest; $s >>= 1);
                }
                else $s = 0;
                $owner->setAttribute($this->sizeAttribute, $s); // set in model
            }
        }
    }

    public function afterSave($event)  {
        if (! $this->_file->hasError)  {
            /**
             * @var $owner ActiveRecord
             */
            $owner = $this->owner;

            $fileName = $owner->getAttribute($this->imgAttribute);

            $size = $this->sizeAttribute ? $owner->getAttribute($this->sizeAttribute) : $this->cropSize;
            $aspect = is_numeric($this->aspectRatio) ? $this->aspectRatio : $owner->getAttribute($this->aspectRatio);
            if ($aspect > 1.0)  {
                $ww = $size;
                $hh = $size / $aspect;
            }
            else    {
                $ww = $size * $aspect;
                $hh = $size;
            }
            if ($this->sizeSteps && $size > 0)    {

                $minSize = $this->cropSize >> ($this->sizeSteps - 1);
                while ($size >= $minSize)  {
                    $this->_image->resize(new Box($ww, $hh));
                    $dir = $this->getImgBaseDir() . DIRECTORY_SEPARATOR . $size;
                    FileHelper::createDirectory($dir);
                    $this->_image->save($dir . DIRECTORY_SEPARATOR . $fileName);

                    $size >>= 1;
                    $ww >>= 1;
                    $hh >>= 1;
                }
            }
            else    {
                if ($size > 0) $this->_image->resize(new Box($ww, $hh));
                $dir = $this->getImgBaseDir();
                FileHelper::createDirectory($dir);
                $this->_image->save($dir . DIRECTORY_SEPARATOR . $fileName);
            }
        }
    }

    public function beforeDelete($event)  {
        $this->deleteFiles();
    }

    /**
     * @param int $size
     *      The largest side in pixels.
     *      If $sizeSteps > 0, getImgHtml returns the smallest crop variant equal to or bigger than $size.
     *      If $size == 0 (default) the biggest variant is returned.
     * @param bool $forceSize
     *      If true (default), the element css is set to $size.
     * @param array $options
     *      HTML-options of the img-tag; see yii\helpers\Html::img().
     * @return string
     */
    public function getImgHtml($size = 0, $forceSize = true, $options = [])  {
        /**
         * @var $owner ActiveRecord
         */
        $owner = $this->owner;
        if ($owner->isNewRecord) return '';

        $baseUrl = $this->directory ? '@web/' . $this->directory
            : '@web/' . $this->illustrationDirectory . '/' . $owner->tableName();
        $baseUrl .= '/';

        $s = $this->sizeAttribute ? $owner->getAttribute($this->sizeAttribute) : $this->cropSize;
        if ($this->sizeSteps && $s > 0)    {
            if ($size == 0)
                $url = $baseUrl . $s . '/';
            else    {
                $imgSize = $this->cropSize >> ($this->sizeSteps - 1);
                $step = 0;
                while ($imgSize < $size && $step < $this->sizeSteps) $imgSize <<= 1;

                $url = $baseUrl . $imgSize . '/';
            }
        }
        else    {
            $url = $baseUrl;
        }
        if ($forceSize && $size > 0) {
            $style = isset($options['style']) ? $options['style'] : '';
            $style .= "max-width:{$size}px;max-height:{$size}px;";
            $options['style'] = $style;
        }
        return Html::img($url . $owner->getAttribute($this->imgAttribute), $options);
    }

    protected function getImgBaseDir()  {
        return $this->directory ? $this->directory
            : Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $this->illustrationDirectory
                . DIRECTORY_SEPARATOR . $this->owner->tableName();
    }

    protected function deleteFiles()    {
        $owner = $this->owner;

        $fileName = $owner->getAttribute($this->imgAttribute);
        if (! empty($fileName)) {
            $size = $this->sizeAttribute ? $owner->getAttribute($this->sizeAttribute) : $this->cropSize;
            if ($this->sizeSteps && $size > 0)    {
                $minSize = $this->cropSize >> ($this->sizeSteps - 1);
                while ($size >= $minSize)  {
                    $path = $this->getImgBaseDir() . DIRECTORY_SEPARATOR . $size . DIRECTORY_SEPARATOR . $fileName;
                    if (file_exists($path)) unlink($path);

                    $size >>= 1;
                }
            }
            else    {
                $path = $this->getImgBaseDir() . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($path)) unlink($path);
            }
        }
    }

    protected function randomName() {
        /**
         * @var $owner ActiveRecord
         */
        $owner = $this->owner;

        // Create random file name. Override this if you need another name generation.
        // This function returns a random combination of six number characters and lower case letters,
        // allowing for 2 billion file names.
        do {
            $r = base_convert(mt_rand(60466176 , mt_getrandmax()), 10, 36);  // 60466176 = 36^5; ensure six characters

        } while ($owner->findOne(['like', $this->imgAttribute, $r . '%', false]));  // ensure unique
        return $r;
    }
}
