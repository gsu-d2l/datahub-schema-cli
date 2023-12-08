<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Commands;

use GSU\D2L\DataHub\Schema\CLI\Actions\GenerateMySQLTablesAction;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'schema:gen-mysql-tables')]
class GenerateMySQLTablesCommand extends GenerateSQLCommand
{
    public function __construct(GenerateMySQLTablesAction $generateSQL)
    {
        parent::__construct($generateSQL);
    }
}
