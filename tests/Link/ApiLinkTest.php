<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Link;

use PHPUnit\Framework\Attributes\DataProvider;
use Nette\Application\UI\InvalidLinkException;
use PHPUnit\Framework\TestCase;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\RouteList;
use Nette\Http\UrlScript;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;

final class ApiLinkTest extends TestCase
{
    public function testATemplateIsFilledFromTheParams(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), ['id' => 'a b', 'q' => 1, 'package' => 'ignored']);

        $this->assertSame('https://example.com/api/v1/users/a%20b/detail?q=1', $link);
    }

    public function testATemplateWithoutAValueStaysLiteral(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'));

        $this->assertSame('https://example.com/api/v1/users/{id}/detail', $link);
    }

    public function testATemplatedPackageWithoutAnActionHasNoTrailingSlash(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users/{id}', null), ['id' => 5]);

        $this->assertSame('https://example.com/api/v1/users/5', $link);
    }

    public function testLinkKeepsASlashInThePackage(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'acme/widgets', 'show'), ['q' => 'a b']);
        $this->assertSame('https://example.com/api/v1/acme/widgets/show?q=a+b', $link);
    }

    /**
     * @return \Iterator<string, array{mixed}>
     */
    public static function valuesThatLeadElsewhere(): \Iterator
    {
        yield 'empty' => [''];
        yield 'dot' => ['.'];
        yield 'dot dot' => ['..'];
        yield 'false' => [false];
        yield 'slash' => ['a/b'];
    }

    #[DataProvider('valuesThatLeadElsewhere')]
    public function testAPathValueThatWouldLeadElsewhereIsRejected(mixed $value): void
    {
        $endpoint = new EndpointIdentifier('GET', 1, 'users', '{id}/detail');
        $this->expectException(InvalidLinkException::class);
        $this->expectExceptionMessage(sprintf("Cannot build a link for GET %s. Path value 'id' cannot be '%s'.", $endpoint->getUrl(), is_scalar($value) ? (string) $value : ''));
        ApiFixtures::apiLink()->link($endpoint, ['id' => $value]);
    }

    public function testATemplateLinkDoesNotNeedAVersionPrefixInTheRouteMask(): void
    {
        $router = new RouteList();
        $router->addRoute('rest/<version>/<package>[/<apiAction>]', 'Api:Api:default');
        $link = new ApiLink(new LinkGenerator($router, new UrlScript('https://example.com/')));

        $this->assertSame('https://example.com/rest/2/users/7/detail', $link->link(new EndpointIdentifier('GET', 2, 'users', '{id}/detail'), ['id' => 7]));
    }

    public function testARouteThatDoesNotPutThePackageInThePathCannotLinkATemplate(): void
    {
        $router = new RouteList();
        $router->addRoute('api/<version>', 'Api:Api:default');
        $link = new ApiLink(new LinkGenerator($router, new UrlScript('https://example.com/')));

        $this->expectException(InvalidLinkException::class);
        $this->expectExceptionMessage('Cannot build a link for GET v1/users/{id}. The router mask must have <package> and <apiAction> as path segments');
        $link->link(new EndpointIdentifier('GET', 1, 'users', '{id}'), ['id' => 7]);
    }
}
