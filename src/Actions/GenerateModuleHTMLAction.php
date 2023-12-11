<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Actions;

use GSU\D2L\DataHub\Schema\Model\ColumnSchema;
use GSU\D2L\DataHub\Schema\Model\DatasetSchema;

class GenerateModuleHTMLAction
{
    /**
     * @param DatasetSchema[] $datasets
     * @return string
     */
    public function execute(array $datasets): string
    {
        return implode(PHP_EOL, [
            '<!DOCTYPE html>',
            '<html lang="en">',
            '<body>',
            '  <div id="fallbackPageContent">',
            '    <main>',
            '      <section>',
            '        <div class="mainColumn">',
            '          <article>',
            ...array_merge(...array_map(
                fn($dataset) => array_map(
                    fn($v) => '            ' . $v,
                    $this->getDataset($dataset)
                ),
                $datasets
            )),
            '          </article>',
            '        </div>',
            '      </section>',
            '    </main>',
            '  </div>',
            '</body>',
            '</html>',
        ]);
    }


    /**
     * @param DatasetSchema $dataset
     * @return string[]
     */
    private function getDataset(DatasetSchema $dataset): array
    {
        return [
            "<h2>" . htmlentities($dataset->name) . "</h2>",
            "<p>" . htmlentities($dataset->description) . "</p>",
            "<table>",
            "  <thead>",
            "    <tr>",
            "      <th>Version History</th>",
            "      <th>Field</th>",
            "      <th>Description</th>",
            "      <th>Type</th>",
            "      <th>Column Size</th>",
            "      <th>Key</th>",
            "    </tr>",
            "  </thead>",
            "  <tbody>",
            ...(array_merge(...array_map(
                fn ($c) => $this->getColumn($c),
                $dataset->columns
            ))),
            "  </tbody>",
            "</table>"
        ];
    }


    /**
     * @param ColumnSchema $c
     * @return string[]
     */
    private function getColumn(ColumnSchema $c): array
    {
        $description = ($c->canBeNull === true && !str_contains(strtolower($c->description), "field can be null"))
            ? $c->description . " Field can be null."
            : $c->description;
        $key = implode(", ", array_filter([
            $c->isPrimary ? "PK" : null,
            $c->isForeign ? "FK" : null
        ]));

        return [
            "    <tr>",
            "      <td>" . htmlentities($c->versionHistory) . "</td>",
            "      <td>" . htmlentities($c->name) . "</td>",
            "      <td>" . htmlentities($description) . "</td>",
            "      <td>" . htmlentities($c->type->value) . "</td>",
            "      <td>" . htmlentities($c->size) . "</td>",
            "      <td>" . htmlentities($key) . "</td>",
            "    </tr>",
        ];
    }
}
