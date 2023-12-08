<?php

declare(strict_types=1);

namespace GSU\D2L\DataHub\Schema\CLI\Utils;

class XMLMethods
{
    /**
     * @param string $documentFile
     * @return \DOMDocument
     */
    public static function loadDocument(string $documentFile): \DOMDocument
    {
        $contents = file_get_contents($documentFile);
        if (!is_string($contents)) {
            throw new \RuntimeException("Unable to read document");
        }

        $document = new \DOMDocument();
        if (!@$document->loadHTML($contents)) {
            throw new \RuntimeException("Unable to load document");
        }

        return $document;
    }


    /**
     * @param \DOMDocument $document
     * @param string $id
     * @return \DOMNode
     */
    public static function findById(
        \DOMDocument $document,
        string $id
    ): \DOMNode {
        $node = $document->getElementById($id);
        return ($node !== null)
            ? $node
            : throw new \RuntimeException("Element '{$id}' not found");
    }


    /**
     * @param \DOMNode $node
     * @param string $name
     * @return \DOMNode
     */
    public static function findChildByName(
        \DOMNode $node,
        string $name
    ): \DOMNode {
        $child = self::findChildrenByName($node, $name)[0] ?? null;
        return $child !== null
            ? $child
            : throw new \RuntimeException("Element '{$name}' not found");
    }


    /**
     * @param \DOMNode $node
     * @param string $name
     * @return \DOMNode[]
     */
    public static function findChildrenByName(
        \DOMNode $node,
        string $name
    ): array {
        $children = [];

        for ($idx = 0; $idx < $node->childNodes->length; $idx++) {
            $item = $node->childNodes->item($idx);

            if (
                $item instanceof \DOMNode
                && $item->nodeType === XML_ELEMENT_NODE
                && $item->nodeName === $name
            ) {
                $children[] = $item;
            }
        }

        return $children;
    }


    /**
     * @param \DOMNode|string|null $value
     * @return string
     */
    public static function getCleanString(mixed $value): string
    {
        $myValue = ($value instanceof \DOMNode ? $value->nodeValue : $value) ?? '';
        $myValue = preg_replace(
            ['/[ \t\n\r\x0B\xc2\xa0]+/u', '/\x{2019}/u'],
            [' ', "'"],
            trim($myValue, " \t\n\r\0\x0B\xc2\xa0")
        ) ?? '';

        if ($value instanceof \DOMNode && $value->ownerDocument instanceof \DOMDocument) {
            $value->nodeValue = '';
            for ($idx = 0; $idx < $value->childNodes->length; $idx++) {
                $item = $value->childNodes->item($idx);
                if ($item instanceof \DOMNode) {
                    $value->removeChild($item);
                }
            }
            $value->appendChild($value->ownerDocument->createTextNode($myValue));
        }

        return $myValue;
    }
}
