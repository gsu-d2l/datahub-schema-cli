<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Model;

use GSU\D2L\DataHub\Schema\Model\DatasetSchemaType;
use mjfklib\Utils\ArrayValue;

class SchemaModule
{
    /**
     * @param mixed $values
     * @return self
     */
    public static function create(mixed $values): self
    {
        $values = ArrayValue::convertToArray($values);
        return new self(
            modulesDir: ArrayValue::getString($values, 'modulesDir'),
            type: DatasetSchemaType::getType(ArrayValue::getStringNull($values, 'type') ?? DatasetSchemaType::BDS),
            name: ArrayValue::getString($values, 'name'),
            url: ArrayValue::getString($values, 'url'),
            datasets: ArrayValue::getStringArray($values, 'datasets')
        );
    }


    /**
     * @param string $name
     * @return string
     */
    public static function getName(string $name): string
    {
        return str_replace(' ', '_', strtoupper($name));
    }


    /**
     * @param string $modulesDir
     * @param DatasetSchemaType $type
     * @param string $name
     * @param string $url
     * @param string[] $datasets
     */
    public function __construct(
        public string $modulesDir,
        public DatasetSchemaType $type,
        public string $name,
        public string $url,
        public array $datasets
    ) {
    }


    /**
     * @return string
     */
    public function getSimpleName(): string
    {
        return self::getName($this->name);
    }


    /**
     * @return string
     */
    public function getContentsPath(): string
    {
        return sprintf('%s/%s.html', $this->modulesDir, $this->getSimpleName());
    }
}
