<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use Nette\Application\Request;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;

class ApiPresenterLogTest extends TestCase
{
    private array $server;

    private ?string $loggedHeaders = null;

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
        $logger = new class ($this) implements ApiLoggerInterface {
            public function __construct(private ApiPresenterLogTest $test)
            {
            }

            public function log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool
            {
                $this->test->logged($requestHeader);
                return true;
            }
        };

        $container = new class ($logger) extends Container {
            public function __construct(private ApiLoggerInterface $logger)
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

    public function logged(string $headers): void
    {
        $this->loggedHeaders = $headers;
    }

    private function runApi(ApiPresenter $presenter): string
    {
        $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']));
        self::assertNotNull($this->loggedHeaders);
        return $this->loggedHeaders;
    }

    public function testHeadersAreNotMaskedByDefault()
    {
        self::assertSame(
            "Authorization: Bearer secret-token\nCookie: PHPSESSID=secret-session\nX-Api-Key: secret-key\nX-Custom-Token: custom-secret\nAccept: application/json\n",
            str_replace(PHP_EOL, "\n", $this->runApi($this->presenter()))
        );
    }

    public function testDefaultSensitiveHeadersAreMasked()
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders();

        self::assertSame(
            "Authorization: ***\nCookie: ***\nX-Api-Key: ***\nX-Custom-Token: custom-secret\nAccept: application/json\n",
            str_replace(PHP_EOL, "\n", $this->runApi($presenter))
        );
    }

    public function testCustomHeadersAreMaskedCaseInsensitive()
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders(['x-custom-TOKEN', 'AUTHORIZATION']);

        self::assertSame(
            "Authorization: ***\nCookie: PHPSESSID=secret-session\nX-Api-Key: secret-key\nX-Custom-Token: ***\nAccept: application/json\n",
            str_replace(PHP_EOL, "\n", $this->runApi($presenter))
        );
    }

    public function testLastCallReplacesTheList()
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders();
        $presenter->setMaskedLogHeaders(['Accept']);

        self::assertSame(
            "Authorization: Bearer secret-token\nCookie: PHPSESSID=secret-session\nX-Api-Key: secret-key\nX-Custom-Token: custom-secret\nAccept: ***\n",
            str_replace(PHP_EOL, "\n", $this->runApi($presenter))
        );
    }

    public function testEmptyListDisablesMasking()
    {
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders();
        $presenter->setMaskedLogHeaders([]);

        self::assertStringContainsString('Authorization: Bearer secret-token', $this->runApi($presenter));
    }

    public function testProxyAuthorizationIsMaskedByDefault()
    {
        $_SERVER['HTTP_PROXY_AUTHORIZATION'] = 'Basic proxy-secret';
        $presenter = $this->presenter();
        $presenter->setMaskedLogHeaders();

        $logged = $this->runApi($presenter);
        self::assertStringContainsString('Proxy-Authorization: ***', $logged);
        self::assertStringNotContainsString('proxy-secret', $logged);
    }

    public function testDefaults()
    {
        self::assertSame(['Authorization', 'Proxy-Authorization', 'Cookie', 'Set-Cookie', 'X-Api-Key'], ApiPresenter::DEFAULT_MASKED_LOG_HEADERS);
        self::assertSame('***', ApiPresenter::MASKED_LOG_HEADER_VALUE);
    }
}
