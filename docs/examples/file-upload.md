---
title: 'Example: file upload'
description: 'Runnable Nette API example that uploads one file and many files with FileInputParam, checks the size and the real file type, and stores the file under a random name.'
---

# Example: file upload

`POST /api/v1/avatar` takes one image and a user name. `POST /api/v1/attachments` takes many files in one field.

<ProjectSource name="file-upload" :files="['config/common.neon', 'app/Api/AvatarUploadHandler.php', 'app/Api/AttachmentsUploadHandler.php', 'e2e.sh']" />

<Diagram name="examples-file-upload-1" alt="A multipart request goes to FileInputParam; the handler checks the upload error, the size and the type, then stores the file; each failed check has its own status code" />

| Upload | Response |
| --- | --- |
| a PNG under 100 kB | `201` with the stored name |
| no file | `400 Field is required` |
| over 100 kB | `413` |
| a text file sent as `image/png` | `415 text/plain is not allowed` |
| three files in `files[]` | `200` with three entries |

## The handler for one file

`$params['avatar']` is the `$_FILES` entry: `name`, `type`, `tmp_name`, `error` and `size`.

<<< @/../examples/file-upload/app/Api/AvatarUploadHandler.php

## Many files

`setMulti()` makes the param a list. The handler receives one entry per file.

<<< @/../examples/file-upload/app/Api/AttachmentsUploadHandler.php

## The services

<<< @/../examples/file-upload/config/common.neon{yaml}

## The test

<<< @/../examples/file-upload/e2e.sh{bash}

## FAQ

### Why not trust the type field?

The client sends it. `finfo` looks at the bytes of the file instead.

### Why a random file name?

The client's name can contain `../` or overwrite another file. Keep the original name in your database if you need it.

### Where is the size limit?

In the handler, and in PHP: `upload_max_filesize` and `post_max_size` must be at least as large, otherwise PHP drops the file before the handler sees it.

### Does it work with PUT?

No, PHP fills `$_FILES` only for `POST` multipart requests. For `PUT`, send the file as the raw body, see [JSON and raw body](/examples/json-body).

### How does the console send files?

It shows a file input for each `FileInputParam`. See [Web console](/guide/console).
