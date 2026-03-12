<?php

namespace App\Utilities;

use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Extract image crop metadata from DOCX files.
 *
 * Word stores cropped images as the full original image + crop metadata
 * in the XML (a:srcRect element inside pic:blipFill). PHPWord ignores
 * this metadata entirely, so we parse it ourselves.
 *
 * Returns a map of image filename => crop percentages.
 */
class DocxImageCropExtractor
{
    private const NS_WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Extract crop data from a DOCX file.
     *
     * @return array<string, array{l: float, t: float, r: float, b: float}>
     *   Map of "word/media/imageN.ext" => crop percentages (0-100).
     *   l=left, t=top, r=right, b=bottom percentage to crop off.
     */
    public function extract(string $docxPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return [];
        }

        // Build relationship map: rId => target path
        $relMap = $this->parseRelationships($zip);

        // Parse document.xml for crop data
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return [];
        }

        return $this->parseCropData($xml, $relMap);
    }

    /**
     * Parse word/_rels/document.xml.rels to build rId => target map.
     */
    private function parseRelationships(ZipArchive $zip): array
    {
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        if ($relsXml === false) return [];

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadXML($relsXml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $map = [];
        $elements = $dom->getElementsByTagName('Relationship');
        foreach ($elements as $el) {
            $id = $el->getAttribute('Id');
            $target = $el->getAttribute('Target');
            if ($id && $target) {
                // Normalize target path to include "word/" prefix if relative
                if (!str_starts_with($target, '/') && !str_starts_with($target, 'http')) {
                    $target = 'word/' . $target;
                }
                $map[$id] = $target;
            }
        }

        return $map;
    }

    /**
     * Parse document.xml to find crop data for each image.
     */
    private function parseCropData(string $xml, array $relMap): array
    {
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('a', self::NS_DRAWING);
        $xpath->registerNamespace('pic', self::NS_PIC);
        $xpath->registerNamespace('r', self::NS_R);

        $crops = [];

        // Find all pic:blipFill elements (contains both image ref and crop)
        $blipFills = $xpath->query('//pic:blipFill');
        if (!$blipFills) return [];

        foreach ($blipFills as $blipFill) {
            // Get image reference: a:blip r:embed="rIdX"
            $blip = $xpath->query('a:blip', $blipFill);
            if (!$blip || $blip->length === 0) continue;

            $rId = $blip->item(0)->getAttributeNS(self::NS_REL, 'embed');
            if (empty($rId) || !isset($relMap[$rId])) continue;

            $imagePath = $relMap[$rId];

            // Get crop data: a:srcRect
            $srcRect = $xpath->query('a:srcRect', $blipFill);
            if (!$srcRect || $srcRect->length === 0) continue;

            $rect = $srcRect->item(0);
            $l = $this->parsePercentage($rect->getAttribute('l'));
            $t = $this->parsePercentage($rect->getAttribute('t'));
            $r = $this->parsePercentage($rect->getAttribute('r'));
            $b = $this->parsePercentage($rect->getAttribute('b'));

            // Only add if there's actual cropping
            if ($l > 0 || $t > 0 || $r > 0 || $b > 0) {
                $crops[$imagePath] = [
                    'l' => $l,
                    't' => $t,
                    'r' => $r,
                    'b' => $b,
                ];
            }
        }

        return $crops;
    }

    /**
     * Parse Office percentage value (1/1000th of a percent) to float percentage.
     *
     * Word stores crop values as integers in 1/1000th percent units.
     * e.g. 25000 = 25%, 50000 = 50%
     */
    private function parsePercentage(string $value): float
    {
        if (empty($value)) return 0.0;

        $num = (int) $value;

        // Values are in 1/1000th of a percent
        return $num / 1000.0;
    }
}
