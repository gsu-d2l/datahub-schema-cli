<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Model;

use GSU\D2L\DataHub\Schema\SchemaRepository;
use mjfklib\Container\ArrayValue;

class SchemaModuleList
{
    /**
     * @param SchemaRepository $schemaRepository
     * @return self
     */
    public static function create(SchemaRepository $schemaRepository): self
    {
        $modulesDir = realpath("{$schemaRepository->getSchemaDir()}/modules");
        if (!is_string($modulesDir) || !is_dir($modulesDir)) {
            throw new \RuntimeException("Invalid or missing modules directory");
        }

        $path = "{$modulesDir}/modules.json";
        if (!is_file($path)) {
            throw new \RuntimeException("Missing modules.json file");
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new \RuntimeException("Unable to read modules.json");
        }

        $values = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($values)) {
            throw new \RuntimeException("Unable to parse modules.json");
        }

        return new self(
            modulesDir: $modulesDir,
            version: ArrayValue::getString($values, 'version'),
            dataSetVersion: ArrayValue::getString($values, 'dataSetVersion'),
            modules: array_values(array_map(
                fn ($v) => SchemaModule::create([
                    ...(match (true) {
                        is_array($v) => $v,
                        is_object($v) => get_object_vars($v),
                        default => throw new \RuntimeException("Unable to parse modules.json")
                    }),
                    'modulesDir' => $modulesDir
                ]),
                ArrayValue::getArray($values, 'modules')
            ))
        );
    }


    /**
     * @param string $modulesDir
     * @param string $version
     * @param string $dataSetVersion
     * @param SchemaModule[] $modules
     */
    public function __construct(
        public string $modulesDir,
        public string $version,
        public string $dataSetVersion,
        public array $modules
    ) {
        $this->modules = array_column(
            array_map(
                fn(SchemaModule $m) => [$m, $m->getSimpleName()],
                $this->modules
            ),
            0,
            1
        );

        ksort($this->modules);
    }


    /**
     * @param string $name
     * @return SchemaModule
     */
    public function getModule(string $name): SchemaModule
    {
        return $this->modules[$name] ?? throw new \RuntimeException("Invalid module: {$name}");
    }
}
