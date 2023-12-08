<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI;

use GSU\D2L\DataHub\Schema\CLI\Model\SchemaModuleList;
use GSU\D2L\DataHub\Schema\SchemaRepository;
use mjfklib\Container\DefinitionSource;
use mjfklib\Container\Env;
use mjfklib\HttpClient\HttpClientDefinitionSource;

class AppDefinitionSource extends DefinitionSource
{
    /**
     * @inheritdoc
     */
    protected function createDefinitions(Env $env): array
    {
        return [
            // SchemaRepository::class => self::autowire(null, [
            //     'schemaDir' => $env['SCHEMA_DIR'] ?? $env->appDir . '/schema'
            // ]),
            SchemaModuleList::class => self::factory([SchemaModuleList::class, 'create'])
        ];
    }


    /**
     * @inheritdoc
     */
    public function getSources(): array
    {
        return [
            HttpClientDefinitionSource::class
        ];
    }
}
