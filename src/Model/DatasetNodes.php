<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Model;

class DatasetNodes
{
    /**
     * @param \DOMNode $h2
     * @param \DOMNode[] $p
     * @param \DOMNode $table
     */
    public function __construct(
        public \DOMNode $h2,
        public array $p,
        public \DOMNode $table
    ) {
    }
}
