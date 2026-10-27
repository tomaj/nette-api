<?php

declare(strict_types=1);

namespace Tests;

use App\Api\ArticleDetailHandler;
use App\Api\ArticleRepository;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\JsonApiResponse;

/** The same check ApiPresenter does after handle(): one of outputs() has to accept the response. */
final class OutputTest extends TestCase
{
    public function testEveryResponseMatchesAnOutput(): void
    {
        $handler = new ArticleDetailHandler(new ArticleRepository([1 => 'One']));

        foreach (['1', '999'] as $id) {
            $response = $handler->handle(['id' => $id]);
            $accepted = false;
            foreach ($handler->outputs() as $output) {
                $accepted = $accepted || $output->validate($response)->isOk();
            }
            $this->assertTrue($accepted, "response for id $id matches no output");
        }
    }

    public function testWrongShapeIsRejected(): void
    {
        $handler = new ArticleDetailHandler(new ArticleRepository());
        $wrong = new JsonApiResponse(200, ['id' => '1', 'title' => 'One', 'extra' => true]);

        foreach ($handler->outputs() as $output) {
            $this->assertFalse($output->validate($wrong)->isOk());
        }
    }
}
