<?php

namespace App\Utilities;

use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Pre-process a DOCX file to convert math formulas (OMML) to LaTeX text
 * BEFORE PHPWord loads it.
 *
 * This prevents phpoffice/math from crashing on complex math expressions
 * by replacing m:oMath/m:oMathPara nodes with regular w:r/w:t text runs
 * containing LaTeX notation wrapped in $...$ delimiters.
 */
class DocxMathPreprocessor
{
    private const NS_MATH = 'http://schemas.openxmlformats.org/officeDocument/2006/math';
    private const NS_WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Pre-process a DOCX file: convert math to LaTeX text.
     *
     * Returns the path to the preprocessed file (a temp copy).
     * Caller is responsible for deleting the temp file after use.
     */
    public function preprocess(string $docxPath): string
    {
        $tempPath = $docxPath . '.mathfix.docx';
        copy($docxPath, $tempPath);

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            return $docxPath; // fallback to original
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            return $docxPath;
        }

        $processedXml = $this->processMathInXml($xml);

        if ($processedXml !== $xml) {
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $processedXml);
        }

        $zip->close();

        return $tempPath;
    }

    /**
     * Process the document XML: find and replace all math nodes.
     */
    private function processMathInXml(string $xml): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        $prev = libxml_use_internal_errors(true);
        $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS_MATH);
        $xpath->registerNamespace('w', self::NS_WORD);

        $converter = new OmmlToLatex();
        $changed = false;

        // Process m:oMathPara first (display math — block level)
        $mathParaNodes = $xpath->query('//m:oMathPara');
        if ($mathParaNodes && $mathParaNodes->length > 0) {
            $changed = true;
            foreach (iterator_to_array($mathParaNodes) as $mathPara) {
                $latex = $this->convertNodeToLatex($converter, $mathPara, $dom);
                if ($latex !== '') {
                    $replacement = $this->createTextRun($dom, '$$ ' . $latex . ' $$');
                    $mathPara->parentNode->replaceChild($replacement, $mathPara);
                }
            }
        }

        // Process remaining m:oMath (inline math)
        // Re-query because DOM has changed
        $mathNodes = $xpath->query('//m:oMath');
        if ($mathNodes && $mathNodes->length > 0) {
            $changed = true;
            foreach (iterator_to_array($mathNodes) as $mathNode) {
                $latex = $this->convertNodeToLatex($converter, $mathNode, $dom);
                if ($latex !== '') {
                    $replacement = $this->createTextRun($dom, '$ ' . $latex . ' $');
                    $mathNode->parentNode->replaceChild($replacement, $mathNode);
                }
            }
        }

        if (!$changed) {
            return $xml;
        }

        return $dom->saveXML();
    }

    /**
     * Convert a math node to LaTeX using OmmlToLatex converter.
     */
    private function convertNodeToLatex(OmmlToLatex $converter, \DOMNode $node, DOMDocument $dom): string
    {
        try {
            $mathXml = $dom->saveXML($node);
            return $converter->convert($mathXml);
        } catch (\Throwable $e) {
            // If conversion fails, try to extract plain text as fallback
            return trim($node->textContent);
        }
    }

    /**
     * Create a w:r/w:t element containing the given text.
     */
    private function createTextRun(DOMDocument $dom, string $text): \DOMElement
    {
        $r = $dom->createElementNS(self::NS_WORD, 'w:r');
        $t = $dom->createElementNS(self::NS_WORD, 'w:t');
        $t->setAttribute('xml:space', 'preserve');
        $t->appendChild($dom->createTextNode($text));
        $r->appendChild($t);
        return $r;
    }
}
