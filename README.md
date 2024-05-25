Nette form FileControl
======================

Uploading files is easy. But if we have the file already uploaded in the system, we can want

- only display it (preferably with a preview),
- or delete the file,
- or replace the file with another version.

A somewhat inconvenient situation is when we get some unrelated error elsewhere in the form.
So we return the form for the user to correct it. But the uploaded file or files must be restarted.
We solve this with a transaction. Once uploaded, the file is stored in a special storage (by default it is handled as a tempo directory, it can be changed),
and entered into the system after successfully saving the form.

We represent image files as images. If the standard renderer is not suitable, we can set our own.

The input value can have three options:

- `Null`: none, or original file deleted
- `FileUploaded`: new file uploaded
- `FileCurrent`: the original file stored in the system


## Installation
```
composer require tacoberu/nette-form-fileupload
```


### Use

```php
use Taco\Nette\Forms\Controls\FileCurrent;
use Taco\Nette\Forms\Controls\FileControl;
use Taco\Nette\Forms\Controls\GenericFilePreviewer;

$form = new Nette\Forms\Form;

$form['portrait'] = (new FileControl('Portrait:'))
	->setDefaultValue(new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg"))
	->setPreviewer(new GenericFilePreviewer());


```
