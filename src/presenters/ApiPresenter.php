<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Tomaj\NetteApi\ApiDecider;

class ApiPresenter extends Presenter
{
    /** @var  ApiDecider @inject */
    public $apiDecider;

    public function renderDefault()
    {
        $handler = $this->apiDecider->getApiHandler(
            $this->request->getMethod(),
            $this->params['version'],
            $this->params['package'],
            $this->params['apiAction']
        );

        $response = $handler->handle($this->params['params']);
        $code = $response->getCode();
        $this->getHttpResponse()->setCode($code);
        $this->sendResponse($response);
    }
}
