<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Actions;

use GSU\D2L\DataHub\Schema\Model\DatasetSchemaType;
use GSU\D2L\DataHub\Schema\SchemaRepository;

abstract class GenerateSQLAction
{
    /**
     * @param SchemaRepository $schemaRepository
     */
    public function __construct(protected SchemaRepository $schemaRepository)
    {
    }


    /**
     * @return array<string,string>
     */
    public function getTableMap(): array
    {
        $contents = file_get_contents("{$this->getTableDir()}/table_map.json");
        if (!is_string($contents)) {
            throw new \RuntimeException("Unable to read table map file");
        }

        $tableMap = json_decode($contents, true, 2, JSON_THROW_ON_ERROR);
        if (!is_array($tableMap)) {
            throw new \RuntimeException("Unable to create table map");
        }

        return array_filter(
            $tableMap,
            fn ($v, $k) => is_string($v) && is_string($k),
            ARRAY_FILTER_USE_BOTH
        );
    }


    abstract public function getTableDir(): string;


    abstract public function generateTable(
        DatasetSchemaType $type,
        string $datasetName,
        string $tableName
    ): void;
}
