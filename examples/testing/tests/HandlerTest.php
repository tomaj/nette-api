<?php

declare(strict_types=1);

namespace Tests;

use App\Api\ArticleDetailHandler;
use App\Api\ArticleRepository;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\JsonApiResponse;

/** handle() is a plain method: give it the params, check the response. No HTTP, no presenter. */
final class HandlerTest extends TestCase
{
    public function testFound(): void
    {
        $handler = new ArticleDetailHandler(new ArticleRepository([5 => 'Five']));

        $response = $handler->handle(['id' => '5']);

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertSame(200, $response->getCode());
        $this->assertSame(['id' => 5, 'title' => 'Five'], $response->getPayload());
    }

    public function testNotFound(): void
    {
        $handler = new ArticleDetailHandler(new ArticleRepository([]));

        $response = $handler->handle(['id' => '5']);

        $this->assertSame(404, $response->getCode());
    }
}
