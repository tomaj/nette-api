---
title: Obmedzenie požiadaviek
description: 'Obmedzenie počtu volaní endpointu Nette API: fixné okno, kĺzavé okno a token bucket, predvolený limit pre celú aplikáciu so zmenou pre endpoint v neone alebo cez #[RateLimit], úložisko v pamäti, v Nette cache alebo atomicky v Redise, hlavičky RateLimit a 429 s Retry-After.'
---

# Obmedzenie požiadaviek

Od verzie 4.0.0 má knižnica vstavané obmedzenia požiadaviek. Nastavíte ich v sekcii `api:` [DI rozšírenia](/sk/guide/di-extension) alebo atribútom [`#[RateLimit]`](/sk/guide/attributes). Každá časť je za rozhraním, takže ju môžete nahradiť vlastnou:

| Časť | Vstavané | Rozhranie |
| --- | --- | --- |
| Algoritmus | `fixedWindow`, `slidingWindow`, `tokenBucket` | `RateLimitAlgorithmInterface`, `RedisScriptAlgorithmInterface` |
| Úložisko | `cache` (Nette cache), `memory`, `redis` | `RateLimitStorageInterface`, `KeyValueStorage` |
| Kľúč (kto zdieľa kvótu) | `ip`, `identity`, `endpoint`, ich zoznam | `RateLimitKeyInterface` |
| Redis klient | ext-redis (`\Redis`, `\RedisCluster`), Predis | `RedisClientInterface` |
| Limiter | `FixedWindowLimiter`, `SlidingWindowLimiter`, `TokenBucketLimiter`, `AlgorithmLimiter`, `RateLimiterChain` | `RateLimiterInterface` |

## Rýchly štart

```yaml
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

api:
	rateLimits:
		perIp: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}
	defaults:
		rateLimit: perIp
```

Každý endpoint teraz povolí 60 požiadaviek za minútu z jednej IP. Počítadlá sú v Nette cache, takže nič ďalšie netreba. Za reverse proxy ju pridajte do `http: proxy:`, inak všetci klienti zdieľajú kvótu proxy, pozrite si [Ktorá IP sa počíta?](#ktora-ip-sa-pocita).

## Predvolený limit pre celú aplikáciu a nastavenie endpointu

`api.rateLimits` definuje pomenované politiky. `api.defaults.rateLimit` je predvolený limit pre všetky endpointy. Endpoint ho môže zmeniť, skombinovať viac politík alebo vypnúť:

```yaml
services:
	- Tomaj\NetteApi\Misc\StaticTokenRepository([my-token: '*'])   # váš TokenRepositoryInterface, pre autorizáciu nižšie

api:
	rateLimits:
		perIp: {algorithm: slidingWindow, limit: 100, interval: 1 minute}
		perUser: {algorithm: tokenBucket, limit: 1000, interval: 1 hour, key: identity}
		login: {algorithm: fixedWindow, limit: 5, interval: 15 minutes, key: [ip, endpoint]}

	defaults:
		rateLimit: perIp                     # všetky endpointy

	endpoints:
		# predvolený perIp
		- {method: GET, version: 1, package: users, handler: App\Api\UsersHandler}
		# iná pomenovaná politika
		- {method: POST, version: 1, package: login, handler: App\Api\LoginHandler, rateLimit: login}
		# viac politík, kontrolujú sa postupne a požiadavka musí prejsť každou
		- {method: GET, version: 1, package: reports, handler: App\Api\ReportsHandler, authorization: Tomaj\NetteApi\Authorization\BearerTokenAuthorization, rateLimit: [perIp, perUser]}
		# vlastný limit len pre tento endpoint
		- {method: POST, version: 1, package: export, handler: App\Api\ExportHandler, rateLimit: {limit: 2, interval: 1 hour}}
		# bez obmedzenia
		- {method: GET, version: 1, package: status, handler: App\Api\StatusHandler, rateLimit: false}
```

To isté na triede handlera cez [atribúty](/sk/guide/attributes):

```php
#[RateLimit('login')]                                      // pomenovaná politika
#[RateLimit(['perIp', 'perUser'])]                         // viac politík
#[RateLimit(limit: 2, interval: '1 hour')]                 // vlastný limit
#[RateLimit(limit: 1000, interval: '1 hour', algorithm: 'tokenBucket', key: 'identity', name: 'quota')]
#[RateLimit(false)]                                        // žiadne
```

Pomenovaná politika je jeden zdieľaný objekt: všetky endpointy s `rateLimit: perIp` zdieľajú počítadlo klienta. Vlastný limit v neone alebo v atribúte má pre každý endpoint vlastné počítadlá (rozšírenie pridá do jeho kľúča `endpoint`); názov jeho politiky je endpoint, napríklad `POST.v1.export`. `name` mení len názov politiky v hlavičkách, počítadlá zostávajú pre každý endpoint. Dve pomenované politiky s rovnakým `name` zlyhajú pri kompilácii, zdieľali by počítadlá; rovnako vlastný `name` zhodný s názvom pomenovanej politiky, na zdieľanie počítadiel použite `rateLimit: perIp`.

Politiky v zozname sa kontrolujú postupne. Prvá, ktorá požiadavku odmietne, odpovie 429 a ďalšie sa už nepočítajú, takže odmietnuté požiadavky jedného klienta nemíňajú kvótu zdieľanú s ostatnými. Limity jedného klienta dajte pred zdieľané, napríklad `[perIp, perEndpoint]`.

### Nastavenia politiky

| Nastavenie | Predvolene | Hodnoty |
| --- | --- | --- |
| `algorithm` | `slidingWindow` | `fixedWindow`, `slidingWindow`, `tokenBucket` alebo [vlastný algoritmus](#vlastny-algoritmus) |
| `limit` | povinné | počet požiadaviek za interval, od 1 do 1 000 000 000 |
| `interval` | povinné | sekundy alebo `30 seconds`, `1 minute`, `2 hours`, `1 day`; od 1 sekundy do 366 dní |
| `key` | `ip` | `ip`, `identity`, `endpoint`, zoznam ako `[identity, endpoint]` alebo trieda implementujúca `RateLimitKeyInterface` |
| `name` | názov v neone | názov politiky v hlavičkách a v kľúči úložiska: písmená, číslice, `_`, `-` a `.`; jedinečný medzi pomenovanými politikami |

Politika môže byť aj trieda implementujúca `RateLimiterInterface`, `@service` alebo `App\MyLimiter(argumenty)`.

### Algoritmy

| Algoritmus | Správanie | Vhodné pre |
| --- | --- | --- |
| `fixedWindow` | počíta v oknách, ktoré začínajú v násobku intervalu; lacné, ale na hranici dvoch okien prepustí až 2× limit | jednoduché kvóty |
| `slidingWindow` | započíta predchádzajúce okno podľa toho, koľko z neho sa ešte prekrýva; plynulé, bez nárazu na hranici | predvolené pre API |
| `tokenBucket` | `limit` tokenov sa rovnomerne dopĺňa počas intervalu; povolí náraz `limit` požiadaviek, potom stálu rýchlosť | klientov, ktorí posielajú v dávkach |

## Pred autorizáciou a po nej

Presenter kontroluje obmedzenia dvakrát: pred autorizáciou a po úspešnej autorizácii. Politika s kľúčom `identity` beží po nej, s identitou z `AuthorizationIdentityInterface` (`BearerTokenAuthorization`, `HeaderApiKeyAuthentication`, `BasicAuthentication`, ...). Ostatné kľúče bežia pred ňou, takže aj klient, ktorý háda tokeny, je obmedzený. Požiadavka bez identity politiky `identity` preskočí. Keď autorizácia implementuje toto rozhranie, ale po úspešnom `authorized()` nevráti identitu, do logu ide varovanie: potomok, ktorý prepíše `authorized()` bez volania rodiča, musí prepísať aj `getIdentity()`.

Vlastný `rateLimit` endpointu s kľúčom `identity` (aj v zozname alebo v pomenovanej politike) zastaví kompiláciu kontajnera, keď je jeho autorizácia známa trieda bez `AuthorizationIdentityInterface`, napríklad `authorization: false` alebo `NoAuthorization`, pretože obmedzenie by sa nikdy neuplatnilo. `defaults.rateLimit` sa nekontroluje, lebo ho zdieľajú endpointy s autorizáciou aj bez nej; ani služba, ktorej typ je len rozhranie.

## Úložiská

```yaml
api:
	rateLimiting:
		storage: cache          # predvolené
		# storage: memory
		# storage: redis
		# redis: @redis         # \Redis, \RedisCluster, Predis\ClientInterface alebo RedisClientInterface
		# prefix: 'myapp:rate-limit:'
		# clock: App\MyClock    # Psr\Clock\ClockInterface
		# logger: @myLogger     # Tracy\ILogger pre varovania, predvolene Tracy log
```

| Úložisko | Atomické | Použitie |
| --- | --- | --- |
| `cache` | áno na jednom serveri, zámok súboru; nie medzi servermi | jeden server; používa Nette cache úložisko aplikácie |
| `memory` | áno, v rámci jedného procesu | testy a dlho bežiace workery (expirované počítadlá sa pri raste odstraňujú); s PHP-FPM začína každá požiadavka prázdna |
| `redis` | áno, jeden Lua skript na požiadavku | viac serverov, presné limity |

::: warning Nette cache je atomická len na jednom serveri
Nette cache nemá inkrement, preto `NetteCacheStorage` číta a zapisuje počítadlo v dvoch krokoch. Na jednom serveri bežia kroky pod zámkom súboru: súbežné požiadavky na seba počkajú a limit platí presne. Súbory zámkov sú v `%tempDir%/nette-api-rate-limit` (64 súborov zdieľaných všetkými kľúčmi), bez DI rozšírenia v `nette-api-rate-limit-<uid>` systémového dočasného adresára, pre každého používateľa zvlášť; adresár odovzdajte ako tretí argument `NetteCacheStorage`, alebo `false` na vypnutie zámku. V adresári aplikácie dostanú súbory práva podľa umask ako Nette cache; webový server a cron iného používateľa ich zdieľajú aj pri umask `022`, pretože súbor zámku, do ktorého sa nedá zapisovať, sa otvorí na čítanie a to na zámok stačí; predvolený adresár sa vytvorí s `0700` a jeho súbory s `0600` a súbor alebo adresár, ktorý tam patrí inému používateľovi, sa nepoužije. Keď sa do adresára nedá zapisovať, alebo sa súbor nepoužije, počítadlo sa zmení bez zámku; zmazaný súbor zámku sa vytvorí znova. Na súborovom systéme bez zámkov (NFS bez lockd) sa počítadlo zmení bez zámku hneď, požiadavka nečaká. Požiadavka čaká na zámok najviac 3 sekundy (štvrtý argument `lockTimeout`), potom úložisko vyhodí výnimku a požiadavka prejde s varovaním v logu, ako pri každej chybe úložiska. Servery so spoločnou cache (Memcached, Redis cez nette/caching) zámok nezdieľajú, takže ich súbežné požiadavky môžu prečítať rovnakú hodnotu a všetky prejsť. Ak musí limit platiť presne naprieč servermi, použite `redis`.

Každý kľúč je jedna položka cache. `FileStorage` zmaže expirovaný súbor, keď ho znova číta, a `SQLiteStorage` expirované riadky ponecháva, takže počítadlá klientov, ktorí sa už nevrátia, zostanú až do `$storage->clean([Cache::All => true])` alebo vyprázdnenia cache. `MemoryStorage` z nette/caching expirované položky nikdy neodstraňuje, takže v dlho bežiacom workeri rastie s každým klientom; tam použite `memory`. `DevNullStorage` nič neuchováva, takže prejde každá požiadavka.
:::

Čas pochádza z hodín aplikačného servera, nie z Redisu. Pri viacerých serveroch majte hodiny synchronizované (NTP): na serveri s posunutými hodinami začína okno v inom okamihu. Token bucket nikdy neposúva svoj čas späť, takže server, ktorý sa oneskoruje, nedoplní bucket dvakrát. Server, ktorý sa oneskoruje, počíta do novšieho okna a nevynuluje ho, v každom úložisku: v Redise si pevné aj posuvné okno pamätajú začiatok najnovšieho okna v kľúči `<kľúč>:start`. `t`, `Retry-After` a expirácia počítadla sa merajú hodinami servera, ktorý odpovedá, takže klienta, ktorý počká `Retry-After`, ten istý server znova neodmietne. Najnovšie okno alebo čas vždy vyhráva, takže limit platí pri akomkoľvek rozdiele hodín; server ďaleko vpredu preto odmieta klientov na ostatných, kým jeho čas neuplynie, čo je ďalší dôvod mať hodiny synchronizované.

Všetky vstavané politiky používajú rovnaké úložisko. Kľúče v Redise sú `prefix` + `{policy:key}`; zátvorky sú hash tag Redis Clustra, takže všetky kľúče jedného počítadla sú v jednom slote. Nainštalujte `ext-redis` alebo `predis/predis` a klienta zaregistrujte ako službu:

```yaml
services:
	redis:
		factory: Redis
		setup:
			- connect('127.0.0.1', 6379, 1.0, null, 0, 1.0)   # 1 sekunda na pripojenie, 1 sekunda na čítanie
		autowired: false

api:
	rateLimiting:
		storage: redis
		redis: @redis
```

Nastavte krátke timeouty. Pri PHP-FPM sa každá požiadavka pripája sama, takže Redis, ktorý neodpovedá, zdrží každú požiadavku s limitom o dĺžku timeoutu. Bez timeoutu požiadavky čakajú na `default_socket_timeout` (predvolene 60 sekúnd) a môžu obsadiť všetky workery, hoci by nakoniec prešli.

### Keď úložisko zlyhá

Obmedzenie nesmie zhodiť API. Úložisko sa vytvorí pri prvej požiadavke s limitom, nie spolu s API, takže Redis, ktorý pri štarte aplikácie nebeží, endpointy nezhodí a endpointy bez limitu sa k nemu nikdy nepripájajú; dlho bežiaci proces skúsi neúspešné pripojenie znova po sekunde, pri PHP-FPM ho skúsi každá požiadavka raz. Keď úložisko vyhodí výnimku (Redis nebeží, adresár cache je len na čítanie), požiadavka prejde (fail-open) a varovanie s príčinou ide do Tracy logu alebo do `Tracy\ILogger` z `rateLimiting.logger` či odovzdaného limiteru. Keď zlyhá aj logger (Tracy bez adresára pre logy), varovanie ide do PHP `error_log` a požiadavka aj tak prejde. `\LogicException` alebo `\Error` (chyba v kóde alebo zlá konfigurácia) sa neskrýva: ako každá výnimka vlastného limitera alebo kľúča ide do `handle()` error handlera, ktorý odpovie `500`.

## Hlavičky a 429

Každá použitá politika pridá hlavičky podľa [draft-ietf-httpapi-ratelimit-headers](https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/); politiky zo zoznamu za tou, ktorá požiadavku odmietla, sa neuplatnili:

```http
RateLimit-Policy: "perIp";q=100;w=60
RateLimit: "perIp";r=97;t=42
```

`q` je limit, `w` okno v sekundách, `r` zostávajúce požiadavky a `t` sekundy, kým bude kvóta znova k dispozícii. Keď ktorákoľvek politika požiadavku odmietne, presenter odpovie `429 Too Many Requests` s `Retry-After` (najdlhšie čakanie, keď vlastný limiter odmietne viacerými výsledkami) a odpoveďou z `handleRateLimit()` error handlera, alebo vašou odpoveďou z `RateLimitResult::$errorResponse`. `ProblemDetailsErrorHandler` pošle [problém](/sk/guide/problem-details) s `limit` a `retryAfter`.

## Vlastné časti

### Vlastný kľúč

```php
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;

final class TenantKey implements RateLimitKeyInterface
{
    public function key(RateLimitContext $context): ?string
    {
        return $_SERVER['HTTP_X_TENANT'] ?? null;           // null limit preskočí
    }

    public function needsIdentity(): bool
    {
        return false;                                       // true ho spustí po autorizácii
    }
}
```

```yaml
api:
	rateLimits:
		perTenant: {limit: 1000, interval: 1 hour, key: App\TenantKey}
		perTenantAndUser: {limit: 100, interval: 1 hour, key: [App\TenantKey, identity]}
```

Hlavičku posiela klient, ktorý môže s každou požiadavkou poslať inú hodnotu; ako kľúč ju použite, len keď ju overí vaša proxy alebo autorizácia. Kľúč dlhší než 200 znakov sa pred uložením zahashuje (SHA-256). Kľúč zo zoznamu spojí časti znakom `|` a `|` a `\` v nich escapuje, takže `[App\TenantKey, identity]` s tenantom `a|identity:x` nemôže zdieľať počítadlo iného tenanta.

### Vlastné úložisko

Rozšírte `KeyValueStorage` a implementujte `load()` a `save()`; funguje s ním každý algoritmus. Výnimka z nich (`PDOException` pri výpadku databázy) sa zmení na `RateLimitStorageException`, takže požiadavka prejde ako pri vstavaných úložiskách; `\LogicException` alebo `\Error` je chyba v kóde a odpovie `500`. `load()` a `save()` bežia v dvoch krokoch bez zámku, takže súbežné požiadavky môžu prečítať rovnakú hodnotu a všetky prejsť. Prepíšte `consume()` a zamknite okolo `parent::consume()` ako `NetteCacheStorage`, alebo sami implementujte atomické `RateLimitStorageInterface::consume()`.

```php
use Tomaj\NetteApi\RateLimit\Storage\KeyValueStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;

final class MemcachedStorage extends KeyValueStorage
{
    public function __construct(private \Memcached $memcached)
    {
    }

    protected function load(string $key, float $now): mixed
    {
        $value = $this->memcached->get('rate:' . md5($key));
        return $value === false ? null : $value;
    }

    protected function save(string $key, mixed $value, int $ttl, float $now): void
    {
        // Memcached číta expiráciu nad 30 dní ako časovú značku, relatívna by vypršala hneď
        $expiration = $ttl > 2_592_000 ? time() + $ttl : $ttl;
        if (!$this->memcached->set('rate:' . md5($key), $value, $expiration)) {
            throw new RateLimitStorageException('Memcached did not save the counter: ' . $this->memcached->getResultMessage());
        }
    }
}
```

```yaml
api:
	rateLimiting:
		storage: App\MemcachedStorage(@memcached)
```

Keď úložisko nie je dostupné, vyhoďte `RateLimitStorageException`, aby požiadavka prešla a varovanie sa zalogovalo.

### Vlastný Redis klient

Implementujte `RedisClientInterface::evaluate(string $script, array $keys, array $arguments): mixed`, ktorý spustí `EVAL`, vráti jeho odpoveď nezmenenú a pri chybovej odpovedi vyhodí výnimku, a odovzdajte ho ako `rateLimiting.redis`. Nastavenia vstavaných klientov nevadia: prefix, serializer a kompresia ext-redis aj `prefix` a `exceptions: false` v Predis fungujú.

### Vlastný limiter

Implementujte `RateLimiterInterface::consume(RateLimitContext $context, int $cost = 1): array` a vráťte jeden `RateLimitResult` pre každú politiku, alebo `[]`, keď v `$context->stage` neplatí žiadny limit. Použite ho ako `rateLimits: {mine: App\MyLimiter}` alebo `#[RateLimit('App\MyLimiter')]`. Presenter z názvu politiky odstráni znaky okrem tlačiteľných ASCII a escapuje `"` a `\`, takže hlavičky zostanú platné; záporné čísla (`limit`, `remaining`, `resetAfter`, `retryAfter`) pošle ako `0`. `resetAfter` s hodnotou `null` vynechá `t` z hlavičky `RateLimit`.

## Vlastný algoritmus

Algoritmus rozhoduje, úložisko len uchováva jeho stav. Váš algoritmus preto funguje s každým úložiskom, aj s Redisom.

`RateLimitAlgorithmInterface::step()` dostane stav, ktorý naposledy vrátil (`null` prvýkrát alebo po vypršaní), a vráti `AlgorithmStep`: výsledok, nový stav a počet sekúnd, koľko ho uchovať. Stav `null` sa neukladá. `RedisStorage` nevie načítať a uložiť v dvoch krokoch atomicky, preto algoritmus pre Redis implementuje aj `RedisScriptAlgorithmInterface::redisScript()` s rovnakou logikou v Lua:

- `KEYS[1]` je kľúč počítadla. Vlastné kľúče píšte ako `KEYS[1] .. ':nazov'`, aby zostali v rovnakom slote Redis Clustra a nestretli sa s kľúčmi vstavaných algoritmov (token bucket používa priamo `KEYS[1]`, okná `KEYS[1] .. ':start'` a `KEYS[1] .. ':<začiatok okna>'`), keď politike zmeníte algoritmus.
- `ARGV` sú `limit`, `interval`, `cost` a `now` (UNIX čas s mikrosekundami).
- Vráti `{accepted (1 alebo 0), remaining, resetAfter, retryAfter (-1 pri prijatí)}` ako celé čísla a nastaví expiráciu každému kľúču, ktorý zapíše.

Príklad povolí jednu požiadavku každých `interval / limit` sekúnd, takže 60 za minútu znamená jednu za sekundu, bez nárazov:

```php
use Tomaj\NetteApi\RateLimit\Algorithm\RedisScriptAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

final readonly class MinimumGap implements RedisScriptAlgorithmInterface
{
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        $gap = $interval / $limit;
        // overte tvar: po zmene konfigurácie môže stav pochádzať z iného algoritmu
        if (is_float($state) && $now - $state < $gap) {
            $wait = (int) ceil($gap - ($now - $state));
            return new AlgorithmStep(new StorageResult(false, 0, $wait, $wait));   // odmietnuté, nič sa neukladá
        }

        // prijaté: stav je čas tejto požiadavky, uchovaný jednu medzeru plus sekundu
        return new AlgorithmStep(new StorageResult(true, 0, (int) ceil($gap), null), $now, (int) ceil($gap) + 1);
    }

    public function redisScript(): string
    {
        return <<<'LUA'
            local limit, interval, now = tonumber(ARGV[1]), tonumber(ARGV[2]), tonumber(ARGV[4])
            local gap = interval / limit
            local last = tonumber(redis.call('GET', KEYS[1] .. ':gap') or '')
            if last and now - last < gap then
                local wait = math.ceil(gap - (now - last))
                return {0, 0, wait, wait}
            end
            redis.call('SET', KEYS[1] .. ':gap', string.format('%.6f', now), 'EX', math.ceil(gap) + 1)
            return {1, 0, math.ceil(gap), -1}
            LUA;
    }
}
```

Použite ho názvom triedy, `@service` alebo `App\MinimumGap(argumenty)`, v neone alebo v atribúte:

```yaml
api:
	rateLimits:
		steady: {algorithm: App\MinimumGap, limit: 60, interval: 1 minute}
```

```php
#[RateLimit(limit: 60, interval: '1 minute', algorithm: MinimumGap::class)]
```

So `storage: redis` alebo s vlastným `RedisStorage` (`@service`, trieda alebo statement) sa kontajner neskompiluje, keď algoritmus (trieda, `@service` alebo statement) nemá Lua skript, takže to zistíte ešte pred nasadením. V PHP použite `new AlgorithmLimiter(new MinimumGap(), 'steady', 60, 60, new IpKey(), $storage)`.

### Testovanie algoritmu

Spustite rovnaký scenár proti každému úložisku, aby sa PHP a Lua verzia nemohli rozísť:

```php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Redis\PhpRedisClient;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;

final class MinimumGapTest extends TestCase
{
    public static function storages(): iterable
    {
        yield 'memory' => ['memory'];
        yield 'redis' => ['redis'];
    }

    #[DataProvider('storages')]
    public function testOneRequestPerGap(string $name): void
    {
        $storage = $name === 'memory' ? new InMemoryStorage() : $this->redisStorage();
        $consume = fn (float $now) => $storage->consume(new MinimumGap(), 'key', 2, 10, 1, $now);

        $this->assertTrue($consume(1000.0)->accepted);
        $rejected = $consume(1003.0);
        $this->assertFalse($rejected->accepted);
        $this->assertSame(2, $rejected->retryAfter);          // medzera je 10 / 2 = 5 sekúnd
        $this->assertTrue($consume(1005.0)->accepted);
    }

    private function redisStorage(): RateLimitStorageInterface
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        return new RedisStorage(new PhpRedisClient($redis), 'test:' . uniqid() . ':');
    }
}
```

Čas je parameter, takže test nemusí čakať. V aplikácii ho limiter berie z `Psr\Clock\ClockInterface` (`rateLimiting.clock`).

## Bez DI rozšírenia

```php
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\SlidingWindowLimiter;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;

$limiter = new SlidingWindowLimiter('perIp', 60, 60, new IpKey(), new NetteCacheStorage($cacheStorage));
$apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users'), $handler, new NoAuthorization(), $limiter);
```

`RateLimiterInterface` sa neautowiruje, odovzdajte ho do `addApi()`. Služba `RateLimitInterface` z 3.x stále ide do každého volania `addApi()` bez štvrtého argumentu, ak nie je zaregistrovaná s `autowired: false`; pri viacerých takých službách sa nepoužije žiadna. Pre žiadny limit odovzdajte `null`.

Kľúč `ip` potrebuje v kontajneri službu `IpDetectorInterface` (DI rozšírenie ju registruje vždy). Bez nej požiadavka na endpoint s obmedzením `ip` odpovie `500` cez error handler a do logu ide `LogicException` s názvom endpointu, takže sa obmedzenie potichu nepreskočí; zaregistrujte `Tomaj\NetteApi\Misc\IpDetector` alebo vlastný detektor. Obmedzenia, ktoré IP nepoužívajú, fungujú aj bez nej.

## Prechod z RateLimitInterface

`RateLimitInterface::check()` z 3.x stále funguje cez `LegacyRateLimitAdapter`, aj v zozname ako `rateLimit: [perIp, App\LegacyLimit]`, s upozornením `E_USER_DEPRECATED`. Bez času na opakovanie nepošle `t`, čas obnovenia nie je známy. V 5.0 bude odstránené. Hlavičky `X-RateLimit-Limit` a `X-RateLimit-Remaining` nahrádzajú `RateLimit-Policy` a `RateLimit`. Pozrite si [návod na upgrade](https://github.com/tomaj/nette-api/blob/master/UPGRADE-4.0.md).

## Časté otázky

### Ktorá IP sa počíta?

Pri predvolenom `IpDetector`, aj keď je nastavený výslovne, adresa z `Nette\Http\IRequest::getRemoteAddress()`, nie z detektora: ten verí hlavičkám `Client-IP` a `X-Forwarded-For`, takže každý klient by s každou hodnotou dostal novú kvótu (pozrite si upozornenie v [Autorizácii](/sk/guide/authorization#tokeny-a-obmedzenie-ip)). Nette verí preposlaným hlavičkám len od proxy, ktoré uvediete:

```yaml
http:
	proxy: 10.0.0.0/8      # vaša reverse proxy alebo load balancer
```

Bez toho všetci klienti za reverse proxy zdieľajú jednu kvótu, kvótu proxy. Vlastný detektor v `api.ipDetector` sa použije tak, ako je, takže sám rozhoduje, ktorým hlavičkám verí. IPv6 klient ovláda celú podsieť, preto je kvóta na `/64`; adresa IPv4 mapovaná do IPv6 (`::ffff:1.2.3.4`) sa počíta ako IPv4. Hodnota dlhšia než IP (45 znakov) sa zahashuje, takže podvrhnuté hlavičky nevytvoria dlhé kľúče v úložisku.

### Logujú sa odpovede 429?

Nie, presenter skončí skôr, než sa spustí logger. Pozrite si [Logovanie](/sk/guide/logging).

### Môže jedna požiadavka stáť viac ako jednu?

Áno, `RateLimiterInterface::consume()` berie `$cost`. Presenter používa 1.
