Illustrated
===========

## Behavior for Yii2 ActiveRecord ##

### With associated upload-widget ###

**Illustrated** is a Behavior for the [Yii2 framework](http://www.yiiframework.com/) that makes any [ActiveRecord](http://www.yiiframework.com/doc-2.0/yii-db-activerecord.html "Yii API"), well, *illustrated*. It links the ActiveRecord to one or more image files. The images have strict proportions, allowing for a clean layout of views and other pages. The uploaded images may have several resolutions.

The **Illustrated** behavior co-operates with the enclosed **Uploader** widget. This lets the user crop the image, before uploading it to the server.

[Here](http://sjaakpriester.nl/software/illustrated) are some demo's of the **Illustrated** behavior and its associated **Uploader** widget. 

**Note that the current version (1.1.0) is changed considerably from the previous versions.** It should be far easier to use. Please consult this document carefully.

----------

## Installation ##

Install **Illustrated** with [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

	"sjaakp/yii2-illustrated-behavior": "*"

Or run:

	composer require sjaakp/yii2-illustrated-behavior "*"

You can manually install **Illustrated** by [downloading the source in ZIP-format](https://github.com/sjaakp/yii2-illustrated-behavior/archive/master.zip).

----------

## Usage of Illustrated ##

**Illustrated** is used like any [Behavior](http://www.yiiframework.com/doc-2.0/yii-base-behavior.html "Yii API") of an ActiveRecord. The code should look something like this:

	<?php

	use sjaakp\illustrated\Illustrated;
    
	class <model> extends \yii\db\ActiveRecord { 
    	... 
    	public function behaviors(){
    		return [
    			[
    				"class" => Illustrated::className(),
					"attributes" => [
						"picture" => [	// attribute name of the illustration
							...		// options for 'picture'
						],
						...		// other illustrations
					],
    				... 	// other Illustrated options
    			],
     			...		// other behaviors
    		];
    	}
    	...
    }  

An ActiveRecord with **Illustrated** behavior can have one or more illustrations.

Each illustration has it's own, fixed proportions, so that views of the ActiveRecord will have a consistent layout.

Each illustration is associated with one attribute in the ActiveRecord and a corresponding field in the database table. This attribute stores the filename of the uploaded image. **Illustrated** uses its own naming scheme. An uploaded image file name is never longer than ten characters.

In its basic operation, **Illustrated** stores one version of the uploaded file for each illustration. However, it is possible to make it store several versions, with different resolutions.

The proportions of an illustration are defined by two options: `cropSize` and `aspectRatio`. As an option, `aspectRatio` can be selected from a list of options. In that case, `aspectOptions` is saved in an extra ActiveRecord attribute.

----------

## Usage of Uploader ##

**Uploader** is an [input widget](http://www.yiiframework.com/doc-2.0/yii-widgets-inputwidget.html "Yii API"). It is intended to upload an illustration. It can be used in an [ActiveForm](http://www.yiiframework.com/doc-2.0/yii-widgets-activeform.html "Yii API") like this:

    use sjaakp\illustrated\Uploader;
     
    <?php $form = ActiveForm::begin([
    		'options' => ['enctype' => 'multipart/form-data']	// important, needed for file upload
    	]); ?>
    
    	...		// other form fields
    
    	<?= $form->field($model, 'picture')->widget(Uploader::className(), [
    		   ...		// Uploader options
	    	]) ?>
    
    	...
    
    <?php ActiveForm::end(); ?>
    
**Uploader** displays a control for file upload, a control to crop the image, a checkbox to delete an uploaded image, and optionally a list of aspect ratios to choose from.

**Note** that the ActiveForm must have the option `'enctype'` set to `'multipart/form-data'`.

----------

## Illustrated functions ##

These functions become methods of the ActiveRecord that owns the **Illustrated** Behavior.

#### getImgHtml() ####

**function getImgHtml( $attribute, $size = 0, $forceSize = true, $options = [] )**

Gets a complete HTML `img` element of the uploaded and cropped illustration.

- `attribute`: the attribute name of the illustration.
- `size`: the length of largest side in pixels. If the option `sizeSteps` > 0, `getImgHtml()` returns the smallest crop variant possible. If `size` = 0 (default) the biggest crop variant is returned.
- `forceSize`: if `true` (default), the element CSS is set to `size`.
- `options`: HTML options of the `img` tag. See [yii\helpers\Html::img](http://www.yiiframework.com/doc-2.0/yii-helpers-basehtml.html#img%28%29-detail). Default: `[]` (empty array).

The easiest way to display the illustration stored in the attribute `'picture'` in a view is:

	...	
	<?= $model->getImgHtml('picture') ?>
	...

#### getImgSrc() ####

**function getImgHtml( $attribute, $size = 0 )**

Gets the source URL of the uploaded and cropped illustration. Returns `false` if not set.

- `attribute`: the attribute name of the illustration.
- `size`: the length of largest side in pixels. If the option `sizeSteps` > 0, `getImgHtml()` returns the smallest crop variant possible. If `size` = 0 (default) the biggest crop variant is returned.

#### deleteFiles() ####

**function deleteFiles( $attribute )**

Deletes the image file(s) and clears `attribute`.

- `attribute`: the attribute name of the illustration.

----------


## Illustrated options ##

#### attributes ####

`array` List of illustration properties `key => value`.

Array member `key` is the name of the attribute that stores the file name of the resulting cropped image.

`Value` is an array with the following properties (all are optional):

- **cropSize**:  `int` - Size of the largest side of the cropped image, in pixels. For portrait format (aspect ratio < 1.0) it is the height, for landscape format the width. 
	- If not set, the default is taken. Default: `240`.
- **aspectRatio**: `float|string` 
	- If `float`: the fixed aspect ratio of the image; it is not saved in the database. 
	- If `string`: name of aspect attribute in the model. The aspect ratio is saved in this database field. 
	- If not set, the default is taken Default: `1.0`.
- **sizeSteps**: `int` - Number of size variants of the cropped image. Each variant has half the size of the previous one. 
	- Example: if `cropSize = 1280`, and `sizeSteps = 5`, variants will be saved with sizes 1280, 640, 320, 160, and 80 pixels, each in it's own subdirectory. 
	- If `sizeSteps = 0` (default) or is not set, only one cropped image is saved, with size `cropSize`.
- **allowTooSmall**: `bool` - If `true`, images which are too small to crop are accepted. They will be sized to fit in the target size, defined by `cropSize` and `aspectRatio`.
- **tooSmallMsg**: `string` - Error message template for images that are too small to crop. Parameters: original file name, width, and height. Default: `'Image "%s" is too small (%d×%d).'`.

`Value` can also be a string. Then it is the name of the attribute. The properties all revert to default.

#### directory ####

Directory (or alias) where cropped images are saved.
	
If `null` (default), the directory will be `'@webroot/<$illustrationDirectory>/<table name>'`, where `<table name>` is the table name of the ActiveRecord.

#### baseUrl ####

URL (or alias) where cropped images reside.

If `null` (default), the URL will be `'@web/<$illustrationDirectory>/<table name>'`, where `<table name>` is the table name of the ActiveRecord.

#### illustrationDirectory ####

Name of subdirectory under `'@webroot'` where cropped images are stored. Default: `'illustrations'`. If `directory` is anything else than `null`, `illustrationDirectory` is ignored

#### noImage ####

HTML returned if no image is available, i.e. 
`$imgAttribute` is empty. Default: `''` (empty text).

#### fileValidation ####

Array with extra parameters for the validation of the file attribute. By default, only the file types and the number of files are tested.

You may add things like maximum file size here. See [FileValidator](http://www.yiiframework.com/doc-2.0/yii-validators-filevalidator.html "Yii API").
Default: `[]` (empty array).

----------

## Uploader options ##

#### aspectOptions ####

- If `false` (default): aspect ratio is fixed, and given by `aspectRatio` in the `attributes` option of **Illustrated**.

- If `array`: list of available aspect ratios. Keys are aspect ratios multiplied by 1000, values are descriptions of aspect ratios.

Example of `aspectOptions` value:

     $uploader->aspectOptions = [     // 1000 times real aspect ratio
               429 => 'tower (9×21)',      // aspect ratio is 0.429
               563 => 'high (9×16)',
               750 => 'portrait (3×4)',
               1000 => 'square (1×1)',      // aspect ratio is 1.0
               1333 => 'landscape (4×3)',
               1778 => 'wide (16×9)',
               2333 => 'cinema (21×9)'     // aspect ratio is 2.333
          ];

#### defaultAspect ####

Default selection of the aspect options. **Note** that there should be a corresponding key in the
`aspectOptions` array. Example: if `defaultAspect` is `0.75`, one of the keys in the array
should be 1000 × .75 = `750`.

If `aspectOptions` is `false`, `defaultAspect` is ignored.

Default: `1.0`.

#### radio ####

- If `false`: the aspect ratio can be chosen from a drop down list.

- If `true`: the aspect ratio can be chosen in a radio button list.

Only applies when `aspectOptions != false`.

Default: `false`.

#### stylefileOptions ####

Extra client options for the stylefile file input control.
See: [https://github.com/sjaakp/stylefile](https://github.com/sjaakp/stylefile).

#### cropperOptions ####

Extra client options for the cropper control.
See: [https://github.com/sjaakp/cropper](https://github.com/sjaakp/cropper).

#### deleteOptions ####

Options for delete checkbox. See [activeCheckbox](http://www.yiiframework.com/doc-2.0/yii-helpers-basehtml.html#activeCheckbox()-detail "Yii API")

- If false, no delete chekbox is rendered.

Default: `[ 'label' => 'Delete image' ]`.

----------

**Note:** in many cases, if the illustration has a fixed aspect ratio, it won't be necessary to set any option for 
**Uploader**, and it can be rendered simply with:

    	<?= $form->field($model, 'picture')->widget(Uploader::className()) ?>



### FAQ ###

**I'm getting an error like 'Trying to get property of non-object'**

- You probably didn't give the form the option `'enctype' => 'multipart/form-data'`.
