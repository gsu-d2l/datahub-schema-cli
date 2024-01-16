<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI;

use GSU\D2L\DataHub\Schema\CLI\GenerateSQL\GenerateSQLCommand;
use GSU\D2L\DataHub\Schema\CLI\GenerateSQL\MySQLTableGenerator;
use GSU\D2L\DataHub\Schema\CLI\GenerateSQL\OracleTableGenerator;
use GSU\D2L\DataHub\Schema\CLI\GenerateSQL\SQLTableGeneratorFactory;
use GSU\D2L\DataHub\Schema\CLI\GenerateSQL\SQLTableGeneratorInterface;
use GSU\D2L\DataHub\Schema\Model\SQLType;
use GSU\D2L\DataHub\Schema\SchemaDefinitionSource;
use mjfklib\Container\DefinitionSource;
use mjfklib\Container\Env;
use mjfklib\HttpClient\HttpClientDefinitionSource;
use Psr\Container\ContainerInterface;

final class AppDefinitionSource extends DefinitionSource
{
    /**
     * @inheritdoc
     */
    protected function createDefinitions(Env $env): array
    {
        return [
            SQLTableGeneratorFactory::class => static::factory(
                fn (ContainerInterface $c): SQLTableGeneratorFactory => new SQLTableGeneratorFactory(
                    function (SQLType $sqlType) use ($c): SQLTableGeneratorInterface {
                        $generateSQLTables = match ($sqlType) {
                            SQLType::MYSQL => $c->get(MySQLTableGenerator::class),
                            SQLType::ORACLE => $c->get(OracleTableGenerator::class)
                        };

                        return ($generateSQLTables instanceof SQLTableGeneratorInterface)
                            ? $generateSQLTables
                            : throw new \RuntimeException();
                    }
                )
            )
        ];
    }


    /**
     * @inheritdoc
     */
    public function getSources(): array
    {
        return [
            HttpClientDefinitionSource::class,
            SchemaDefinitionSource::class
        ];
    }
}
