<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use Nette\Bridges\HttpDI\HttpExtension;
use Nette\DI\Compiler;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\DI\EndpointDefinition;
use Tomaj\NetteApi\DI\NetteApiExtension;
use Tomaj\NetteApi\RateLimit\NoRateLimit;
use Tomaj\NetteApi\Test\DI\Discovery\Valid\HelloHandler;
use Tomaj\NetteApi\Test\DI\Discovery\Valid\Sub\OptionsHandler;
use Tomaj\NetteApi\Test\DI\Discovery\Valid\PlainHandler;
use Tomaj\NetteApi\Test\DI\Discovery\Valid\UsersHandler;
use Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit;

final class AttributeDiscoveryTest extends TestCase
{
    private const string AUTH = 'authorizations: {token: Tomaj\NetteApi\Authorization\BasicAuthentication({admin: secret})}'
        . "\n    rateLimits: {strict: Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit}";

    public function testHandlersInPathsBecomeEndpoints(): void
    {
        $container = ContainerFactory::create($this->config('paths: [' . $this->dir('Valid') . ']'));

        $apis = $container->getByType(ApiDecider::class)->getApis();
        $this->assertSame([
            'GET v1.1/hello/',
            'HEAD v2/extra/',
            'OPTIONS v2/misc/',
            'GET v1/users/',
            'POST v1/users/create',
            'PUT v1/users/update',
            'PATCH v1/users/patch',
            'DELETE v1/users/delete',
        ], $this->routes($apis));

        $this->assertInstanceOf(HelloHandler::class, $apis[0]->getHandler());
        $this->assertInstanceOf(NoAuthorization::class, $apis[0]->getAuthorization(), '#[Authorization(false)] wins over the default');
        $this->assertInstanceOf(NoRateLimit::class, $apis[0]->getRateLimit(), '#[RateLimit(false)] wins over the default');

        $this->assertInstanceOf(OptionsHandler::class, $apis[2]->getHandler());
        $this->assertInstanceOf(BasicAuthentication::class, $apis[2]->getAuthorization(), 'defaults apply without attributes');
        $this->assertInstanceOf(NoRateLimit::class, $apis[2]->getRateLimit());

        $this->assertInstanceOf(UsersHandler::class, $apis[3]->getHandler());
        $this->assertSame($container->getService('api.authorization.token'), $apis[3]->getAuthorization());
        $this->assertInstanceOf(FixedRateLimit::class, $apis[3]->getRateLimit());
        foreach (array_slice($apis, 4) as $api) {
            $this->assertSame($apis[3]->getHandler(), $api->getHandler(), 'one service per class');
            $this->assertSame($apis[3]->getAuthorization(), $api->getAuthorization());
        }
        $this->assertSame($apis[3]->getHandler(), $container->getByType(UsersHandler::class));
    }

    public function testNamespaceLimitsThePaths(): void
    {
        $container = ContainerFactory::create($this->config(
            'paths: [' . $this->dir('Valid') . "], namespace: '\\Tomaj\\NetteApi\\Test\\DI\\Discovery\\Valid\\Sub\\'",
        ));

        $this->assertSame(['OPTIONS v2/misc/'], $this->routes($container->getByType(ApiDecider::class)->getApis()));
    }

    public function testEmptyNamespaceMeansAllClasses(): void
    {
        $all = ContainerFactory::create($this->config('paths: [' . $this->dir('Valid') . ']'));
        $empty = ContainerFactory::create($this->config('paths: [' . $this->dir('Valid') . "], namespace: ''"));

        $this->assertSame(
            $this->routes($all->getByType(ApiDecider::class)->getApis()),
            $this->routes($empty->getByType(ApiDecider::class)->getApis()),
        );
    }

    public function testRegisteredServicesAreDiscoveredWithoutPaths(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                - Tomaj\NetteApi\Test\DI\Discovery\Valid\PlainHandler
                users: Tomaj\NetteApi\Test\DI\Discovery\Valid\UsersHandler
                hidden:
                    factory: Tomaj\NetteApi\Test\DI\Discovery\Valid\HelloHandler
                    autowired: false
            api:
                {$this->auth()}
                endpoints:
                    - {method: GET, version: 9, package: hidden, handler: @hidden}
            NEON);

        $apis = $container->getByType(ApiDecider::class)->getApis();
        $this->assertSame($container->getService('users'), $apis[1]->getHandler());
        $this->assertSame(['users'], $container->findByType(UsersHandler::class), 'no second service is created');
        $this->assertSame($container->getService('hidden'), $apis[0]->getHandler());
        $this->assertNotContains('GET v1.1/hello/', $this->routes($apis), 'a non-autowired service is not discovered, the same rule as handler: Class');
    }

    public function testIgnoredAttributesOfNonAutowiredServiceFail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Service 'hidden' (class " . HelloHandler::class . ") has endpoint attributes, but it is registered with autowired: false, so the api extension ignores them. Remove autowired: false, or add the directory of the class to api.discovery.paths, or use the service in api.endpoints as handler: @hidden (without quotes, in neon a quoted '@name' is a plain string).");

        ContainerFactory::compile(<<<NEON
            services:
                other:
                    factory: Tomaj\NetteApi\Test\DI\Discovery\Valid\PlainHandler
                    autowired: false
                hidden:
                    factory: Tomaj\NetteApi\Test\DI\Discovery\Valid\HelloHandler
                    autowired: false
            api:
                endpoints:
                    - {method: GET, version: 9, package: other, handler: @other}
            NEON);
    }

    public function testNonAutowiredServiceOfADiscoveredClassIsAllowed(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                hidden:
                    factory: Tomaj\NetteApi\Test\DI\Discovery\Valid\HelloHandler
                    autowired: false
            api:
                {$this->auth()}
                discovery:
                    paths: [{$this->dir('Valid')}]
            NEON);

        $this->assertContains('GET v1.1/hello/', $this->routes($container->getByType(ApiDecider::class)->getApis()));
    }

    public function testAutowiredServiceIsUsedForAClassInPaths(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                users: Tomaj\NetteApi\Test\DI\Discovery\Valid\UsersHandler
            api:
                {$this->auth()}
                discovery:
                    paths: [{$this->dir('Valid')}]
            NEON);

        $apis = $container->getByType(ApiDecider::class)->getApis();
        $this->assertSame(['GET v1.1/hello/', 'HEAD v2/extra/', 'OPTIONS v2/misc/', 'GET v1/users/'], array_slice($this->routes($apis), 0, 4), 'sorted by class name');
        $this->assertSame($container->getService('users'), $apis[3]->getHandler());
        $this->assertSame(['users'], $container->findByType(UsersHandler::class));
    }

    public function testNonAutowiredServiceIsNotUsedForAClassInPaths(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                users:
                    factory: Tomaj\NetteApi\Test\DI\Discovery\Valid\UsersHandler
                    autowired: false
            api:
                {$this->auth()}
                discovery:
                    paths: [{$this->dir('Valid')}]
            NEON);

        $apis = $container->getByType(ApiDecider::class)->getApis();
        $this->assertSame('GET v1/users/', $this->routes($apis)[3]);
        $this->assertInstanceOf(UsersHandler::class, $apis[3]->getHandler());
        $this->assertNotSame($container->getService('users'), $apis[3]->getHandler(), 'like handler: Class, use @users in api.endpoints to reuse it');
        $this->assertCount(2, $container->findByType(UsersHandler::class));
    }

    public function testTwoServicesOfOneAttributedClassFail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Services 'one' and 'two' are both of class " . HelloHandler::class . ', which has endpoint attributes, so its endpoints would be registered twice. Remove one of the services, or set autowired: false on one of them (a service that is not autowired is not used for attribute endpoints).');

        ContainerFactory::compile(<<<NEON
            services:
                one: Tomaj\NetteApi\Test\DI\Discovery\Valid\HelloHandler
                two: Tomaj\NetteApi\Test\DI\Discovery\Valid\HelloHandler
            api:
            NEON);
    }

    public function testEndpointFromAttributeAndNeonFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Endpoint GET /v1.1/hello is defined twice: in api.endpoints[0] and in #[Get] on ' . HelloHandler::class . '. Remove one of them, or change the method, version, package or action of one so the URLs differ.');

        ContainerFactory::compile(<<<NEON
            api:
                endpoints:
                    - {method: GET, version: '1.1', package: hello, handler: Tomaj\NetteApi\Handlers\EchoHandler}
                discovery:
                    paths: [{$this->dir('Valid')}]
                    namespace: Tomaj\NetteApi\Test\DI\Discovery\Valid
            NEON);
    }

    public function testMissingDirectoryFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("api.discovery.paths[1]: directory '" . __DIR__ . "/Discovery/Missing' does not exist. Write an absolute path, for example paths: [%appDir%/Api].");

        ContainerFactory::compile($this->config('paths: [' . $this->dir('Valid') . ', ' . $this->dir('Missing') . ']'));
    }

    public function testAttributeOnANonHandlerFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Class Tomaj\NetteApi\Test\DI\Discovery\NotHandler\NotAHandler has an endpoint attribute, but it does not implement Tomaj\NetteApi\Handlers\ApiHandlerInterface. Extend Tomaj\NetteApi\Handlers\BaseHandler (or implement the interface), or remove the attribute.');

        ContainerFactory::compile($this->config('paths: [' . $this->dir('NotHandler') . ']'));
    }

    public function testAttributeOnAnAbstractHandlerFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Class Tomaj\NetteApi\Test\DI\Discovery\AbstractHandler\BaseApiHandler has an endpoint attribute, but it is abstract, so no handler can be created from it. Attributes are not inherited, put the attribute on each concrete handler class instead.');

        ContainerFactory::compile($this->config('paths: [' . $this->dir('AbstractHandler') . ']'));
    }

    public function testInvalidAttributeFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid endpoint attribute on Tomaj\NetteApi\Test\DI\Discovery\Repeated\TwiceHandler (Attribute "Tomaj\NetteApi\Attribute\Authorization" must not be repeated). Check the attribute arguments, for example #[Get(1, \'users\')] or #[Get(version: 1, package: \'users\', action: \'detail\')]; #[Authorization] and #[RateLimit] can be used only once per class.');

        ContainerFactory::compile($this->config('paths: [' . $this->dir('Repeated') . ']'));
    }

    public function testClassThatCannotBeAutoloadedFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Class Somewhere\Unknown\Lost (' . __DIR__ . '/Discovery/Unloadable/Lost.php) found by api.discovery cannot be autoloaded. Add its namespace to the autoload section of composer.json (for example "autoload": {"psr-4": {"App\\\\": "app/"}}) and run composer dump-autoload, or keep the file out of api.discovery.paths.');

        ContainerFactory::compile($this->config('paths: [' . $this->dir('Unloadable') . ']'));
    }

    public function testClassWithMissingParentFailsWithItsFile(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Class Tomaj\NetteApi\Test\DI\Discovery\BrokenParent\Orphan (' . __DIR__ . '/Discovery/BrokenParent/Orphan.php) found by api.discovery cannot be loaded (Class "Tomaj\NetteApi\Test\DI\Discovery\BrokenParent\MissingParent" not found). Fix the class, or keep it out of the discovery with api.discovery.paths or api.discovery.namespace.');

        ContainerFactory::compile($this->config('paths: [' . $this->dir('BrokenParent') . ']'));
    }

    public function testNamespaceSkipsClassesBeforeLoadingThem(): void
    {
        $container = ContainerFactory::create($this->config('paths: [' . $this->dir('Unloadable') . ', ' . $this->dir('Valid') . '], namespace: Tomaj\NetteApi\Test\DI\Discovery\Valid\Sub'));

        $this->assertCount(1, $container->getByType(ApiDecider::class)->getApis());
    }

    public function testScannedDirectoriesInvalidateTheContainer(): void
    {
        $compiler = new Compiler();
        $compiler->addExtension('http', new HttpExtension());
        $compiler->addExtension('api', new NetteApiExtension());
        $file = tempnam(sys_get_temp_dir(), 'api-neon');
        assert(is_string($file));
        file_put_contents($file, $this->config('paths: [' . $this->dir('Valid') . ']'));
        $compiler->addConfig(new NeonAdapter()->load($file));
        unlink($file);
        $compiler->setClassName('ApiDiscoveryDependencies');
        $compiler->compile();

        // files compared by mtime: a new file in any directory or an edited attribute rebuilds the container
        $files = array_keys($compiler->exportDependencies()[1]);
        $this->assertContains($this->dir('Valid'), $files);
        $this->assertContains($this->dir('Valid/Sub'), $files, 'subdirectories too');
        $this->assertContains($this->dir('Valid/Sub/OptionsHandler.php'), $files);
        $this->assertContains($this->dir('Valid/SubOther/ExtraHandler.php'), $files);
    }

    public function testRegisteredHandlerFileInvalidatesTheContainer(): void
    {
        $compiler = new Compiler();
        $compiler->addExtension('http', new HttpExtension());
        $compiler->addExtension('api', new NetteApiExtension());
        // the non-autowired service first: it is skipped, the services after it are still read
        $compiler->addConfig([
            'services' => ['users' => ['factory' => UsersHandler::class, 'autowired' => false], PlainHandler::class, HelloHandler::class],
            'api' => ['endpoints' => [['method' => 'GET', 'version' => 9, 'package' => 'users', 'handler' => '@users']]],
        ]);
        $compiler->setClassName('ApiServiceDependencies');
        $compiler->compile();

        $files = array_keys($compiler->exportDependencies()[1]);
        $this->assertContains($this->dir('Valid/HelloHandler.php'), $files, 'an edited attribute of a registered handler rebuilds the container');
        $this->assertContains($this->dir('Valid/PlainHandler.php'), $files, 'so does a new attribute on a handler without one');
        $this->assertNotContains($this->dir('Valid/UsersHandler.php'), $files, 'a non-autowired service is not discovered');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMisconfiguredDiscovery(): iterable
    {
        yield 'route on a method' => ['OnMethod', 'Attribute #[Get] on method Tomaj\\NetteApi\\Test\\DI\\Discovery\\OnMethod\\MethodHandler::handle() is ignored, endpoint attributes work only on the handler class. Move it above "class MethodHandler".'];
        yield 'authorization without a route' => ['NoRoute', 'Class Tomaj\\NetteApi\\Test\\DI\\Discovery\\NoRoute\\AuthOnlyHandler has #[Authorization] but no route attribute, so it defines no endpoint and #[Authorization] is ignored. Add a route attribute, for example #[Get(1, \'users\')], or remove #[Authorization].'];
        yield 'invalid version in an attribute' => ['BadVersion', 'Endpoint in #[Get] on Tomaj\\NetteApi\\Test\\DI\\Discovery\\BadVersion\\VersionHandler has an invalid version \'v1\', the \'v\' is added to the URL automatically (/v1/users). Use for example #[Get(1, \'users\')].'];
    }

    #[DataProvider('provideMisconfiguredDiscovery')]
    public function testMisconfiguredDiscoveryFails(string $dir, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile($this->config('paths: [' . $this->dir($dir) . ']'));
    }

    public function testNamespaceMatchingNoClassFails(): void
    {
        try {
            ContainerFactory::compile($this->config('paths: [' . $this->dir('Valid') . '], namespace: Tomaj\NetteApi\Test\DI\Discovery\Vlid'));
            $this->fail('a namespace that matches no class fails');
        } catch (InvalidConfigurationException $e) {
            $valid = 'Tomaj\NetteApi\Test\DI\Discovery\Valid';
            $this->assertStringStartsWith("api.discovery.namespace 'Tomaj\\NetteApi\\Test\\DI\\Discovery\\Vlid' matches no class in api.discovery.paths ('" . $this->dir('Valid') . "'). Did you mean '$valid'? Namespaces found there: '", $e->getMessage());
            $this->assertStringEndsWith("'. Fix the namespace, or remove the option to use all classes in the paths.", $e->getMessage());
            // the order of the namespaces follows the file index
            $found = explode('Namespaces found there: ', $e->getMessage())[1];
            foreach (["'$valid'", "'$valid\\Sub'", "'$valid\\SubOther'"] as $namespace) {
                $this->assertSame(1, substr_count($found, $namespace), $namespace);
            }
            $this->assertSame(2, substr_count($found, "', '"), 'three namespaces, each once');
        }
    }

    public function testNamespaceInAnEmptyDirectoryIsAllowed(): void
    {
        $container = ContainerFactory::create($this->config('paths: [' . $this->dir('Empty') . '], namespace: App\Api'));
        $this->assertSame([], $container->getByType(ApiDecider::class)->getApis());
    }

    public function testQuotedServiceReferenceFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('\'@hidden\' in the handler of endpoint GET /v9/hidden (api.endpoints[0]) is quoted, and in neon a quoted \'@name\' is a plain string, not a service reference. Write it without quotes: @hidden.');
        ContainerFactory::compile(<<<NEON
            services:
                hidden:
                    factory: Tomaj\NetteApi\Test\DI\Discovery\Valid\HelloHandler
                    autowired: false
            api:
                endpoints:
                    - {method: GET, version: 9, package: hidden, handler: '@hidden'}
            NEON);
    }

    /**
     * @return iterable<string, array{string, string, string, ?string, string}>
     */
    public static function provideInvalidAttributeValues(): iterable
    {
        yield 'method' => ['G ET', '1', 'users', null, "Endpoint in #[Endpoint] on App\\Api\\Foo has an invalid method 'G ET', a method is one word of letters. Use an HTTP method, for example #[Endpoint('GET', 1, 'users')]."];
        yield 'package' => ['GET', '1', 'users/admin', null, "Endpoint in #[Get] on App\\Api\\Foo has an invalid package 'users/admin', a package is one URL segment without a slash. Use for example #[Get(1, 'users', 'admin')]."];
        yield 'action' => ['GET', '1', 'users', 'a/b', "Endpoint in #[Get] on App\\Api\\Foo has an invalid action 'a/b', an action is one URL segment without a slash. Use for example #[Get(1, 'users', 'detail')]."];
    }

    #[DataProvider('provideInvalidAttributeValues')]
    public function testInvalidAttributeValueShowsAnAttributeExample(string $method, string $version, string $package, ?string $action, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        $attribute = $method === 'GET' ? 'Get' : 'Endpoint';
        new EndpointDefinition($method, $version, $package, $action, 'X', null, null, "#[$attribute] on App\\Api\\Foo");
    }

    private function config(string $discovery): string
    {
        return <<<NEON
            api:
                {$this->auth()}
                defaults:
                    authorization: token
                discovery: {{$discovery}}
            NEON;
    }

    private function auth(): string
    {
        return self::AUTH;
    }

    private function dir(string $name): string
    {
        return __DIR__ . '/Discovery/' . $name;
    }

    /**
     * @param list<Api> $apis
     * @return list<string>
     */
    private function routes(array $apis): array
    {
        return array_map(
            static fn (Api $api): string => $api->getEndpoint()->getMethod() . ' ' . $api->getEndpoint()->getUrl(),
            $apis,
        );
    }
}
