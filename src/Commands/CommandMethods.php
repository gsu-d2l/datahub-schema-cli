<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Commands;

class CommandMethods
{
    public static function deleteFiles(string $pattern, int $flags = 0): void
    {
        $files = glob($pattern, $flags);
        if (is_array($files)) {
            array_map('unlink', array_filter($files, 'is_file'));
        }
    }
}
