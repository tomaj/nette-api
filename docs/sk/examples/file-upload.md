---
title: 'Príklad: nahrávanie súborov'
description: 'Spustiteľný príklad Nette API, ktorý nahrá jeden aj viac súborov cez FileInputParam, overí veľkosť a skutočný typ súboru a uloží ho pod náhodným menom.'
---

# Príklad: nahrávanie súborov

`POST /api/v1/avatar` prijme jeden obrázok a meno používateľa. `POST /api/v1/attachments` prijme viac súborov v jednom poli.

<ProjectSource name="file-upload" :files="['config/common.neon', 'app/Api/AvatarUploadHandler.php', 'app/Api/AttachmentsUploadHandler.php', 'e2e.sh']" />

<Diagram name="examples-file-upload-1" alt="Multipart požiadavka ide do FileInputParam; handler overí chybu nahrávania, veľkosť a typ a potom súbor uloží; každá neúspešná kontrola má vlastný stavový kód" />

| Nahrávanie | Odpoveď |
| --- | --- |
| PNG do 100 kB | `201` s uloženým menom |
| bez súboru | `400 Field is required` |
| nad 100 kB | `413` |
| textový súbor poslaný ako `image/png` | `415 text/plain is not allowed` |
| tri súbory vo `files[]` | `200` s tromi položkami |

## Handler pre jeden súbor

`$params['avatar']` je položka z `$_FILES`: `name`, `type`, `tmp_name`, `error` a `size`.

<<< @/../examples/file-upload/app/Api/AvatarUploadHandler.php

## Viac súborov

`setMulti()` urobí z parametra zoznam. Handler dostane jednu položku na súbor.

<<< @/../examples/file-upload/app/Api/AttachmentsUploadHandler.php

## Služby

<<< @/../examples/file-upload/config/common.neon{yaml}

## Test

<<< @/../examples/file-upload/e2e.sh{bash}

## Časté otázky

### Prečo neveriť poľu type?

Posiela ho klient. `finfo` sa namiesto toho pozrie na bajty súboru.

### Prečo náhodné meno súboru?

Meno od klienta môže obsahovať `../` alebo prepísať iný súbor. Ak pôvodné meno potrebujete, uložte ho do databázy.

### Kde je limit veľkosti?

V handleri a v PHP: `upload_max_filesize` a `post_max_size` musia byť aspoň také veľké, inak PHP súbor zahodí skôr, než ho handler uvidí.

### Funguje to s PUT?

Nie, PHP plní `$_FILES` iba pre multipart požiadavky `POST`. Pre `PUT` pošlite súbor ako surové telo, pozrite si [JSON a surové telo](/sk/examples/json-body).

### Ako posiela súbory konzola?

Pre každý `FileInputParam` ukáže pole na výber súboru. Pozrite si [Webová konzola](/sk/guide/console).
