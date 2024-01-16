<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\GenerateSQL;

use GSU\D2L\DataHub\Schema\Model\ColumnSchema;
use GSU\D2L\DataHub\Schema\Model\ColumnSchemaType;
use GSU\D2L\DataHub\Schema\Model\DatasetSchema;
use GSU\D2L\DataHub\Schema\Model\SQLType;

final class MySQLTableGenerator implements SQLTableGeneratorInterface
{
    /**
     * @return SQLType
     */
    public function getSqlType(): SQLType
    {
        return SQLType::MYSQL;
    }


    /**
     * @inheritdoc
     */
    public function generateTable(
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
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
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
