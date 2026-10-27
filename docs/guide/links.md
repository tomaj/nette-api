---
title: Links and versions
description: Build URLs to Nette API endpoints with ApiLink and the {apiLink} Latte tag, link to a specific version, and run several API versions side by side in one app.
---

# Links and versions

## ApiLink

`Tomaj\NetteApi\Link\ApiLink` builds URLs through the Nette router, so links follow your route:

```php
$url = $apiLink->link(new EndpointIdentifier('GET', 1, 'users', 'detail'), ['id' => 5]);
```

It needs a route to `Api:Api:default` with `version`, `package` and `apiAction` parameters, as in [Getting started](/guide/getting-started).

Inside a handler extending `BaseHandler`, call `$this->createLink($params)` for a link to the handler's own endpoint. It needs Nette's `LinkGenerator` first, otherwise it throws `Nette\InvalidStateException`. Add a `setup` line to the handler service:

```yaml
services:
	-
		factory: App\Api\UserDetailHandler
		setup:
			- setupLinkGenerator(@Nette\Application\LinkGenerator)
```

See also [Handlers](/guide/handlers#knowing-your-own-endpoint).

## The {apiLink} tag

```latte
<a href="{apiLink 'GET', '1', 'openapi', null, ['format' => 'yaml']}">YAML</a>
```

Arguments are method, version, package, action and params. Register the extension and the `apiLink` filter that the tag calls:

```yaml
latte:
	extensions:
		- Tomaj\NetteApi\Link\ApiLinkExtension

services:
	apiLink: Tomaj\NetteApi\Link\ApiLink
	latte.latteFactory:
		setup:
			- addFilter(apiLink, [@apiLink, link])
```

The older `ApiLinkMacro` from 2.x does not exist in 3.x.

## Versions

The version is part of every `EndpointIdentifier` and of the URL, `/api/v1/users`, `/api/v2/users`. Register both and keep the old handler until clients move:

```yaml
- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), App\Api\V1\UserListHandler, ...)
- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 2, 'users'), App\Api\V2\UserListHandler, ...)
```

A version can be a number or a string like `'1.1'`, without `/`. Mark the old handler with `deprecated()` so OpenAPI shows it.

## FAQ

### Why does ApiLink return an empty string?

No route matched `Api:Api:default` with those parameters. Check the route mask and the `Api` mapping.

### Can one handler serve several versions?

Yes, register the same class twice and read `$this->getEndpoint()->getVersion()`.

### Does each version need its own OpenAPI endpoint?

Yes, [OpenAPI](/guide/openapi) lists only its own version.

### Is this page about the version of the library?

No, this is your API's version. For the library see [Versions of this documentation](/guide/versioning).

### Where is a working {apiLink} template?

In the [OpenAPI and console example](/examples/openapi-console).
