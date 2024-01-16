<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\GenerateSQL;

use GSU\D2L\DataHub\Schema\Model\DatasetSchema;
use GSU\D2L\DataHub\Schema\Model\SQLType;

interface SQLTableGeneratorInterface
{
    /**
     * @return SQLType
     */
    public function getSqlType(): SQLType;


    /**
     * @param DatasetSchema $datasetSchema
     * @param string $tableName
     * @return string
     */
    public function generateTable(
        DatasetSchema $datasetSchema,
        string $tableName
    ): string;
}
