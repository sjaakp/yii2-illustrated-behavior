Illustrated
===========

## Behavior for Yii2 ActiveRecord ##

### With associated upload-widget ###

**Illustrated** is a Behavior for the [Yii2 framework](http://www.yiiframework.com/) that makes any ActiveRecord, well, *illustrated*. It links the ActiveRecord to one (or possibly more) image files. The images have strict proportions, allowing for a clean layout of views and other pages. The uploaded images may have several resolutions.

The **Illustrated** behavior co-operates with the enclosed **Uploader** widget. This lets the user crop the image, before uploading it to the server.

[Here](http://sjaakpriester.nl/software/illustrated) are some demo's of the **Illustrated** behavior and its associated **Uploader** widget. 

## Installation ##

The preferred way to install **Illustrated** is through [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

	"sjaakp/yii2-illustrated-behavior": "*"

Or run:

	$ php composer.phar require sjaakp/yii2-illustrated-behavior "*"

You can manually install **Illustrated** by [downloading the source in ZIP-format](https://github.com/sjaakp/yii2-illustrated-behavior/archive/master.zip).

## Usage of Illustrated ##

**Illustrated** is used like any Behavior of an ActiveRecord. The code should look something like this:

    class <model> extends \yii\db\ActiveRecord { 
    	... 
    	public function behaviors(){
    		return [
    			[
    				"class" => "sjaakp\illustrated\Illustrated",
    				... 	// Illustrated options
    			],
     			...		// other behaviors
    		];
    	}
    	...
    }  
    
## Usage of Uploader ##

**Uploader** is an input widget. It can be used in an ActiveForm like this:

    use sjaakp\illustrated\Uploader;
     
    <?php $form = ActiveForm::begin([
    		'options' => ['enctype' => 'multipart/form-data']	// important, needed for file upload
    	]); ?>
    
    	...		// other form fields
    
    	<?= $form->field($model, 'file')->widget(Uploader::className(), [
    		   ...		// Uploader options
	    	]) ?>
    
    	...
    
    <?php ActiveForm::end(); ?>
    
Note that the ActiveForm must have the option `'enctype'` set to `'multipart/form-data'`.

## Illustrated function ##

#### getImgHtml() ####

**function getImgHtml( $size = 0, $forceSize = true, $options = [] )**

Gets a complete HTML `img` element of the uploaded and cropped illustration.

- `size`: the length of largest side in pixels. If the option `sizeSteps` > 0, `getImgHtml()` returns the smallest crop variant possible. If `size` = 0 (default) the biggest crop variant is returned.

- `forceSize`: if `true` (default), the element CSS is set to `size`.

- `options`: HTML options of the `img` tag. See [yii\helpers\Html::img](http://www.yiiframework.com/doc-2.0/yii-helpers-basehtml.html#img%28%29-detail). Default: `[]` (empty array).


## Illustrated options ##

#### fileAttribute ####

Name of the file input attribute in the ActiveRecord. Default: `'file'`.

#### imgAttribute ####

Name of the attribute in the ActiveRecord where the img src file name of the resulting cropped image is stored. Default: `'img'`.

#### sizeAttribute ####

If `null`: `cropSize` is strict, and the crop size is not stored in the ActiveRecord. 

If `string`: name of the attribute in the ActiveRecord where size is stored. Lower crop size than `cropSize` may be accepted. 

Default: `null`.

#### aspectRatio ####

If `float`: the fixed aspect ratio of the image; it is not saved in the database. 

If `string`: name of aspect attribute in the ActiveRecord. The variable aspect ratio is saved in the database.

Default: `1.0` (square).

#### cropSize ####

Size of the largest side of the cropped image, in pixels.
For portrait format (aspect ratio < 1.0) it is the height, for landscape format the width.
Default: `240`.

#### sizeSteps ####

Number of size variants of the cropped image. Each variant has half the size of the previous one.
Example: if `cropSize` is 1280, and `sizeSteps` is 5, variants will be saved with sizes 1280, 640, 320, 160, and 80 pixels, each in it's own subdirectory.

If `sizeSteps` is `0`, only one cropped image is saved, with size `cropSize`.

Default: `0` (no crop variants).

#### directory ####

Directory where cropped images are saved.
If `null`, the directory will be `'@webroot/<$illustrationDirectory>/<table name>'`, where `<table name>` is the table name of the ActiveRecord.
Default: `null`.

#### illustrationDirectory ####

Name of subdirectory under `'@webroot'` where cropped images are stored. Default: `'illustrations'`. If `directory` is anything else than null, `illustrationDirectory` is ignored

#### tooSmallMsg ####

Error message template for images that are too small to crop. Parameters: original file name, width, and height.

Default: `'Image "%s" is too small (%d×%d).'`.

#### fileValidation ####

Array with extra parameters for the validation of the file attribute. By default, only the file types and the number of files are tested.

You may add things like maximum file size here. See [yii\validators\FileValidator](http://www.yiiframework.com/doc-2.0/yii-validators-filevalidator.html).
Default: `[]` (empty array).

## Uploader options ##

#### aspectRatio ####

If `float`: the fixed aspect ratio of the image; it is not saved in the database.
Example: `aspectRatio` is `.75` for portrait format (3x4).

If `string`: name of aspect attribute in the model. The variable aspect ratio is stored in the database.

Default: `1.0` (square).

#### cropSize ####

Size of the (largest) cropped image in pixels. Default: `240`.

#### aspectOptions ####

If `false`: aspect ratio is fixed.

If `array`: list of available aspect ratios. Keys are aspect ratios multiplied by 1000, values are descriptions of aspect ratios. One of the array keys should correspond to `aspectRatio` (i.e.: be 1000 × `aspectRatio`).

Default: `false`.

Example of `array` value:

     $this->aspectOptions = [     // 1000 times real aspect ratio
               429 => 'tower (9×21)',      // aspect ratio is 0.429
               563 => 'high (9×16)',
               750 => 'portrait (3×4)',
               1000 => 'square (1×1)',      // aspect ratio is 1.0
               1333 => 'landscape (4×3)',
               1778 => 'wide (16×9)',
               2333 => 'cinema (21×9)'     // aspect ratio is 2.333
          ];

#### defaultAspect ####

Default selection of the aspect options. Note that there should be a corresponding key in the
`aspectOptions` array. F.i.: if `defaultAspect` is `0.75`, one of the keys in the array
should be 1000 × .75 = `750`.

If `aspectOptions` is `false`, `defaultAspect` is ignored.

Default: `1.0`.

#### radio ####

If `false`: the aspect ratio can be chosen from a drop down list.

If `true`: the aspect ratio can be chosen in a radio button list.

Only applies when `aspectOptions != false`.

Default: `false`.

#### stylefileOptions ####

Extra client options for the stylefile file input control.
See: [https://github.com/sjaakp/stylefile](https://github.com/sjaakp/stylefile).

#### cropperOptions ####

Extra client options for the cropper control.
See: [https://github.com/sjaakp/cropper](https://github.com/sjaakp/cropper).

## FAQ ##

#### I'm getting an error like 'Trying to get property of non-object' ####

You probably didn't give the form the option `'enctype' => 'multipart/form-data'`.

#### The image is distorted after upload. ####

Maybe the owner model is used under a scenario. This scenario should declare the crop attributes
(`'<file>_x'`, `'<file>_y'`, `'<file>_w'`, `'<file>_h'`) in the attribute list.

#### I'm getting an error message: one of the crop dimensions appears to be 0. aspectRatio is a string. ####

The owner model may be used under a scenario. This scenario should declare the aspect ratio attribute (`'aspect'`) in the attribute list.
