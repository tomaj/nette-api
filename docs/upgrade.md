---
title: Upgrade guide
description: Breaking changes between Nette API major versions from 1.x to 3.x, how to update your code, and what the planned 4.0 release is expected to change.
---

<!--@include: ../UPGRADE.md-->

## FAQ

### Is there a guide for 2.x to 3.0?

Not a separate one. The breaking changes of 3.0.0 are the [BC] items in the [changelog](/changelog): `DefaultHandler` answers `404` instead of `400`, `ApiDecider` gets the DI container, and the output configurator and error handler must be registered as services.

### Is there a 4.0 to upgrade to?

Not yet. 3.x is the current line. 4.0 is in development on `master`: it needs PHP 8.4 or newer and its breaking changes so far are in [UPGRADE-4.0.md](https://github.com/tomaj/nette-api/blob/master/UPGRADE-4.0.md). A [4.0.0 milestone](https://github.com/tomaj/nette-api/milestone/9) is open with planned breaking changes, such as removing the unsafe `'auto'` CORS mode and raising the minimum PHP version. It has no release date yet; see [Versions of this documentation](/guide/versioning).

### Which PHP version does 3.x need?

PHP 8.3 or newer, see [Getting started](/guide/getting-started#requirements).
