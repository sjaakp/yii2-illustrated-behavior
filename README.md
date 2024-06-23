Illustrated 2.0
===============

## Behavior for Yii2 ActiveRecord ##

### With associated Cropper-widget ###

[![Latest Stable Version](https://poser.pugx.org/sjaakp/yii2-illustrated-behavior/v/stable)](https://packagist.org/packages/sjaakp/yii2-illustrated-behavior)
[![Total Downloads](https://poser.pugx.org/sjaakp/yii2-illustrated-behavior/downloads)](https://packagist.org/packages/sjaakp/yii2-illustrated-behavior)
[![License](https://poser.pugx.org/sjaakp/yii2-illustrated-behavior/license)](https://packagist.org/packages/sjaakp/yii2-illustrated-behavior)

**Illustrated 2.0** is a Behavior for the [Yii2 framework](http://www.yiiframework.com/) that 
makes any [ActiveRecord](http://www.yiiframework.com/doc-2.0/yii-db-activerecord.html "Yii API"), well, *illustrated*. 
It links the ActiveRecord to one or more image files. The images 
have strict proportions, allowing for a clean layout of views 
and other pages. The images may be saved in several resolutions.

The **Illustrated 2.0** behavior co-operates with the 
enclosed **CropperWidget** widget. This lets the user crop the image,
before uploading it to the server.

[Here](http://sjaakpriester.nl/software/illustrated) is a demo of the **Illustrated 2.0** behavior and its 
associated **CropperWidget**. 

**Note that the current version (2.0.0) is changed considerably 
from previous versions.** It should be far easier to use. Please 
consult this document carefully.

----------

## Installation ##

Install **Illustrated 2.0** with [Composer](https://getcomposer.org/). Either add the 
following to the require section of your `composer.json` file:

	"sjaakp/yii2-illustrated-behavior": "*"

Or run:

	composer require sjaakp/yii2-illustrated-behavior "*"

You can manually install **Illustrated 2.0** 
by [downloading the source in ZIP-format](https://github.com/sjaakp/yii2-illustrated-behavior/archive/master.zip).

**Note** that **Illustrated 2.0** needs PHP version 8.1 or later.

----------

## Usage of Illustrated ##

**Illustrated 2.0** is used like any [Behavior](http://www.yiiframework.com/doc-2.0/yii-base-behavior.html "Yii API") of an 
ActiveRecord. The code should look something like this:

	<?php

	use sjaakp\illustrated\Illustrated;
    
	class <model> extends \yii\db\ActiveRecord { 
    	... 
    	public function behaviors(){
    		return [
    			[
    				"class" => Illustrated::class,
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

An ActiveRecord with **Illustrated 2.0** behavior can have one or more
illustrations.

Each illustration has it's own, fixed proportions, so that views of 
the ActiveRecord will have a consistent layout.

Each illustration is associated with one attribute in the ActiveRecord 
and a corresponding field in the database table. This attribute stores 
the filename of the uploaded image. **Illustrated 2.0** uses its own 
naming scheme. An uploaded image file name is never longer than eleven 
characters.

In its basic operation, **Illustrated 2.0** stores one version of the 
uploaded file for each illustration. However, it is possible to make 
it store several versions, with different resolutions. This is perfect
for [responsive images](https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images)
More information [here](https://css-tricks.com/a-guide-to-the-responsive-images-syntax-in-html/).

----------

## Usage of CropperWidget ##

**CropperWidget** is an [input widget](http://www.yiiframework.com/doc-2.0/yii-widgets-inputwidget.html "Yii API"). It is intended 
to upload an illustration. It can be used in an [ActiveForm](http://www.yiiframework.com/doc-2.0/yii-widgets-activeform.html "Yii API") 
like this:

    use sjaakp\illustrated\CropperWidget;
     
    <?php $form = ActiveForm::begin([
    		'options' => ['enctype' => 'multipart/form-data']	// important, needed for file upload
    	]); ?>
    
    	...		// other form fields
    
    	<?= $form->field($model, 'picture')->widget(CropperWidget::class, [
    		   ...		// CropperWidget options
	    	]) ?>
    	...
		<?= Html::submitButton('Submit') ?>
    
    <?php ActiveForm::end(); ?>
    
**CropperWidget** displays a control for file upload combined with controls to 
crop and rotate the image. It is based on 
my [**Cropper 2.0**](https://sjaakpriester.nl/software/cropper) JavaScript 
widget.

**Note** that the ActiveForm must have the option `'enctype'` set 
to `'multipart/form-data'`.

By far the most important option of **CropperWidget** is **aspect**. This 
sets the aspect ratio of the cropped immage.

It can be set to one one of the following strings:

- **'tower'** equivalent to 0.429, 9:21
- **'high'** equivalent to 0.563, 9:16
- **'phi_portrait'** equivalent to 0.618, 1:φ, [golden ratio](https://en.wikipedia.org/wiki/Golden_ratio)
- **'din_portrait'** equivalent to 0.707, 1:√2, [DIN/ISO 216 paper sizes](https://en.wikipedia.org/wiki/ISO_216)
- **'portrait'** equivalent to 0.75, 3:4
- **'square'** equivalent to 1.0, 1:1
- **'landscape'** equivalent to 1.333, 4:3
- **'din_landscape'** equivalent to 1.414, √2:1
- **'phi_landscape'** equivalent to 1.618, φ:1
- **'wide'** equivalent to 1.718, 16:9
- **'cinema'** equivalent to 2.333, 21:9

Alternatively, you can set **aspect** to a float between 0.2 and 5.0.

**CropperWidget** has some other options too. Please refer tot the documentation
for [**Cropper 2.0**](https://sjaakpriester.nl/software/cropper)

### Example ###

To set the aspect ratio to `'portrait'` (0.75) one would use:

    use sjaakp\illustrated\CropperWidget;
     
    <?php $form = ActiveForm::begin([
    		'options' => ['enctype' => 'multipart/form-data']
    	]); ?>
    
    	...   
    	<?= $form->field($model, 'picture')->widget(CropperWidget::class, [
    		'aspect' => 'portrait'
	    	]) ?>
    
    	...
    
    <?php ActiveForm::end(); ?>

----------

## Illustrated 2.0 functions ##

These functions become methods of the ActiveRecord that owns 
the **Illustrated 2.0** Behavior.

#### getImgHtml() ####

**function getImgHtml( $attribute, $options = [] )**

Gets a complete HTML `<img>` element of the uploaded and cropped 
illustration. If **cropWidth** is set and **cropSteps** is greater 
than zero, a `srcset` is included.

**Note** that for `srcset` to be effective, you have to set the 
**sizes** value in **$options**. For details,
[see here](https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images "MDN").

- `attribute`: the attribute name of the illustration.
- `options`: HTML options of the `img` tag. See [yii\helpers\Html::img](http://www.yiiframework.com/doc-2.0/yii-helpers-basehtml.html#img%28%29-detail). 
Default: `[]` (empty array).

The easiest way to display the illustration stored in the attribute `'picture'` in a view is:

	...	
	<?= $model->getImgHtml('picture') ?>
	...

#### getSrc() ####

**function getSrc( $attribute, $step = 0 )**

Gets the source URL of (one of the variants of) the uploaded and 
cropped illustration. Returns `null` if not available.

- `attribute`: the attribute name of the illustration.
- `step`: the 'step', or variant, of the illustration, 0 being the greatest.
To get the smallest variant (useful for thumbnails), set `step` to -1.

#### getSrcSet() ####

**function getSrcSet( $attribute )**

Gets the `srcset` of the uploaded and cropped illustration. 
Returns `""` if `cropWidth` is not set.

- `attribute`: the attribute name of the illustration.

#### deleteFiles() ####

**function deleteFiles( $attribute )**

Deletes the image file(s) and clears `attribute`.

- `attribute`: the attribute name of the illustration.

----------


## Illustrated 2.0 options ##

#### attributes ####

`array` List of illustration properties `key => value`.

Array member `key` is the name of the attribute that stores the file name of the resulting cropped image.

`value` is an array with the following properties (all are optional):

- **cropWidth**:  `int` - Width of the cropped image, in pixels. 
	- If not set, the cropped image is saved with the maximum
  possible resolution and filesize.
- **cropSteps**: `int` - Number of size variants of the cropped image. 
Each variant has half the width of the previous one. 
	- Example: if `cropWidth = 1280`, and `cropSteps = 5`, variants 
  will be saved with widths 1280, 640, 320, 160, and 80 pixels, each 
  in it's own subdirectory. 
	- If `cropSteps = 0` or is not set, only one cropped image is saved,
  with width `cropWidth`.
    - If `cropWidth` is not set, `cropSteps` is ignored.
- **rejectTooSmall**: `bool` - If `true`, images which are too small 
to crop are rejected.
  	If not set, `true` is assumed; small images will be rejected.

#### mime ####

MIME type of saved, cropped image(s). If null, cropped images will be
saved with the same MIME type as the original. Default: `'image/avif'`.
**AVIF** is a [recent (2019) image file format](https://en.wikipedia.org/wiki/AVIF "Wikipedia")
which is in many ways superior to JPEG. It is supported by all modern browsers.

#### directory ####

Directory (or alias) where cropped images are saved.
	
If `null` (default), the directory will be `'@webroot/<$illustrationDirectory>/<table name>'`,
where `<table name>` is the table name of the ActiveRecord.

#### baseUrl ####

URL (or alias) where cropped images reside.

If `null` (default), the URL will be `'@web/<$illustrationDirectory>/<table name>'`, 
where `<table name>` is the table name of the ActiveRecord.

#### illustrationDirectory ####

Name of subdirectory under `'@webroot'` where cropped images are stored. 
Default: `'illustrations'`. If `directory` is anything else than `null`,
`illustrationDirectory` is ignored

#### noImage ####

HTML returned if no image is available, i.e. 
`$imgAttribute` is empty. Default: `''` (empty text).

#### fileValidation ####

Array with extra parameters for the validation of the file attribute. 
By default, only the file types and the number of files are tested.

You may add things like maximum file size here. 
See [FileValidator](http://www.yiiframework.com/doc-2.0/yii-validators-filevalidator.html "Yii API").
Default: `[]` (empty array).

#### tooSmallError ####

Error message template for images that are too small to crop. 
Parameters: original file name, width, and height. 
Default: `'Image "%s" is too small (%d×%d).'`.

#### uploadError ####

Error message template for upload errors.
Parameter: error number. [ See here.](https://www.php.net/manual/en/features.file-upload.errors.php "PHP")
Default: `'Upload Error %d.'`.

----------

## CropperWidget options ##

#### options ####

Client options such as `aspect` for the **Cropper 2.0** control.
See [Cropper documentation](https://github.com/sjaakp/cropper).

#### translations ####

Optional translations for the **Cropper 2.0** control.
See [Cropper documentation](https://github.com/sjaakp/cropper).

### FAQ ###

**Why am I getting an error like 'Trying to get property of non-object'?**

- You probably didn't give the form the option `'enctype' => 'multipart/form-data'`.

**Why can't I change the cropping of an illustration if I try to
update an illustrated document?**

- This is by design. 'Recropping' an already cropped image is discouraged.
You might reload the original image.