<?php

namespace Tomaj\NetteApi\Test\Presenters;

use PHPUnit_Framework_TestCase;
use Nette\Application\Request;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use Nette\Http\Request as HttpRequest;
use Nette\Http\UrlScript;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Presenters\ApiPresenter;

class ApiPresenterTest extends PHPUnit_Framework_TestCase
{
    public function testValidation()
    {
        $apiDecider = new ApiDecider();
        $apiDecider->addApiHandler(new EndpointIdentifier('GET', 1, 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization());

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->injectPrimary(new Container(), null, null, $httpRequest = new HttpRequest(new UrlScript('')), new HttpResponse());

        $request = new Request('Api:Api:default', 'GET', ['version' => 1, 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(200, $result->getCode());
        $this->assertEquals(['status' => 'ok'], $result->getPayload());
        $this->assertEquals('application/json; charset=utf-8', $result->getContentType());
    }
}
