<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Actions;

use GSU\D2L\DataHub\Schema\CLI\Model\DatasetNodes;
use GSU\D2L\DataHub\Schema\CLI\Model\SchemaModule;
use GSU\D2L\DataHub\Schema\CLI\Utils\XMLMethods;
use GSU\D2L\DataHub\Schema\Model\DatasetSchema;
use GSU\D2L\DataHub\Schema\Model\ColumnSchema;
use mjfklib\Logger\LoggerAwareTrait;
use mjfklib\Utils\FileMethods;
use Psr\Log\LoggerAwareInterface;

class GenerateSchemaAction implements LoggerAwareInterface
{
    use LoggerAwareTrait;


    /** @var array<int,string> */
    private const ADS_COLUMN_MAP = [
        0 => 'versionHistory',
        1 => 'name',
        2 => 'description',
        3 => 'canBeNull',
    ];


    /** @var array<int,string> */
    private const BDS_COLUMN_MAP = [
        0 => 'versionHistory',
        1 => 'name',
        2 => 'description',
        3 => 'type',
        4 => 'size',
        5 => 'key'
    ];


    /** @var string[] */
    private const INVALID_H2_VALUES = [
        "Entity Relationship Diagram",
        "Deleting Outcome Objects",
        "Sample join"
    ];


    /**
     * @param SchemaModule $module
     * @return DatasetSchema[]
     */
    public function execute(SchemaModule $module): array
    {
        $document = XMLMethods::loadDocument($module->getContentsPath());
        $mainContent = $this->findMainContent($document);
        $datasetNodes = $this->collectDatasetNodes($mainContent);
        $datasets = $this->buildDatasetList($module, $datasetNodes);
        $this->minifyContents($module, $document, $datasetNodes);

        return $datasets;
    }


    /**
     * @param \DOMDocument $document
     * @return \DOMNode
     */
    private function findMainContent(\DOMDocument $document): \DOMNode
    {
        // Collect all nodes at path '#fallbackPageContent > main > section > div'
        $nodes = XMLMethods::findChildrenByName(
            XMLMethods::findChildByName(
                XMLMethods::findChildByName(
                    XMLMethods::findById(
                        $document,
                        'fallbackPageContent'
                    ),
                    'main'
                ),
                'section'
            ),
            'div'
        );

        // Look for <div> with CSS class 'mainColumn'
        foreach ($nodes as $node) {
            if ($node->attributes !== null) {
                foreach ($node->attributes as $name => $attr) {
                    if (
                        $name === "class"
                        && $attr instanceof \DOMAttr
                        && str_contains($attr->nodeValue ?? '', "mainColumn")
                    ) {
                        // Return first <article> child node
                        return XMLMethods::findChildByName(
                            $node,
                            'article'
                        );
                    }
                }
            }
        }

        throw new \RuntimeException("Unable to find main content node in document");
    }


    /**
     * @param \DOMNode $mainContent
     * @return DatasetNodes[]
     */
    private function collectDatasetNodes(\DOMNode $mainContent): array
    {
        $datasetNodesList = [];
        $current = null;

        // Foreach child node of main content node
        for ($idx = 0; $idx < $mainContent->childNodes->length; $idx++) {
            $item = $mainContent->childNodes->item($idx);
            if (!$item instanceof \DOMNode || $item->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /**
             * The dataset documentation has the following pattern:
             *   1. An <h2> node that contains the dataset name
             *   2. Any number of <p> nodes that contain the dataset description
             *   3. A <table> node with the dataset column definitions
             */
            switch ($item->nodeName) {
                case 'h2':
                    if ($this->checkIfValidH2($item)) {
                        if ($current === null) {
                            $current = ['h2' => $item, 'p' => []];
                        } else {
                            $current['h2'] = $item;
                        }
                    }
                    break;
                case 'p':
                    if (is_array($current)) {
                        $current['p'][] = $item;
                    }
                    break;
                case 'table':
                    if (is_array($current)) {
                        $datasetNodesList[] = new DatasetNodes(
                            $current['h2'],
                            $current['p'],
                            $item
                        );
                        $current = null;
                    }
                    break;
            }
        }

        return $datasetNodesList;
    }


    /**
     * @param SchemaModule $module
     * @param \DOMDocument $document
     * @param DatasetNodes[] $datasetNodes
     * @return void
     */
    private function minifyContents(
        SchemaModule $module,
        \DOMDocument $document,
        array $datasetNodes
    ): void {
        $contents = [];
        foreach ($datasetNodes as $datasetNode) {
            $contents[] = $document->saveHTML($datasetNode->h2);
            foreach ($datasetNode->p as $p) {
                $contents[] = $document->saveHTML($p);
            }
            $contents[] = $document->saveHTML($datasetNode->table);
        }

        FileMethods::putContents(
            $module->getContentsPath(),
            implode("\r\n", [
                '<!DOCTYPE html>',
                '<html lang="en">',
                '<body>',
                '<div id="fallbackPageContent">',
                '<main>',
                '<section>',
                '<div class="mainColumn">',
                '<article>',
                ...$contents,
                '</article>',
                '</div>',
                '</section>',
                '</main>',
                '</div>',
                '</body>',
                '</html>'
            ])
        );
    }


    /**
     * @param SchemaModule $module
     * @param DatasetNodes[] $datasetNodesList
     * @return DatasetSchema[]
     */
    private function buildDatasetList(
        SchemaModule $module,
        array $datasetNodesList
    ): array {
        $datasets = [];
        foreach ($datasetNodesList as $datasetNodes) {
            $name = XMLMethods::getCleanString($datasetNodes->h2);

            // Skip any definitions that aren't in the module list
            if (!in_array($name, $module->datasets, true)) {
                $this->logger?->notice("Dataset '{$name}' is not listed in module '{$module->name}'; Skipping");
                continue;
            }

            $datasets[] = new DatasetSchema(
                type: $module->type,
                name: $name,
                url: $this->getDatasetURL($module->url, $name),
                description: $this->getDatasetDescription($datasetNodes->p),
                columns: $this->getDatasetColumns($datasetNodes->table)
            );
        }

        return $datasets;
    }


    /**
     * @param \DOMNode $item
     * @return bool
     */
    private function checkIfValidH2(\DOMNode $item): bool
    {
        $itemValue = XMLMethods::getCleanString($item);
        foreach (self::INVALID_H2_VALUES as $invalidValue) {
            if (str_contains($itemValue, $invalidValue)) {
                return false;
            }
        }
        return true;
    }


    /**
     * @param string $url
     * @param string $name
     * @return string
     */
    private function getDatasetURL(
        string $url,
        string $name
    ): string {
        return $url . '#' . str_replace(' ', '-', strtolower($name));
    }


    /**
     * @param \DOMNode[] $p
     * @return string
     */
    private function getDatasetDescription(array $p): string
    {
        return implode(" ", array_filter(
            array_map(
                fn (\DOMNode $node) => XMLMethods::getCleanString($node),
                $p
            ),
            fn (string $v): bool => !in_array(
                strtolower($v),
                ['', 'about', 'returned Fields', 'available Filters'],
                true
            )
        ));
    }


    /**
     * @param \DOMNode $table
     * @return ColumnSchema[]
     */
    private function getDatasetColumns(\DOMNode $table): array
    {
        $columns = [];

        $tableRows = XMLMethods::findChildrenByName(
            XMLMethods::findChildByName($table, 'tbody'),
            'tr'
        );

        foreach ($tableRows as $tableRow) {
            $tableRowValues = [];

            $tableCells = XMLMethods::findChildrenByName($tableRow, 'td');

            $columnMap = match (count($tableCells)) {
                4 => self::ADS_COLUMN_MAP,
                6 => self::BDS_COLUMN_MAP,
                default => throw new \RuntimeException("Invalid dataset type")
            };

            foreach ($tableCells as $idx => $tableCell) {
                $p = XMLMethods::findChildrenByName($tableCell, 'p');
                $tableRowValues[$columnMap[$idx]] = implode(" ", array_filter(
                    array_map(
                        fn (\DOMNode $n) => XMLMethods::getCleanString($n),
                        (count($p) > 0) ? $p : [$tableCell]
                    ),
                    fn ($p) => $p !== ''
                ));
            }

            $columns[] = ColumnSchema::create($tableRowValues);
        }

        return $columns;
    }
}
