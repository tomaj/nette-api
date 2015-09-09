<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\Response;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Params\ParamsProcessor;

class ApiPresenter extends Presenter
{
    /** @var  ApiDecider @inject */
    public $apiDecider;

    public function renderDefault()
    {
        // get handler
        $hand = $this->apiDecider->getApiHandler(
            $this->request->getMethod(),
            $this->params['version'],
            $this->params['package'],
            $this->params['apiAction']
        );
        $handler = $hand['handler'];
        $authorization = $hand['authorization'];

        // check authorization
        if (!$authorization->authorized()) {
            $this->getHttpResponse()->setCode(Response::S500_INTERNAL_SERVER_ERROR);
            $this->sendResponse(new JsonResponse(['status' => 'error', 'message' => $authorization->getErrorMessage()]));
            return;
        }

        // process params
        $paramsProcessor = new ParamsProcessor($handler->params());
        if ($paramsProcessor->isError()) {
            $this->getHttpResponse()->setCode(Response::S500_INTERNAL_SERVER_ERROR);
            $this->sendResponse(new JsonResponse(['status' => 'error', 'message' => 'wrong input']));
            return;
        }
        $params = $paramsProcessor->getValues();

        // process handler
        $response = $handler->handle($params);
        $code = $response->getCode();

        // output to nette
        $this->getHttpResponse()->setCode($code);
        $this->sendResponse($response);
    }
}
