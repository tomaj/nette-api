---
title: Handlery
description: Písanie handlerov v Nette API - dedenie z BaseHandler, deklarácia params(), outputs(), summary, tagov a deprecation a registrácia cez ApiDecider::addApi().
---

# Handlery

Handler je kód jedného endpointu. Implementuje `Tomaj\NetteApi\Handlers\ApiHandlerInterface`; v praxi dedíte z `BaseHandler` a píšete `handle()`.

<Diagram name="handlers-1" alt="Handler deklaruje parametre, výstupy a metadáta; čítajú ich ApiPresenter, OpenApiHandler a webová konzola" />

## Metódy

| Metóda | Predvolená hodnota v BaseHandler | Používa |
| --- | --- | --- |
| `handle(array $params): ResponseInterface` | abstraktná, píšete ju vy | ApiPresenter |
| `params(): array` | `[]` | validácia, OpenAPI, konzola |
| `outputs(): array` | `[]` | kontrola výstupu, OpenAPI |
| `summary(): string` | `''` | OpenAPI, konzola |
| `description(): string` | `''` | OpenAPI, konzola |
| `tags(): array` | `[]` | OpenAPI |
| `deprecated(): bool` | `false` | OpenAPI |

`handle()` dostane hodnoty parametrov podľa ich názvov. Vracia odpoveď, napríklad `JsonApiResponse`, pozrite si [Výstupy a odpovede](/sk/guide/outputs).

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Model\UserRepository;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\InputType;

final class UserDetailHandler extends BaseHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function summary(): string
    {
        return 'Get one user';
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id', InputType::INTEGER))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $user = $this->users->find($params['id']);
        if ($user === null) {
            return new JsonApiResponse(404, ['status' => 'error', 'message' => 'User not found']);
        }
        return new JsonApiResponse(200, ['id' => $user->id, 'name' => $user->name]);
    }
}
```

`BaseHandler` má konštruktor s voliteľným Fractal `ScopeFactoryInterface`. Keď má váš handler vlastný konštruktor, zavolajte `parent::__construct()`.

## Registrácia handlera

`ApiDecider::addApi()` berie endpoint, handler, autorizáciu a voliteľné obmedzenie požiadaviek:

```yaml
services:
	- App\Api\UserDetailHandler
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users', 'detail'), App\Api\UserDetailHandler, Tomaj\NetteApi\Authorization\NoAuthorization())
```

Handler môže byť objekt alebo názov triedy. Názov triedy sa hľadá v DI kontajneri až pri volaní endpointu, takže handler s ťažkými závislosťami nič nestojí pri iných požiadavkách. Zároveň to rieši cyklické závislosti, napríklad pre `OpenApiHandler`, ktorý potrebuje samotný `ApiDecider`.

## Vlastný endpoint

`ApiDecider` zavolá `setEndpointIdentifier()` pred `handle()`, takže `$this->getEndpoint()` vráti nájdený endpoint. [Príklad autorizácie](/sk/examples/auth) vracia `getEndpoint()->getUrl()`, čo je pre tú routu `v1/bearer/`.

`createLink()` vytvorí absolútny odkaz na ten istý endpoint, keď predtým zavoláte `setupLinkGenerator()` s Nette `LinkGenerator` (riadok `setup` v neon, pozrite si [Odkazy](/sk/guide/links)); bez neho vyhodí `Nette\InvalidStateException`. Odkazy na iné endpointy opisujú [Odkazy a verzie](/sk/guide/links).

## Handlery, ktoré sú súčasťou knižnice

| Trieda | Čo robí |
| --- | --- |
| `DefaultHandler` | Zastupuje neznámy endpoint; presenter odpovie `404 Unknown api endpoint` cez metódu `handleNotFound()` error handlera. Od verzie 4.0.0 je `final`. |
| `AlwaysOkHandler` | Vráti `200 {"status":"ok"}`, hodí sa na health check. |
| `EchoHandler` | Vráti povinný `status` (`ok` alebo `error`) a všetky svoje parametre, na testovanie. |
| `CorsPreflightHandler` | Odpovedá na `OPTIONS` požiadavky, pozrite si [CORS](/sk/guide/cors). |
| `OpenApiHandler` | OpenAPI dokument, pozrite si [OpenAPI](/sk/guide/openapi). |
| `ApiListingHandler` | JSON zoznam endpointov vo vlastnej verzii. |

## Časté otázky

### Musia byť handlery služby?

Nie, `addApi()` prijme aj objekt vytvorený v neon setupe. Ako služby ich registrujte, keď potrebujú autowirované závislosti. Robí to [príklad parametrov](/sk/examples/params-validation).

### Ako vrátim iný stavový kód?

Odovzdajte ho ako prvý argument odpovede, napríklad `new JsonApiResponse(404, [...])`. Pozrite si [Výstupy a odpovede](/sk/guide/outputs).

### Mám v handle() zachytávať výnimky?

Len keď chcete konkrétnu odpoveď. Nezachytené výnimky idú do `error handlera` a stane sa z nich `500`. Pozrite si [Spracovanie chýb](/sk/guide/errors).

### Ako označím endpoint ako zastaraný?

Vráťte `true` z `deprecated()`. OpenAPI označí operáciu ako deprecated; endpoint funguje ďalej. Pozrite si [OpenAPI](/sk/guide/openapi).

### Môže jeden handler obsluhovať dva endpointy?

Áno, zaregistrujte ho dvakrát s rôznymi `EndpointIdentifier`. `getEndpoint()` povie, ktorý bol zavolaný. Pozrite si [Odkazy a verzie](/sk/guide/links#verzie).
