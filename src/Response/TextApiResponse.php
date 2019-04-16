<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use Nette\Application\UI\ITemplate;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;

class TextApiResponse implements ResponseInterface
{
    use SmartObject;

    /** @var int */
    private $code;

    /** @var mixed */
    private $data;

    /**
     * @param int $code
     * @param mixed $data
     */
    public function __construct(int $code, $data)
    {
        $this->code = $code;
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * {@inheritdoc}
     */
    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        if ($this->data instanceof ITemplate) {
            $this->data->render();
        } else {
            echo $this->data;
        }
    }
}
