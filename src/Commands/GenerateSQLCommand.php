<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Commands;

use GSU\D2L\DataHub\Schema\CLI\Actions\GenerateSQLAction;
use GSU\D2L\DataHub\Schema\Model\DatasetSchemaType;
use mjfklib\Console\Command\Command;
use mjfklib\Utils\FileMethods;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSQLCommand extends Command
{
    /**
     * @param GenerateSQLAction $generateSQL
     */
    public function __construct(protected GenerateSQLAction $generateSQL)
    {
        parent::__construct(false, false);
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument(
            name: 'type',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Dataset type. Valid options are "ADS" and "BDS"'
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
        $type = $input->getArgument('type');
        $type = is_string($type)
            ? DatasetSchemaType::getType($type)
            : throw new \RuntimeException('Invalid dataset type');

        if ($input->getOption('purge') === true) {
            FileMethods::deleteFiles("{$this->generateSQL->getTableDir()}/*.sql");
        }

        $tableMap = $this->generateSQL->getTableMap();
        foreach ($tableMap as $datasetName => $tableName) {
            $this->generateSQL->generateTable(
                $type,
                $datasetName,
                $tableName
            );
        }

        return static::SUCCESS;
    }
}
