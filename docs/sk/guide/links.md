---
title: Odkazy a verzie
description: Vytváranie URL na endpointy Nette API cez ApiLink a Latte tag {apiLink}, odkaz na konkrétnu verziu a beh viacerých verzií API vedľa seba v jednej aplikácii.
---

# Odkazy a verzie

## ApiLink

`Tomaj\NetteApi\Link\ApiLink` vytvára URL cez Nette router, takže odkazy zodpovedajú vašej route:

```php
$url = $apiLink->link(new EndpointIdentifier('GET', 1, 'users', 'detail'), ['id' => 5]);
```

Potrebuje routu na `Api:Api:default` s parametrami `version`, `package` a `apiAction`, ako v [Začíname](/sk/guide/getting-started).

V handleri, ktorý dedí z `BaseHandler`, zavolajte `$this->createLink($params)` pre odkaz na vlastný endpoint handlera. Najprv potrebuje Nette `LinkGenerator`, inak vyhodí `Nette\InvalidStateException`. Pridajte do služby handlera riadok `setup`:

```yaml
services:
	-
		factory: App\Api\UserDetailHandler
		setup:
			- setupLinkGenerator(@Nette\Application\LinkGenerator)
```

Pozrite si aj [Handlery](/sk/guide/handlers#vlastny-endpoint).

## Tag {apiLink} v Latte

```latte
<a href="{apiLink 'GET', '1', 'openapi', null, ['format' => 'yaml']}">YAML</a>
```

Argumenty sú metóda, verzia, balík, akcia a parametre. Zaregistrujte rozšírenie a filter `apiLink`, ktorý tag volá:

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

Staršie `ApiLinkMacro` z 2.x v 3.x neexistuje.

## Verzie

Verzia je súčasťou každého `EndpointIdentifier` aj URL, `/api/v1/users`, `/api/v2/users`. Zaregistrujte obe a starý handler nechajte, kým klienti neprejdú:

```yaml
- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), App\Api\V1\UserListHandler, ...)
- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 2, 'users'), App\Api\V2\UserListHandler, ...)
```

Verzia môže byť číslo alebo reťazec ako `'1.1'`, bez `/`. Starý handler označte cez `deprecated()`, aby to ukázalo OpenAPI.

## Časté otázky

### Prečo ApiLink vracia prázdny reťazec?

S týmito parametrami sa nezhodovala žiadna routa na `Api:Api:default`. Skontrolujte masku routy a mapovanie `Api`.

### Môže jeden handler obsluhovať viac verzií?

Áno, zaregistrujte tú istú triedu dvakrát a čítajte `$this->getEndpoint()->getVersion()`.

### Potrebuje každá verzia vlastný OpenAPI endpoint?

Áno, [OpenAPI](/sk/guide/openapi) vypisuje len svoju verziu.

### Je táto stránka o verzii knižnice?

Nie, ide o verziu vášho API. Pre knižnicu pozrite si [Verzie tejto dokumentácie](/sk/guide/versioning).

### Kde je funkčná šablóna s {apiLink}?

V [príklade OpenAPI a konzoly](/sk/examples/openapi-console).
