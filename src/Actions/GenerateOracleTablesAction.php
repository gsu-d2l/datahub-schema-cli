<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Actions;

use GSU\D2L\DataHub\Schema\Model\ColumnSchema;
use GSU\D2L\DataHub\Schema\Model\ColumnSchemaType;
use GSU\D2L\DataHub\Schema\Model\DatasetSchema;
use GSU\D2L\DataHub\Schema\Model\DatasetSchemaType;
use mjfklib\Utils\FileMethods;

class GenerateOracleTablesAction extends GenerateSQLAction
{
    /**
     * @return string
     */
    public function getTableDir(): string
    {
        return "{$this->schemaRepository->getSchemaDir()}/oracle";
    }


    /**
     * @param DatasetSchemaType $type
     * @param string $datasetName
     * @param string $tableName
     * @return void
     */
    public function generateTable(
        DatasetSchemaType $type,
        string $datasetName,
        string $tableName
    ): void {
        $dataset = $this->schemaRepository->fetch($type, $datasetName);

        $tableDir = $this->getTableDir();

        FileMethods::putContents(
            "{$tableDir}/{$tableName}.sql",
            $this->generateTableSQL($dataset, $tableName)
        );

        FileMethods::putContents(
            "{$tableDir}/{$tableName}_LOAD.sql",
            $this->generateTableSQL($dataset, $tableName . '_LOAD')
        );
    }


    /**
     * @param DatasetSchema $dataset
     * @param string $tableName
     * @return string
     */
    private function generateTableSQL(
        DatasetSchema $dataset,
        string $tableName
    ): string {
        return implode("\n", [
            "DROP TABLE {$tableName};",
            "",
            "CREATE TABLE {$tableName} (",
            implode(",\n", $this->generateColumnSQL($dataset)),
            ");",
            ...$this->generateIndexSQL($dataset, $tableName),
            "",
            "QUIT;",
        ]);
    }


    /**
     * @param DatasetSchema $dataset
     * @return string[]
     */
    private function generateColumnSQL(DatasetSchema $dataset): array
    {
        $columns = [];
        foreach ($dataset->columns as $column) {
            $columnName = match (strtolower($column->name)) {
                "group", "comment", "order" => "D2L" . $column->name,
                default => $column->name
            };
            $dataType = $this->getColumnType($column);
            $canBeNull = $column->isPrimary ? "NOT NULL" : "DEFAULT NULL";
            $columns[] = "  {$columnName} {$dataType} {$canBeNull}";
        }

        return $columns;
    }


    /**
     * @param DatasetSchema $dataset
     * @param string $tableName
     * @return string[]
     */
    private function generateIndexSQL(
        DatasetSchema $dataset,
        string $tableName
    ): array {
        $index = [];
        $primaryKeys = array_map(
            fn (string $columnName) => "  " . match (strtolower($columnName)) {
                "group", "comment", "order" => "D2L" . $columnName,
                default => $columnName
            },
            array_column($dataset->getPrimaryColumns(), 'name')
        );
        if (count($primaryKeys) > 0) {
            $index = [
                "",
                "CREATE UNIQUE INDEX {$tableName}_PK ON {$tableName} (",
                implode(",\n", $primaryKeys),
                ");"
            ];
        }
        return $index;
    }


    /**
     * @param ColumnSchema $column
     * @return string
     */
    private function getColumnType(ColumnSchema $column): string
    {
        $size = match ($column->type) {
            ColumnSchemaType::BIGINT           => '(20)',
            ColumnSchemaType::BIT              => '(1)',
            ColumnSchemaType::DATETIME2        => '',
            ColumnSchemaType::DECIMAL,
            ColumnSchemaType::FLOAT            => ($column->size !== '') ? "({$column->size})" : '',
            ColumnSchemaType::INT              => '(10)',
            ColumnSchemaType::NVARCHAR         => '(' . min(intval(2 * max(1, intval($column->size))), 4000) . ')',
            ColumnSchemaType::SMALLINT         => '(5)',
            ColumnSchemaType::VARCHAR          => '(' . min(intval(2 * max(1, intval($column->size))), 4000) . ' CHAR)',
            ColumnSchemaType::UNIQUEIDENTIFIER => '(36)'
        };

        $type = match ($column->type) {
            ColumnSchemaType::BIGINT,
            ColumnSchemaType::BIT,
            ColumnSchemaType::INT,
            ColumnSchemaType::SMALLINT  => 'NUMBER',
            ColumnSchemaType::DATETIME2 => 'TIMESTAMP WITH LOCAL TIME ZONE',
            ColumnSchemaType::DECIMAL   => 'DECIMAL',
            ColumnSchemaType::FLOAT     => 'FLOAT',
            ColumnSchemaType::NVARCHAR  => 'NVARCHAR2',
            default                     => 'VARCHAR2'
        };

        return $type . $size;
    }
}
