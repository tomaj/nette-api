<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use Nette\Application\UI\ITemplate;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;
use Tomaj\NetteApi\Misc\TypeHelper;

class TextApiResponse implements ResponseInterface
{
    use SmartObject;

    /**
     * @param mixed $data
     */
    public function __construct(private int $code, private $data)
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        if ($this->data instanceof ITemplate) {
            $this->data->render();
        } else {
            echo TypeHelper::toString($this->data);
        }
    }
}
