<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Commands;

use GSU\D2L\DataHub\Schema\CLI\Actions\DownloadModuleAction;
use GSU\D2L\DataHub\Schema\CLI\Actions\GenerateModuleHTMLAction;
use GSU\D2L\DataHub\Schema\CLI\Actions\GenerateSchemaAction;
use GSU\D2L\DataHub\Schema\CLI\Model\SchemaModuleList;
use GSU\D2L\DataHub\Schema\SchemaRepository;
use mjfklib\Console\Command\Command;
use mjfklib\Utils\FileMethods;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'schema:download')]
class DownloadSchemaCommand extends Command
{
    public function __construct(
        private SchemaModuleList $modulesList,
        private DownloadModuleAction $downloadModule,
        private GenerateSchemaAction $generateSchema,
        private GenerateModuleHTMLAction $generateModuleHTML,
        private SchemaRepository $schemaRepository
    ) {
        parent::__construct(false, false);
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption(
            name: 'force',
            shortcut: 'f',
            mode: InputOption::VALUE_NONE,
            description: 'Force download of module contents',
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
        if ($input->getOption('force') === true) {
            FileMethods::deleteFiles("{$this->modulesList->modulesDir}/*.html");
            FileMethods::deleteFiles("{$this->schemaRepository->getDatasetsDir()}/*.json");
        }

        foreach ($this->modulesList->modules as $module) {
            $newModule = !is_file($module->getContentsPath());
            if ($newModule) {
                $this->downloadModule->execute($module);
            }

            $datasets = $this->generateSchema->execute($module);
            array_map(
                fn ($dataset) => $this->schemaRepository->store($dataset),
                $datasets
            );

            if ($newModule) {
                FileMethods::putContents(
                    $module->getContentsPath(),
                    $this->generateModuleHTML->execute($datasets)
                );
            }
        }

        return static::SUCCESS;
    }
}
