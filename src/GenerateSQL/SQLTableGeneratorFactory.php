<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\GenerateSQL;

use GSU\D2L\DataHub\Schema\Model\SQLType;

final class SQLTableGeneratorFactory
{
    /**
     * @param (callable(SQLType $sqlType):SQLTableGeneratorInterface) $factory
     */
    public function __construct(private mixed $factory)
    {
    }


    /**
     * @param SQLType $sqlType
     * @return SQLTableGeneratorInterface
     */
    public function __invoke(SQLType $sqlType): SQLTableGeneratorInterface
    {
        return ($this->factory)($sqlType);
    }
}
