<?php

/**
 * MIT licence
 * Version 2.0.0
 * Sjaak Priester, Amsterdam 07-07-2014 ... 23-06-2024.
 *
 * Add illustrations to ActiveRecord in Yii 2.0 framework
 *
 * @link https://github.com/sjaakp/yii2-illustrated-behavior
 * @link http://sjaakpriester.nl/software/illustrated
 *
 */

namespace sjaakp\illustrated;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\validators\FileValidator;
use yii\validators\SafeValidator;
use yii\helpers\Json;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\web\UploadedFile;

class Illustrated extends Behavior  {
    /** @var yii\db\ActiveRecordInterface $owner */

    /**
     * @var array list of illustration attributes
     * key is the name of the attribute that stores the file name of the resulting cropped image.
     * value is an array with the following members:
     * - cropWidth:  int - Horizontal size of the cropped image, in pixels. If not set,
     *              the cropped image is saved with the maximum possible width.
     * - cropSteps: int - Number of size variants of the cropped image. Each variant has half the width of the previous one.
     *              Example: if cropWidth = 1280, and cropSteps = 5, variants will be saved with
     *                  widths 1280, 640, 320, 160, and 80 pixels, each in its own subdirectory.
     *              If cropSteps = 0, only one cropped image is saved, with width cropWidth.
     *              If cropSteps is not set, it is set to defaultSteps (4).
     * - rejectTooSmall: bool - If false, images which are too small to crop are accepted.
     *              If not set, it is assumed to be true.
     */
    public $attributes = [];

    public $cropData = [];

    /**
     * @var null|string
     * Directory or alias where cropped images are saved.
     * If null (default), the directory will be '@webroot/<$illustrationDirectory>/<table name>', where table name is
     *  the table name of the owner model.
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

    /**
     * @var int
     * Maximum width or height of original, uncropped image, in pixels. If you encounter PHP memory problems,
     * you may lower this number.
     */
    public $treshold = 2000;

    /**
     * @var string
     * MIME type of saved, cropped image(s). If null, cropped images will be saved with the same MIME type
     * as the original.
     */
    public $mime = 'image/avif';    // PHP >= 8.1

    /**
     * @var int
     * Value used if cropSteps  is not set.
     */
    public $defaultSteps = 4;

    /**
     * @var string
     * Error message for images that are too small to crop. Parameters: original file name, width, and height.
     * If $allowTooSmall is false, this setting is not used.
     */
    public $tooSmallError = 'Image "%s" is too small (%d×%d).';

    /**
     * @var string
     * Error message for upload error.
     * @see https://www.php.net/manual/en/features.file-upload.errors.php
     */
    public $uploadError = 'Upload Error %d.';

    // Magic functions to handle 'subattributes'.
    // Attributes of the form '<a>_<b>' are delegated to $this->attributes[<a>]-><b>
    /**
     * @inheritdoc
     */
    public function __set($name, $value)    {
        $matches = null;
        if (preg_match('/(\\w+)_data/', $name, $matches))    {
            $attr = $matches[1];
            if (isset($this->attributes[$attr])) {
                $this->cropData[$attr] = $value;
            }
        }
        else parent::__set($name, $value);
    }

    public function canSetProperty($name, $checkVars = true): bool
    {
        $matches = null;
        if (preg_match('/(\\w+)_data/', $name, $matches))    {
            $attr = $matches[1];
            return isset($this->attributes[$attr]);
        }
        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function events()    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * @param $owner ActiveRecord
     * @throws \yii\base\InvalidConfigException
     */
    public function attach($owner)    {
        parent::attach($owner);

        $keys = array_keys($this->attributes);
        // add validation rules to model
        $vals = $owner->getValidators();
        $vals[] = new FileValidator(array_merge($this->fileValidation, [
            'attributes' => $keys,
            'extensions' => 'jpg, jpeg, bmp, gif, png, webp, avif',
            'skipOnEmpty' => true,
            'on' => $owner->scenario
        ]));
        $dataAttrs = array_map(fn($v): string => "{$v}_data", $keys);
        $vals[] = new SafeValidator([
            'attributes' => $dataAttrs,
            'on' => $owner->scenario
        ]);
    }

    /**
     * @inheritdoc
     * @param $event
     * @throws \yii\base\Exception
     */
    public function beforeSave($event)  {
        /** @var yii\base\Model $owner */
        $owner = $this->owner;
        foreach ($this->attributes as $attr => $cfg)    {
            $current = $owner->getOldAttribute($attr);

            $upload = UploadedFile::getInstance($owner, $attr); // no file, or deleted
            if (! $upload) {
                $this->deleteFiles($attr);
                continue;
            }

            $crop = Json::decode($this->cropData[$attr]);

            if ($upload->getHasError()) {  // something wrong
                $owner->addError($attr,
                    sprintf($this->uploadError, $upload->error));
                $event->isValid = false;
                return false;

            } else if ($upload->name == $current) { // unchanged
                $owner->setAttribute($attr, $current);  // reset attribute (it will be empty)

            } else {   // valid new upload
                $image = match ($upload->type) {
                    'image/bmp' => imagecreatefrombmp($upload->tempName),
                    'image/gif' => imagecreatefromgif($upload->tempName),
                    'image/jpeg' => imagecreatefromjpeg($upload->tempName),
                    'image/png' => imagecreatefrompng($upload->tempName),
                    'image/webp' => imagecreatefromwebp($upload->tempName),
                    'image/avif' => imagecreatefromavif($upload->tempName),   // PHP >= 8.1
                };

                $scale = 1.0;

                $sx = imagesx($image);
                $sy = imagesy($image);
                $greatest = max($sx, $sy);
                if ($greatest >= $this->treshold) {
                    $scale = $this->treshold / $greatest;
                    $nsx = (int)round($scale * $sx);
                    $nsy = (int)round($scale * $sy);
                    $newImage = imagecreatetruecolor($nsx, $nsy);
                    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $nsx, $nsy, $sx, $sy);
                    $image = $newImage;
                }

                $image = imagerotate($image, $crop['degrees'] ?? 0, 0);
                $image = imagecrop($image, [ 'x' => (int)round($scale * $crop['x']), 'y' => (int)round($scale * $crop['y']),
                    'width' => (int)round($scale * $crop['w']), 'height' => (int)round($scale * $crop['h']) ]);

                $dir = $this->getImgRootDir($attr);
                FileHelper::createDirectory($dir);  // ensure it exists

                $fileName = $this->randomName($attr, $upload);

                if (isset($cfg['cropWidth']))    {
                    $ww = $cfg['cropWidth'];
                    $hh = (int) round($ww / $crop['aspect']);

                    if ($cfg['rejectTooSmall'] ?? true) {
                        if (imagesx($image) < $ww || imagesy($image) < $hh) {
                            $owner->addError($attr,
                                sprintf($this->tooSmallError, $upload->name, $sx, $sy));
                            $event->isValid = false;
                            return false;
                        }
                    }

                    $steps = $cfg['cropSteps'] ?? $this->defaultSteps;
                    while ($steps > 0)  {
                        $subDir = $dir . DIRECTORY_SEPARATOR . $ww . 'w';
                        FileHelper::createDirectory($subDir);  // ensure it exists
                        $image = imageScale($image, $ww);
                        $this->saveImage($image, $subDir . DIRECTORY_SEPARATOR . $fileName, $upload);
                        $ww >>= 1;
                        $steps--;
                    }
                }
                else {
                    $this->saveImage($image, $dir . DIRECTORY_SEPARATOR . $fileName, $upload);
                }

                $this->deleteFiles($attr);  // delete old files, if any
                $owner->setAttribute($attr, $fileName);
            }
        }
        return true;
    }

    /**
     * @inheritdoc
     * @param $event
     */
    public function beforeDelete($event)  {
        foreach ($this->attributes as $attr => $cfg) {
            $this->deleteFiles($attr);
        }
        return true;
    }

    /**
     * @param $attribute
     * @return void
     * Delete file(s) belonging to attribute.
     */
    public function deleteFiles($attribute) {
        /** @var yii\db\ActiveRecordInterface $owner */
        $owner = $this->owner;
        $fileName = $owner->getOldAttribute($attribute);

        if (empty($fileName)) return;   // no files

        $cfg = $this->attributes[$attribute];
        $dir = $this->getImgRootDir($attribute);

        if (isset($cfg['cropWidth']))    {
            $steps = $cfg['cropSteps'] ?? $this->defaultSteps;
            $ww = $cfg['cropWidth'];
            while ($steps > 0)  {
                $subDir = $dir . DIRECTORY_SEPARATOR . $ww . 'w';
                $p = $subDir . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($p)) unlink($p);
                $ww >>= 1;
                $steps--;
            }
        } else {
            $path = $dir . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($path)) unlink($path);
        }

        $owner->setAttribute($attribute, '');
    }

    /**
     * @param $attribute
     * @param $options
     * @return string - HTML <img> with src and srcset, or $this->>noImage if not present.
     */
    public function getImgHtml($attribute, $options = [])    {
        $owner = $this->owner;
        $fileName = $owner->getAttribute($attribute);
        if (empty($fileName)) return $this->noImage;

        $cfg = $this->attributes[$attribute];

        if (isset($cfg['cropWidth'])) {
            $options['srcset'] = $this->getSrcSet($attribute);

            // if 'sizes' is absent, img is rendered way too wide (width of viewport)
            if (! isset($options['sizes'])) $options['sizes'] = $cfg['cropWidth'] . 'px';
        }

        return Html::img($this->getSrc($attribute), $options);
    }

    /**
     * @param $attribute
     * @return string - srcset; '' if CropWidth is not set
     */
    public function getSrcSet($attribute)   {
        $cfg = $this->attributes[$attribute];

        if (!isset($cfg['cropWidth'])) return '';

        $steps = $cfg['cropSteps'] ?? $this->defaultSteps;
        $ww = $cfg['cropWidth'];
        $i = 0;
        $srcset = [];
        while ($steps > 0)  {
            $srcset[] = $this->getSrc($attribute, $i) . " {$ww}w";
            $ww >>= 1;
            $steps--;
            $i++;
        }
        return implode(',', $srcset);
    }

    /**
     * @param $attribute
     * @param $step - size variant, 0 is greatest nsteps - 1 for smallest. Can be negative;
     *          -1 gives the smallest variant.
     * @return string|null URL of cropped image
     */
    public function getSrc($attribute, $step = 0)   {
        $owner = $this->owner;
        $fileName = $owner->getAttribute($attribute);
        if (empty($fileName)) return null;

        $cfg = $this->attributes[$attribute];

        $baseUrl = $this->getImgRootUrl($attribute);
        if (isset($cfg['cropWidth']))   {
            $steps = $cfg['cropSteps'] ?? $this->defaultSteps;
            if ($step < 0) $step += $steps;
            $step %= $steps;    // no insensible values
            $w = $cfg['cropWidth'] >> $step;
            $baseUrl .= "/{$w}w";
        }
        return "$baseUrl/$fileName";
    }

    protected function getImgRootDir($attribute)  {
        return Yii::getAlias($this->directory ?: $this->getResourceAlias('@webroot', $attribute));
    }

    protected function getImgRootUrl($attribute)  {
        return Yii::getAlias($this->baseUrl ?: $this->getResourceAlias('@web', $attribute));
    }

    protected function getResourceAlias($root, $attribute)   {
        $illDir = $this->illustrationDirectory;
        $baseName =  get_class($this->owner)::tableName();
        return "$root/$illDir/$baseName/$attribute";
    }

    protected function saveImage($image, $path, $uploadedFile) {
        $mime = $this->mime ?? $uploadedFile->type;
        match ($mime) {
            'image/bmp' => imagebmp($image, $path),
            'image/gif' => imagegif($image, $path),
            'image/jpeg' => imagejpeg($image, $path),
            'image/png' => imagepng($image, $path),
            'image/webp' => imagewebp($image, $path),
            'image/avif' => imageavif($image, $path),   // PHP >= 8.1
        };
    }

    /**
     * @return string
     * Create random file name. Override this if you need another name generation.
     * This function returns a random combination of six number characters and lower case letters,
     * allowing for 2 billion file names. Includes extension.
     */
    protected function randomName($attr, $uploadedFile) {
        /** @var yii\db\ActiveRecordInterface $owner */
        $owner = $this->owner;

        $mime = $this->mime ?? $uploadedFile->type;
        $ext = substr($mime, 6);
        if ($ext == 'jpeg') $ext = 'jpg';

        do {
            $r = base_convert(mt_rand(60466176 , mt_getrandmax()), 10, 36);  // 60466176 = 36^5; ensure six characters
            $r .= ".$ext";
        } while ($owner->findOne(['like', $attr, $r . '%', false]));  // ensure unique
        return $r;
    }
}
