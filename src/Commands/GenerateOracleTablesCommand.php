<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Commands;

use GSU\D2L\DataHub\Schema\CLI\Actions\GenerateOracleTablesAction;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'schema:gen-oracle-tables')]
class GenerateOracleTablesCommand extends GenerateSQLCommand
{
    public function __construct(GenerateOracleTablesAction $generateSQL)
    {
        parent::__construct($generateSQL);
    }
}
