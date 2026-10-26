---
title: Versions of this documentation
description: Which Nette API release this documentation describes, how “Since 3.6.0” features are marked, and where docs for 4.x and the older 2.x line are published.
---

# Versions of this documentation

This site describes **3.x**, the current release line as of the upcoming 3.6.0, which is not released yet. The latest release is 3.5.0; features marked "Since 3.6.0" are not in it. Until the release, install them with `composer require tomaj/nette-api:dev-master`. It needs PHP 8.3 or newer.

<Diagram name="versioning-1" alt="Today 3.x is at the root; when 4.x ships it takes the root and 3.x moves to /3.x/" />

| Line | Where |
| --- | --- |
| 3.x | this site, at the root |
| 4.x | will take the root when it ships; 3.x moves to `/3.x/` |
| 2.x and older | the [README of 2.13.0](https://github.com/tomaj/nette-api/tree/2.13.0) on GitHub |

The [changelog](/changelog) lists every release, the [upgrade guide](/upgrade) the breaking changes.

## FAQ

### How do I know which version I have?

`composer show tomaj/nette-api`.

### Will 3.x still get fixes after 4.0?

A [4.0.0 milestone](https://github.com/tomaj/nette-api/milestone/9) is open with planned breaking changes, such as removing the unsafe `'auto'` CORS mode and raising the minimum PHP version. It has no release date yet. Whether 3.x gets fixes after it is not decided; today fixes and features go to 3.x, and the [changelog](/changelog) shows every release.

### Do the pages mark features of a newer release?

Yes, a "Since 3.6.0" note marks what the upcoming 3.6.0 release adds; it is not in 3.5.0 yet, as on [CORS](/guide/cors) and [Logging](/guide/logging).

### Where do I report a mistake in the docs?

Open an issue or a pull request, see [Contributing](/contributing).

### Is this the same as the API versions in my URLs?

No, those are described in [Links and versions](/guide/links#versions).
