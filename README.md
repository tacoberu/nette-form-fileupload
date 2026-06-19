Nette form FileControl
======================

Uploading files is easy. But once a file is already stored in the system, it is sometimes needed to

- only display it (preferably with a preview),
- or delete the file,
- or replace it with another version.

There are several issues with the standard file input:

1. It is inconvenient that the original file cannot be shown in any reasonable way — unlike other inputs, where the original value can be displayed.
2. Related to that is how to delete an existing file.
3. It is inconvenient when an unrelated error occurs elsewhere in the form. The form is then returned so the user can fix it — but the uploaded file (or files) has to be selected again.
4. When uploading a file with a form, working with files is *different* from the other fields.
5. Uploading large files is a chapter of its own.

**FileControl** tries to solve this with several techniques:

1. An existing file is represented by a value of the `FileCurrent` class. If the user deletes it, the form holds a `FileCurrent` value with the `remove` property set to `True`.
2. All uploaded files are kept in a transaction. Once uploaded, a file is stored in a special storage (by default handled as a directory in temp, which can be changed) and committed to the system after the form is saved successfully.

Image files can be represented as images. If the standard renderer is not suitable, a custom one can be set.

The input value can take one of three forms:

- `Null`: none, or the original file was deleted
- `FileUploaded`: a newly uploaded file
- `FileCurrent`: the original file stored in the system


## Installation
```
composer require tacoberu/nette-form-fileupload
```


### Usage
The extension has to be registered and configured in `config.neon`:
```neon
extensions:
	filecontrol: Taco\Nette\Forms\Controls\FileControlExtension


filecontrol:
	store: Taco\Nette\Forms\Controls\UploadStoreTemp('uploading/txt-', baseDir: %tempDir%)

```

It can then be used in a form:
```php
use Taco\Nette\Forms\Controls\FileCurrent;
use Taco\Nette\Forms\Controls\FileControl;
use Taco\Nette\Forms\Controls\GenericFilePreviewer;

$form = new Nette\Forms\Form;

$form->addFileControl('portrait', 'Portrait');
$form->addMultiFileControl('attachments1', 'Attachments 1');
```

A form with a single file (`FileControl`) — an uploaded file has a delete button:

![Form with FileControl](docs/file.png)

A form with multiple files (`MultiFileControl`) — with image previews, deletion of individual items (✕) and the ↻ button for preloading without submitting the whole form:

![Form with MultiFileControl](docs/files.png)



## What FileControl and MultiFileControl support

### AJAX upload with chunked transfer

When either control is embedded inside a Nette `Presenter`, files are uploaded immediately after the user selects them — without waiting for the form to be submitted.

Large files are **automatically split into chunks** so that each individual POST stays within PHP's `upload_max_filesize` limit. The chunks are reassembled on the server inside the transaction directory. The client shows a `<progress>` bar during the transfer.

Small files (below `upload_max_filesize − 100 KB`) are sent as a single POST.

After a successful upload the server returns a rendered preview (thumbnail or filename label) that is inserted into the page immediately, without a full page reload.

The no-JS fallback (↻ preload button) still works for environments without JavaScript.

### Validation

Both controls override Nette's built-in file validators so they work with `FileCurrent` and `FileUploaded` values (the Nette originals only accept `FileUpload`):

```php
$form->addFileControl('portrait', 'Portrait')
    ->addRule($form::MaxFileSize, 'File is too large (max %d B).', 512 * 1024)
    ->addRule($form::MimeType, 'Only images are allowed.', ['image/jpeg', 'image/png'])
    ->addRule($form::Image, 'File must be an image.');
```

| Rule | Description |
|---|---|
| `Form::MaxFileSize` | Maximum file size in bytes. |
| `Form::MimeType` | Allowed MIME types, e.g. `'image/jpeg'` or an array of types. |
| `Form::Image` | Shorthand for supported image formats (`image/jpeg`, `image/png`, `image/gif`, `image/webp`). |
| `Form::Required` / `setRequired()` | Standard Nette required field — a file must be selected or already exist as `FileCurrent`. |

Conditional validation via `addConditionOn()` works the same as for other Nette controls.

### Upload errors

When PHP rejects a file (server-level error), the control adds an error message to itself:

- **A single file exceeds `upload_max_filesize`** — PHP marks the file with `UPLOAD_ERR_INI_SIZE`; the control displays a message with the limit value.
- **The combined upload exceeds `post_max_size`** — PHP silently discards the entire POST body. The control detects this from `Content-Length` and adds a form-level error before submit detection.

### Values

`FileControl::getValue()` returns:

| Type | Situation |
|---|---|
| `FileCurrent` | An existing file from a previous save (or a default value). |
| `FileUploaded` | A newly uploaded file (stored in the transaction) that needs to be committed to the system. |
| `null` | No file, or the file was deleted — it should be removed from the system. |

`MultiFileControl::getValue()` returns an array (possibly empty) whose elements are `FileCurrent` or `FileUploaded`.

---

## API

### setPreviewer()

A previewer can be set on the FileControl to control how file previews are formatted. With `GenericFilePreviewer`, a preview of an image file is available.

### getRemoveButtonPrototype()

Allows customizing the look of the delete button: label, classes, title.


### getCurrentControlPrototype()

Allows customizing how the input looks when it has a selected value.


### getPreviewControlPart()

Allows customizing the file preview without using a previewer.


### Transactions

When a file is successfully uploaded to the server, it is automatically moved to the storage — a transaction. This serves the purpose that if the form is not processed but is, for example, returned to the user because of validation, the file does not have to be uploaded again. After successful processing, the file is available via `$control->getValue()` like the other values.

If the form is discarded — for example the user closes the page or clicks cancel — the uploaded files stay in the transaction and get in no one's way.

Once a file has been uploaded into the system, the transaction can be discarded — that is, with the default `UploadStoreTemp` storage, the directory is deleted. This can be done either explicitly:

```php
$form['portrait']->destroyStore();
```

Or it can be left to the GC, which deletes it automatically after a certain time.

#### UploadStoreTemp, GC

Automatic cleanup is implemented in `UploadStoreTemp`. It works so that after the page finishes, the destructor goes through all the relevant transactions and checks whether a transaction is older than `UploadStoreTemp::$gcAgeLimit`.
To spread the load, only `UploadStoreTemp::$gcMaxCount` transactions/directories are deleted at a time.

This behavior is solely a matter of the `UploadStoreTemp` implementation.


## Assets (TypeScript)

The client-side scripts are written in TypeScript and compiled to `assets/filecontrol.js` (symlinked into `examples/document_root/js/`):

	npm run build:assets


## E2E tests (Playwright)

	npm install
	npx playwright install chromium  # first time only

	npm run test:e2e        # run the tests
	npm run test:e2e:ui     # interactive UI

Outputs (report, results) are saved to `temp/`.

The URL of the tested application is configured in `.env` via `APP_URL`.
