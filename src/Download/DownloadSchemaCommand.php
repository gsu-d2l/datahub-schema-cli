<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Download;

use GSU\D2L\DataHub\Schema\Model\DatasetModule;
use GSU\D2L\DataHub\Schema\SchemaRepositoryInterface;
use mjfklib\Console\Command\Command;
use mjfklib\Utils\FileMethods;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'schema:download')]
final class DownloadSchemaCommand extends Command
{
    public function __construct(
        private SchemaRepositoryInterface $schemaRepository,
        private DatasetModuleDownloader $moduleDownloader,
        private DatasetSchemaBuilder $schemaBuilder,
        private DatasetModuleContentBuilder $moduleContentBuilder,
    ) {
        parent::__construct(false, true);
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
            $this->schemaRepository->cleanUpModules();
            $this->schemaRepository->cleanUpDatasets();
        }

        $modules = $this->schemaRepository->listModules();
        foreach ($modules as $moduleName) {
            try {
                list($module, $newModule) = $this->getModule($moduleName);

                $datasets = $this->schemaBuilder->buildSchema($module);

                if ($newModule) {
                    FileMethods::putContents(
                        $module->contentsPath,
                        $this->moduleContentBuilder->buildContent($datasets)
                    );
                }

                foreach ($module->datasets as $datasetName) {
                    $found = false;
                    foreach ($datasets as $dataset) {
                        if ($dataset->name === $datasetName) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $this->logger?->notice(
                            "Dataset '{$datasetName}' is listed in module '{$module->name}' but not found in output"
                        );
                    }
                }

                foreach ($datasets as $dataset) {
                    try {
                        $this->schemaRepository->storeDataset($dataset);
                    } catch (\Throwable $t) {
                        throw new \RuntimeException(
                            "Error storing dataset: {$dataset->name}",
                            0,
                            $t
                        );
                    }
                }
            } catch (\Throwable $t) {
                $this->logError(
                    $input,
                    new \RuntimeException(
                        "Error downloading module: {$moduleName}",
                        0,
                        $t
                    )
                );
            }
        }

        return static::SUCCESS;
    }


    /**
     * @param string $moduleName
     * @return array{DatasetModule,bool}
     */
    private function getModule(string $moduleName): array
    {
        $module = $this->schemaRepository->fetchModule($moduleName);

        $newModule = !is_file($module->contentsPath);
        if ($newModule) {
            $this->moduleDownloader->download($module);
        }
        if (!is_file($module->contentsPath)) {
            throw new \RuntimeException("Module contents not found: {$module->contentsPath}");
        }

        return [$module, $newModule];
    }
}
