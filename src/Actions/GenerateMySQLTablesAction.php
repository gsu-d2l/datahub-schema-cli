<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Actions;

use GSU\D2L\DataHub\Schema\Model\ColumnSchema;
use GSU\D2L\DataHub\Schema\Model\ColumnSchemaType;
use GSU\D2L\DataHub\Schema\Model\DatasetSchema;
use GSU\D2L\DataHub\Schema\Model\DatasetSchemaType;

class GenerateMySQLTablesAction extends GenerateSQLAction
{
    /**
     * @return string
     */
    public function getTableDir(): string
    {
        return "{$this->schemaRepository->getSchemaDir()}/mysql";
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

        file_put_contents(
            "{$tableDir}/{$tableName}.sql",
            $this->generateTableSQL($dataset, $tableName)
        );

        file_put_contents(
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
            "DROP TABLE IF EXISTS `{$tableName}`;",
            "",
            "CREATE TABLE `{$tableName}` (",
            implode(",\n", [
                ...$this->generateColumnSQL($dataset),
                ...$this->generateIndexSQL($dataset),
            ]),
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            ""
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
            $dataType = $this->getColumnType($column);
            $canBeNull = $column->isPrimary && !$column->canBeNull ? "NOT NULL" : "DEFAULT NULL";
            $columns[] = "  `{$column->name}` {$dataType} {$canBeNull}";
        }

        return $columns;
    }


    /**
     * @param DatasetSchema $dataset
     * @return string[]
     */
    private function generateIndexSQL(DatasetSchema $dataset): array
    {
        $index = [];
        $primaryKeys = array_column($dataset->getPrimaryColumns(), 'name');
        if (count($primaryKeys) > 0) {
            $index[] = '  UNIQUE KEY (`' . implode('`, `', $primaryKeys) . '`)';
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
            ColumnSchemaType::DECIMAL          => $column->size !== '' ? "({$column->size})" : '',
            ColumnSchemaType::NVARCHAR,
            ColumnSchemaType::VARCHAR          => '(' . min(intval(max(1, intval($column->size))), 9999) . ')',
            ColumnSchemaType::UNIQUEIDENTIFIER => '(36)',
            default                            => ''
        };

        $type = match ($column->type) {
            ColumnSchemaType::BIT              => 'TINYINT',
            ColumnSchemaType::DATETIME2        => 'DATETIME',
            ColumnSchemaType::NVARCHAR,
            ColumnSchemaType::VARCHAR,
            ColumnSchemaType::UNIQUEIDENTIFIER => 'VARCHAR',
            default                            => strtoupper($column->type->value)
        };

        return $type . $size;
    }
}
