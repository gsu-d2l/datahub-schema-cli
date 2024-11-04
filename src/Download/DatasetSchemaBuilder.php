<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Download;

use GSU\D2L\DataHub\Schema\CLI\Download\Model\DatasetNodes;
use GSU\D2L\DataHub\Schema\CLI\Download\Utils\XMLMethods;
use GSU\D2L\DataHub\Schema\Model\DatasetSchema;
use GSU\D2L\DataHub\Schema\Model\ColumnSchema;
use GSU\D2L\DataHub\Schema\Model\DatasetModule;
use mjfklib\Logger\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;

final class DatasetSchemaBuilder implements LoggerAwareInterface
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
     * @param DatasetModule $module
     * @return DatasetSchema[]
     */
    public static function buildSchema(DatasetModule $module): array
    {
        return (new self($module))
            ->loadDocument()
            ->findMainNode()
            ->collectDatasetNodes()
            ->buildDatasetList();
    }


    private \DOMDocument $document;
    private \DOMNode $mainNode;
    /** @var DatasetNodes[] $datasetNodes */
    private array $datasetNodes;


    /**
     * @param DatasetModule $module
     */
    public function __construct(private DatasetModule $module)
    {
    }


    /**
     * @return self
     */
    public function loadDocument(): self
    {
        $this->document = XMLMethods::loadDocument($this->module->contentsPath);
        return $this;
    }


    /**
     * @return self
     */
    public function findMainNode(): self
    {
        // Collect all nodes at path '#fallbackPageContent > main > section > div'
        $nodes = XMLMethods::findChildrenByName(
            XMLMethods::findChildByName(
                XMLMethods::findChildByName(
                    XMLMethods::findById(
                        $this->document,
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
                        $this->mainNode = XMLMethods::findChildByName(
                            $node,
                            'article'
                        );
                        return $this;
                    }
                }
            }
        }

        throw new \RuntimeException("Unable to find main content node in document");
    }


    /**
     * @return self
     */
    public function collectDatasetNodes(): self
    {
        $this->datasetNodes = [];
        $current = null;

        // Foreach child node of main content node
        for ($idx = 0; $idx < $this->mainNode->childNodes->length; $idx++) {
            $item = $this->mainNode->childNodes->item($idx);
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
                    /** @var array{h2:\DOMNode,p:\DOMNode[]}|null $current */
                    if (is_array($current) && count(XMLMethods::findChildrenByName($item, 'thead')) > 0) {
                        $this->datasetNodes[] = new DatasetNodes(
                            $current['h2'],
                            $current['p'],
                            $item
                        );
                        $current = null;
                    }
                    break;
            }
        }

        return $this;
    }


    /**
     * @return DatasetSchema[]
     */
    public function buildDatasetList(): array
    {
        $datasets = [];
        foreach ($this->datasetNodes as $datasetNodes) {
            $name = XMLMethods::getCleanString($datasetNodes->h2);
            $datasets[] = new DatasetSchema(
                type: $this->module->type,
                name: $name,
                url: $this->module->url . '#' . str_replace(' ', '-', strtolower($name)),
                description: implode(" ", array_filter(
                    array_map(
                        fn(\DOMNode $node) => XMLMethods::getCleanString($node),
                        $datasetNodes->p
                    ),
                    fn(string $v): bool => !in_array(
                        strtolower($v),
                        ['', 'about', 'returned fields', 'available filters'],
                        true
                    )
                )),
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
                        fn(\DOMNode $n) => XMLMethods::getCleanString($n),
                        (count($p) > 0) ? $p : [$tableCell]
                    ),
                    fn($p) => $p !== ''
                ));
            }

            $columns[] = ColumnSchema::create($tableRowValues);
        }

        return $columns;
    }
}
