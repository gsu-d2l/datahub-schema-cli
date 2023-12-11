<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Actions;

use GSU\D2L\DataHub\Schema\Model\DatasetSchemaType;
use GSU\D2L\DataHub\Schema\SchemaRepository;
use mjfklib\Utils\ArrayValue;
use mjfklib\Utils\FileMethods;
use mjfklib\Utils\JSON;

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
        return ArrayValue::convertToStringArray(
            JSON::decodeArray(
                FileMethods::getContents(
                    "{$this->getTableDir()}/table_map.json"
                )
            )
        );
    }


    abstract public function getTableDir(): string;


    abstract public function generateTable(
        DatasetSchemaType $type,
        string $datasetName,
        string $tableName
    ): void;
}
