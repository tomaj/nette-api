<?php

namespace Tomaj\NetteApi\Presenters;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\Response;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Params\ParamsProcessor;

class ApiPresenter extends Presenter
{
    /** @var  ApiDecider @inject */
    public $apiDecider;

    /** @var  IpDetectorInterface @inject */
    public $ipDetector;

    public function renderList($version = null)
    {
        $list = $this->apiDecider->getHandlersList($version);
        $this->sendJson($list);
    }
    
    public function renderDefault()
    {
        $start = microtime(true);

        $this->getHttpResponse()->addHeader('Access-Control-Allow-Origin', '*');

        $logger = null;
        if ($this->context->hasService('apiLogger')) {
            $logger = $this->context->getService('apiLogger');
        }

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

        $end = microtime(true);

        if ($logger) {
            $headers = [];
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            }

            $requestHeaders = '';
            foreach ($headers as $key => $value) {
                $requestHeaders .= "$key: $value\n";
            }

            $logger->log(
                $code,
                $this->request->getMethod(),
                $requestHeaders,
                $_SERVER['REQUEST_URI'],
                $this->ipDetector->getRequestIp(),
                isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                ($end-$start) * 1000
            );
        }

        // output to nette
        $this->getHttpResponse()->setCode($code);
        $this->sendResponse($response);
    }
}
