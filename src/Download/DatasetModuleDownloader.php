<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Download;

use GSU\D2L\DataHub\Schema\Model\DatasetModule;
use mjfklib\HttpClient\HttpClientMethods;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;

final class DatasetModuleDownloader
{
    /**
     * @param ClientInterface $httpClient
     * @param ServerRequestFactoryInterface $requestFactory
     */
    public function __construct(
        private ClientInterface $httpClient,
        private ServerRequestFactoryInterface $requestFactory
    ) {
    }


    /**
     * @param DatasetModule $module
     * @return DatasetModule
     */
    public function download(DatasetModule $module): DatasetModule
    {
        $bytes = HttpClientMethods::writeResponseToFile(
            $this->getContents($module->url),
            $module->contentsPath
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
    private function getContents(string $url): ResponseInterface
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
