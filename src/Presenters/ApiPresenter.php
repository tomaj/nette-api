<?php

namespace Tomaj\NetteApi\Presenters;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\Response;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Exception;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ApiPresenter extends Presenter
{
    /**
     * @var  ApiDecider @inject
     */
    public $apiDecider;

    /**
     * @var  IpDetectorInterface @inject
     */
    public $ipDetector;

    /**
     * Nette render default method
     *
     * @return void
     */
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
            $this->getRequest()->getMethod(),
            $this->params['version'],
            $this->params['package'],
            $this->params['apiAction']
        );
        $handler = $hand['handler'];
        $authorization = $hand['authorization'];

        // check authorization
        if (!$authorization->authorized()) {
            $this->getHttpResponse()->setCode(Response::S403_FORBIDDEN);
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
        try {
            $response = $handler->handle($params);
            $code = $response->getCode();
        } catch (Exception $exception) {
            $response = new JsonApiResponse(500, ['status' => 'error', 'message' => 'Internal server error']);
            $code = $response->getCode();
        }

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
                $this->getRequest()->getMethod(),
                $requestHeaders,
                filter_input(INPUT_SERVER, 'REQUEST_URI'),
                $this->ipDetector->getRequestIp(),
                filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'),
                ($end-$start) * 1000
            );
        }

        // output to nette
        $this->getHttpResponse()->setCode($code);
        $this->sendResponse($response);
    }
}
