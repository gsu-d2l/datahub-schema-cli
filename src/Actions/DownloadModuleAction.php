<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Actions;

use GSU\D2L\DataHub\Schema\CLI\Model\SchemaModule;
use mjfklib\HttpClient\HttpClientMethods;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;

class DownloadModuleAction
{
    public function __construct(
        private ClientInterface $httpClient,
        private ServerRequestFactoryInterface $requestFactory
    ) {
    }


    /**
     * @param SchemaModule $module
     * @return SchemaModule
     */
    public function execute(SchemaModule $module): SchemaModule
    {
        $bytes = HttpClientMethods::writeResponseToFile(
            $this->getContents($module->url),
            $module->getContentsPath()
        );

        if ($bytes < 1) {
            throw new \RuntimeException("Empty response body");
        }

        return $module;
    }


    /**
     * @param string $url
     * @return ResponseInterface
     */
    protected function getContents(string $url): ResponseInterface
    {
        $response = $this->httpClient->sendRequest(
            $this->requestFactory->createServerRequest(
                'GET',
                $url
            )
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new \RuntimeException("Invalid response code: {$statusCode}");
        }

        return $response;
    }
}
