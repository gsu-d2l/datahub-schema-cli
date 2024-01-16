<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\GenerateSQL;

use GSU\D2L\DataHub\Schema\Model\DatasetSchemaType;
use GSU\D2L\DataHub\Schema\Model\SQLType;
use GSU\D2L\DataHub\Schema\SchemaRepositoryInterface;
use mjfklib\Console\Command\Command;
use mjfklib\Utils\FileMethods;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'schema:generate-sql')]
final class GenerateSQLCommand extends Command
{
    /**
     * @param SchemaRepositoryInterface $schemaRepository
     * @param SQLTableGeneratorFactory $sqlTableGeneratorFactory
     */
    public function __construct(
        private SchemaRepositoryInterface $schemaRepository,
        private SQLTableGeneratorFactory $sqlTableGeneratorFactory
    ) {
        parent::__construct(false, true);
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument(
            name: 'sql-type',
            mode: InputOption::VALUE_REQUIRED,
            description: 'SQL type. Valid options are ' . implode(", ", array_map(
                fn ($sqlType) => "'{$sqlType->value}'",
                SQLType::cases()
            ))
        );

        $this->addArgument(
            name: 'dataset-type',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Dataset type. Valid options are ' . implode(", ", array_map(
                fn ($sqlType) => "'{$sqlType->value}'",
                DatasetSchemaType::cases()
            ))
        );

        $this->addOption(
            name: 'purge',
            shortcut: 'p',
            mode: InputOption::VALUE_NONE,
            description: 'Purge tables directory before generation',
            default: null
        );
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $sqlType = $input->getArgument('sql-type');
        $sqlType = is_string($sqlType)
            ? SQLType::getType($sqlType)
            : throw new \RuntimeException('Invalid SQL yype');

        $sqlTableGenerator = ($this->sqlTableGeneratorFactory)($sqlType);

        $datasetType = $input->getArgument('dataset-type');
        $datasetType = is_string($datasetType)
            ? DatasetSchemaType::getType($datasetType)
            : throw new \RuntimeException('Invalid dataset type');

        if ($input->getOption('purge') === true) {
            $this->schemaRepository->cleanUpSqlTables($sqlType);
        }

        $tableMap = $this->schemaRepository->listSqlTables($sqlType);
        foreach ($tableMap as $datasetName => $tableName) {
            try {
                $datasetSchema = $this->schemaRepository->fetchDataset(
                    $datasetType,
                    $datasetName
                );

                $tableName = $this->schemaRepository->fetchSqlTableName(
                    $sqlType,
                    $datasetSchema
                );

                FileMethods::putContents(
                    $this->schemaRepository->fetchSqlTablePath(
                        $sqlType,
                        $datasetSchema
                    ),
                    $sqlTableGenerator->generateTable(
                        $datasetSchema,
                        $tableName
                    )
                );

                FileMethods::putContents(
                    $this->schemaRepository->fetchSqlTablePath(
                        $sqlType,
                        $datasetSchema,
                        '_LOAD'
                    ),
                    $sqlTableGenerator->generateTable(
                        $datasetSchema,
                        $tableName . '_LOAD'
                    )
                );
            } catch (\Throwable $t) {
                $this->logError(
                    $input,
                    new \RuntimeException(
                        "Error generating SQL table: '{$datasetName}', '{$tableName}'",
                        0,
                        $t
                    )
                );
            }
        }

        return static::SUCCESS;
    }
}
