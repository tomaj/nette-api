<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use Nette\Application\Request;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Logger\LogMaskMode;
use Tomaj\NetteApi\Logger\LogValueMaskerInterface;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;

final class ApiPresenterLogTest extends TestCase
{
    private array $server;

    private ?string $loggedHeaders = null;

    private ?string $loggedUri = null;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with((string)$key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-token';
        $_SERVER['HTTP_COOKIE'] = 'PHPSESSID=secret-session';
        $_SERVER['HTTP_X_API_KEY'] = 'secret-key';
        $_SERVER['HTTP_X_CUSTOM_TOKEN'] = 'custom-secret';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    private function presenter(): ApiPresenter
    {
        $logger = new readonly class ($this) implements ApiLoggerInterface {
            public function __construct(private ApiPresenterLogTest $test)
            {
            }

            public function log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool
            {
                $this->test->logged($requestHeader, $requestUri);
                return true;
            }
        };

        $container = new class ($logger) extends Container {
            public function __construct(private readonly ApiLoggerInterface $logger)
            {
                parent::__construct();
            }

            public function findByType(string $type): array
            {
                return $type === ApiLoggerInterface::class ? ['logger'] : [];
            }

            public function getByType(string $type, bool $throw = true): ?object
            {
                return $type === ApiLoggerInterface::class ? $this->logger : new StaticIpDetector('127.0.0.1');
            }
        };

        $apiDecider = new ApiDecider(new Container());
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization());

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = $container;
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);
        return $presenter;
    }

    public function logged(string $headers, string $uri = ''): void
    {
        $this->loggedHeaders = $headers;
        $this->loggedUri = $uri;
    }

    public function testRequestUriIsReadFromServerAndMasked(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/v1/test?api_key=query-secret&page=2';
        $this->runApi($this->presenter());
        $this->assertSame('/api/v1/test?api_key=***&page=2', $this->loggedUri);
    }

    public function testMissingRequestUriIsLoggedEmpty(): void
    {
        $_SERVER['REQUEST_URI'] = ['not', 'a', 'string'];
        $this->runApi($this->presenter());
        $this->assertSame('', $this->loggedUri);
    }

    private function runApi(ApiPresenter $presenter): string
    {
        $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']));
        $this->assertNotNull($this->loggedHeaders);
        return $this->loggedHeaders;
    }

    private static function lines(string $logged): string
    {
        return str_replace(PHP_EOL, "\n", $logged);
    }

    public function testHeadersAreMaskedByDefault(): void
    {
        $this->assertSame("Authorization: ***\nCookie: ***\nX-Api-Key: ***\nX-Custom-Token: ***\nAccept: application/json\n", self::lines($this->runApi($this->presenter())));
    }

    public function testDefaultSensitiveHeadersAreMasked(): void
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaderPatterns([]);
        $presenter->setMaskedLogHeaders();

        $this->assertSame("Authorization: ***\nCookie: ***\nX-Api-Key: ***\nX-Custom-Token: custom-secret\nAccept: application/json\n", self::lines($this->runApi($presenter)));
    }

    public function testCustomHeadersAreMaskedCaseInsensitive(): void
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaderPatterns([]);
        $presenter->setMaskedLogHeaders(['x-custom-TOKEN', 'AUTHORIZATION']);

        $this->assertSame("Authorization: ***\nCookie: PHPSESSID=secret-session\nX-Api-Key: secret-key\nX-Custom-Token: ***\nAccept: application/json\n", self::lines($this->runApi($presenter)));
    }

    public function testLastCallReplacesTheList(): void
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaderPatterns([]);
        $presenter->setMaskedLogHeaders();
        $presenter->setMaskedLogHeaders(['Accept']);

        $this->assertSame("Authorization: Bearer secret-token\nCookie: PHPSESSID=secret-session\nX-Api-Key: secret-key\nX-Custom-Token: custom-secret\nAccept: ***\n", self::lines($this->runApi($presenter)));
    }

    public function testPatternsMaskMatchingHeaders(): void
    {
        $_SERVER['HTTP_X_AUTH_USER'] = 'u1';
        $_SERVER['HTTP_X_CLIENT_SECRET'] = 's1';
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders([]);
        $presenter->setMaskedLogHeaderPatterns(['x-auth-*', '*SECRET']);

        $this->assertSame("Authorization: Bearer secret-token\nCookie: PHPSESSID=secret-session\nX-Api-Key: secret-key\nX-Custom-Token: custom-secret\nAccept: application/json\nX-Auth-User: ***\nX-Client-Secret: ***\n", self::lines($this->runApi($presenter)));
    }

    public function testAddAndRemoveHeader(): void
    {
        $presenter = $this->presenter();
        $presenter->removeMaskedLogHeader('cookie');
        $presenter->addMaskedLogHeader('Accept');

        $this->assertSame(['Authorization', 'Proxy-Authorization', 'Set-Cookie', 'X-Api-Key', 'Accept'], $presenter->getMaskedLogHeaders());
        $this->assertSame("Authorization: ***\nCookie: PHPSESSID=secret-session\nX-Api-Key: ***\nX-Custom-Token: ***\nAccept: ***\n", self::lines($this->runApi($presenter)));
    }

    public function testRemovePattern(): void
    {
        $presenter = $this->presenter();
        $presenter->removeMaskedLogHeaderPattern('*TOKEN*');
        $presenter->addMaskedLogHeaderPattern('accept*');

        $this->assertSame(['*secret*', '*key*', 'x-auth-*', 'accept*'], $presenter->getMaskedLogHeaderPatterns());
        $this->assertSame("Authorization: ***\nCookie: ***\nX-Api-Key: ***\nX-Custom-Token: custom-secret\nAccept: ***\n", self::lines($this->runApi($presenter)));
    }

    public function testRemovingAHeaderNotInTheListFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("removeMaskedLogHeader('Set-Cokie'): 'Set-Cokie' is not in the masked headers (Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key), so there is nothing to remove. Did you mean 'Set-Cookie'?");
        $this->presenter()->removeMaskedLogHeader('Set-Cokie');
    }

    public function testAuthorizationHeaderAndCookieNamesAreMasked(): void
    {
        $_SERVER['HTTP_X_PARTNER'] = 'partner-secret';
        $_SERVER['HTTP_COOKIE'] = 'PHPSESSID=session; api_session=cookie-secret';
        $presenter = $this->presenter();
        $presenter->apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'header'), new AlwaysOkHandler(), new HeaderApiKeyAuthentication('X-Partner', new StaticTokenRepository([]), new StaticIpDetector('127.0.0.1')));
        $presenter->apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'cookie'), new AlwaysOkHandler(), new CookieApiKeyAuthentication('api_session', new StaticTokenRepository([]), new StaticIpDetector('127.0.0.1')));
        $presenter->apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'query'), new AlwaysOkHandler(), new QueryApiKeyAuthentication('partner_key', new StaticTokenRepository([]), new StaticIpDetector('127.0.0.1')));
        $presenter->removeMaskedLogHeader('Cookie');

        $logged = self::lines($this->runApi($presenter));
        $this->assertStringContainsString("Cookie: PHPSESSID=session; api_session=***\n", $logged);
        $this->assertStringContainsString("X-Partner: ***\n", $logged);
        $this->assertStringNotContainsString('secret', $logged);
    }

    public function testMaskingOffLeavesAuthorizationNamesToo(): void
    {
        $_SERVER['HTTP_X_PARTNER'] = 'partner-secret';
        $_SERVER['HTTP_COOKIE'] = 'api_session=cookie-secret';
        $presenter = $this->presenter();
        $presenter->apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'header'), new AlwaysOkHandler(), new HeaderApiKeyAuthentication('X-Partner', new StaticTokenRepository([]), new StaticIpDetector('127.0.0.1')));
        $presenter->apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'cookie'), new AlwaysOkHandler(), new CookieApiKeyAuthentication('api_session', new StaticTokenRepository([]), new StaticIpDetector('127.0.0.1')));
        $presenter->setMaskedLogHeaders([]);

        $logged = self::lines($this->runApi($presenter));
        $this->assertStringContainsString("X-Partner: partner-secret\n", $logged);
        $this->assertStringContainsString("Cookie: api_session=cookie-secret\n", $logged);
    }

    public function testMaskingModes(): void
    {
        $presenter = $this->presenter();
        $presenter->setLogMasking(LogMaskMode::Omit);
        $this->assertSame(LogMaskMode::Omit, $presenter->getLogMasking()->getMode());
        $this->assertSame("Accept: application/json\n", self::lines($this->runApi($presenter)));

        $presenter = $this->presenter();
        $presenter->setLogMasking(LogMaskMode::Partial);
        $this->assertStringContainsString("Authorization: Bearer secr***\nCookie: PHPS***\nX-Api-Key: ***\n", self::lines($this->runApi($presenter)));

        $presenter = $this->presenter();
        $presenter->setLogMasking(LogMaskMode::Hash);
        $this->assertStringContainsString('X-Api-Key: hash:' . substr(hash('sha256', 'secret-key'), 0, 16) . "\n", self::lines($this->runApi($presenter)));
    }

    public function testCustomMasker(): void
    {
        $masker = new class implements LogValueMaskerInterface {
            public function mask(string $value): ?string
            {
                return '[' . strlen($value) . ']';
            }
        };
        $presenter = $this->presenter();
        $presenter->setLogMasking($masker);

        $this->assertSame($masker, $presenter->getLogMasking());
        $this->assertStringContainsString("X-Api-Key: [10]\n", self::lines($this->runApi($presenter)));
    }

    public function testEmptyListDisablesMasking(): void
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders();
        $presenter->setMaskedLogHeaders([]);

        $this->assertSame([], $presenter->getMaskedLogHeaders());
        $this->assertSame([], $presenter->getMaskedLogHeaderPatterns(), 'an empty list turns the patterns off too');
        $this->assertStringContainsString('Authorization: Bearer secret-token', $this->runApi($presenter));
        $this->assertStringContainsString('X-Custom-Token: custom-secret', $this->runApi($presenter));
    }

    public function testProxyAuthorizationIsMaskedByDefault(): void
    {
        $_SERVER['HTTP_PROXY_AUTHORIZATION'] = 'Basic proxy-secret';
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders();

        $logged = $this->runApi($presenter);
        $this->assertStringContainsString('Proxy-Authorization: ***', $logged);
        $this->assertStringNotContainsString('proxy-secret', $logged);
    }

    public function testDefaults(): void
    {
        $this->assertSame(['Authorization', 'Proxy-Authorization', 'Cookie', 'Set-Cookie', 'X-Api-Key'], ApiPresenter::DEFAULT_MASKED_LOG_HEADERS);
        $this->assertSame('***', ApiPresenter::MASKED_LOG_HEADER_VALUE);
        $this->assertSame(['*token*', '*secret*', '*key*', 'x-auth-*'], ApiPresenter::DEFAULT_MASKED_LOG_HEADER_PATTERNS);
        $this->assertSame(['api_key', 'apikey', 'key', 'token', 'access_token', 'refresh_token', 'id_token', 'auth', 'password', 'secret', 'signature', 'sig', 'code', 'client_secret'], ApiPresenter::DEFAULT_MASKED_LOG_QUERY_PARAMS);
    }
}
