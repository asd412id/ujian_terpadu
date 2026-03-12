<?php

namespace App\Utilities;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Convert Office MathML (OMML) XML to LaTeX notation.
 *
 * Pure PHP implementation — no XSL extension required.
 * Handles the most common OMML elements found in Word documents.
 */
class OmmlToLatex
{
    private DOMXPath $xpath;

    private const NS_MATH = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    /**
     * Convert an OMML XML string (m:oMath or m:oMathPara) to LaTeX.
     */
    public function convert(string $ommlXml): string
    {
        $dom = new DOMDocument();

        // Ensure namespace declarations exist
        if (!str_contains($ommlXml, 'xmlns:m=')) {
            $ommlXml = str_replace('<m:', '<m:', $ommlXml); // noop, just ensure valid
            // Wrap with namespace if not present
            $ommlXml = '<root xmlns:m="' . self::NS_MATH
                . '" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . $ommlXml . '</root>';
        }

        // Suppress warnings from possibly malformed XML
        $prev = libxml_use_internal_errors(true);
        $dom->loadXML($ommlXml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $this->xpath = new DOMXPath($dom);
        $this->xpath->registerNamespace('m', self::NS_MATH);

        // Find the math root element
        $mathNodes = $this->xpath->query('//m:oMathPara | //m:oMath');
        if (!$mathNodes || $mathNodes->length === 0) {
            return '';
        }

        $result = '';
        foreach ($mathNodes as $node) {
            $result .= $this->processNode($node);
        }

        return trim($result);
    }

    /**
     * Convert a DOMNode to LaTeX.
     */
    private function processNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return trim($node->textContent);
        }

        $localName = $node->localName ?? '';
        $ns = $node->namespaceURI ?? '';

        // Only process math namespace elements; for word namespace, skip
        if ($ns && $ns !== self::NS_MATH && $localName !== 'root') {
            return '';
        }

        return match ($localName) {
            'oMathPara' => $this->processChildren($node),
            'oMath'     => $this->processChildren($node),
            'r'         => $this->processRun($node),
            'f'         => $this->processFraction($node),
            'sSup'      => $this->processSuperscript($node),
            'sSub'      => $this->processSubscript($node),
            'sSubSup'   => $this->processSubSuperscript($node),
            'rad'       => $this->processRadical($node),
            'nary'      => $this->processNary($node),
            'd'         => $this->processDelimiter($node),
            'eqArr'     => $this->processEqArray($node),
            'func'      => $this->processFunction($node),
            'limLow'    => $this->processLimLow($node),
            'limUpp'    => $this->processLimUpp($node),
            'acc'       => $this->processAccent($node),
            'bar'       => $this->processBar($node),
            'm'         => $this->processMatrix($node),
            'groupChr'  => $this->processGroupChar($node),
            'sPre'      => $this->processPreSubSup($node),
            'borderBox' => $this->processBorderBox($node),
            'box'       => $this->processBox($node),
            // Container elements — just process children
            'e', 'num', 'den', 'sup', 'sub', 'deg', 'lim',
            'fName', 'mr' => $this->processChildren($node),
            // Properties — skip
            'rPr', 'ctrlPr', 'fPr', 'sSupPr', 'sSubPr', 'sSubSupPr',
            'radPr', 'naryPr', 'dPr', 'eqArrPr', 'funcPr', 'limLowPr',
            'limUppPr', 'accPr', 'barPr', 'mPr', 'groupChrPr', 'sPrePr',
            'borderBoxPr', 'boxPr', 'oMathParaPr' => '',
            'root' => $this->processChildren($node),
            default => $this->processChildren($node),
        };
    }

    private function processChildren(DOMNode $node): string
    {
        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= $this->processNode($child);
        }
        return $result;
    }

    /**
     * m:r — math run, contains m:t (text)
     */
    private function processRun(DOMNode $node): string
    {
        $texts = $this->xpath->query('m:t', $node);
        if ($texts && $texts->length > 0) {
            $value = '';
            foreach ($texts as $t) {
                $value .= $t->textContent;
            }
            return $this->escapeLatex($value);
        }
        return '';
    }

    /**
     * m:f — fraction: \frac{num}{den}
     */
    private function processFraction(DOMNode $node): string
    {
        $num = $this->getChildContent($node, 'm:num');
        $den = $this->getChildContent($node, 'm:den');

        // Check fraction type from properties
        $type = $this->getPropertyValue($node, 'm:fPr/m:type', 'm:val');
        if ($type === 'lin') {
            return $num . '/' . $den;
        }

        return '\\frac{' . $num . '}{' . $den . '}';
    }

    /**
     * m:sSup — superscript: base^{sup}
     */
    private function processSuperscript(DOMNode $node): string
    {
        $base = $this->getChildContent($node, 'm:e');
        $sup = $this->getChildContent($node, 'm:sup');
        return $base . '^{' . $sup . '}';
    }

    /**
     * m:sSub — subscript: base_{sub}
     */
    private function processSubscript(DOMNode $node): string
    {
        $base = $this->getChildContent($node, 'm:e');
        $sub = $this->getChildContent($node, 'm:sub');
        return $base . '_{' . $sub . '}';
    }

    /**
     * m:sSubSup — subscript+superscript: base_{sub}^{sup}
     */
    private function processSubSuperscript(DOMNode $node): string
    {
        $base = $this->getChildContent($node, 'm:e');
        $sub = $this->getChildContent($node, 'm:sub');
        $sup = $this->getChildContent($node, 'm:sup');
        return $base . '_{' . $sub . '}^{' . $sup . '}';
    }

    /**
     * m:rad — radical/root: \sqrt[deg]{e} or \sqrt{e}
     */
    private function processRadical(DOMNode $node): string
    {
        $deg = $this->getChildContent($node, 'm:deg');
        $base = $this->getChildContent($node, 'm:e');

        // Check degHide property
        $degHide = $this->getPropertyValue($node, 'm:radPr/m:degHide', 'm:val');
        if ($degHide === '1' || empty($deg)) {
            return '\\sqrt{' . $base . '}';
        }
        return '\\sqrt[' . $deg . ']{' . $base . '}';
    }

    /**
     * m:nary — n-ary operator: \sum, \prod, \int, etc.
     */
    private function processNary(DOMNode $node): string
    {
        $chr = $this->getPropertyValue($node, 'm:naryPr/m:chr', 'm:val');
        $sub = $this->getChildContent($node, 'm:sub');
        $sup = $this->getChildContent($node, 'm:sup');
        $base = $this->getChildContent($node, 'm:e');

        $operator = match ($chr) {
            '∑', null => '\\sum',
            '∏'       => '\\prod',
            '∫'       => '\\int',
            '∬'       => '\\iint',
            '∭'       => '\\iiint',
            '∮'       => '\\oint',
            '⋃'       => '\\bigcup',
            '⋂'       => '\\bigcap',
            '⋁'       => '\\bigvee',
            '⋀'       => '\\bigwedge',
            default   => '\\sum',
        };

        $result = $operator;
        if (!empty($sub)) $result .= '_{' . $sub . '}';
        if (!empty($sup)) $result .= '^{' . $sup . '}';
        $result .= '{' . $base . '}';

        return $result;
    }

    /**
     * m:d — delimiter (parentheses, brackets, braces, etc.)
     */
    private function processDelimiter(DOMNode $node): string
    {
        $begChr = $this->getPropertyValue($node, 'm:dPr/m:begChr', 'm:val') ?? '(';
        $endChr = $this->getPropertyValue($node, 'm:dPr/m:endChr', 'm:val') ?? ')';

        // Map to LaTeX delimiters
        $begLatex = $this->mapDelimiter($begChr);
        $endLatex = $this->mapDelimiter($endChr);

        // Collect all m:e children (multiple elements separated by delimiters)
        $elements = [];
        $eNodes = $this->xpath->query('m:e', $node);
        if ($eNodes) {
            foreach ($eNodes as $eNode) {
                $elements[] = $this->processNode($eNode);
            }
        }

        $sepChr = $this->getPropertyValue($node, 'm:dPr/m:sepChr', 'm:val') ?? ',';

        return '\\left' . $begLatex . implode($sepChr, $elements) . '\\right' . $endLatex;
    }

    /**
     * m:eqArr — equation array (like aligned equations)
     */
    private function processEqArray(DOMNode $node): string
    {
        $rows = [];
        $eNodes = $this->xpath->query('m:e', $node);
        if ($eNodes) {
            foreach ($eNodes as $eNode) {
                $rows[] = $this->processNode($eNode);
            }
        }
        if (count($rows) === 1) {
            return $rows[0];
        }
        return '\\begin{aligned}' . implode(' \\\\ ', $rows) . '\\end{aligned}';
    }

    /**
     * m:func — function application: func(arg)
     */
    private function processFunction(DOMNode $node): string
    {
        $name = $this->getChildContent($node, 'm:fName');
        $arg = $this->getChildContent($node, 'm:e');

        // Common function names
        $funcMap = [
            'sin' => '\\sin', 'cos' => '\\cos', 'tan' => '\\tan',
            'sec' => '\\sec', 'csc' => '\\csc', 'cot' => '\\cot',
            'log' => '\\log', 'ln' => '\\ln', 'lim' => '\\lim',
            'min' => '\\min', 'max' => '\\max',
        ];

        $trimName = trim($name);
        $latexFunc = $funcMap[$trimName] ?? $trimName;

        return $latexFunc . ' ' . $arg;
    }

    /**
     * m:limLow — lower limit: base with limit below
     */
    private function processLimLow(DOMNode $node): string
    {
        $base = $this->getChildContent($node, 'm:e');
        $lim = $this->getChildContent($node, 'm:lim');
        return $base . '_{' . $lim . '}';
    }

    /**
     * m:limUpp — upper limit: base with limit above
     */
    private function processLimUpp(DOMNode $node): string
    {
        $base = $this->getChildContent($node, 'm:e');
        $lim = $this->getChildContent($node, 'm:lim');
        return $base . '^{' . $lim . '}';
    }

    /**
     * m:acc — accent: \hat, \tilde, \dot, etc.
     */
    private function processAccent(DOMNode $node): string
    {
        $chr = $this->getPropertyValue($node, 'm:accPr/m:chr', 'm:val');
        $base = $this->getChildContent($node, 'm:e');

        $accent = match ($chr) {
            '̂', '^'  => '\\hat',
            '̃', '~'  => '\\tilde',
            '̇'       => '\\dot',
            '̈'       => '\\ddot',
            '⃗', '→'  => '\\vec',
            '̄', '‾'  => '\\bar',
            '˘'       => '\\breve',
            'ˇ'       => '\\check',
            default   => '\\hat',
        };

        return $accent . '{' . $base . '}';
    }

    /**
     * m:bar — overbar or underbar
     */
    private function processBar(DOMNode $node): string
    {
        $pos = $this->getPropertyValue($node, 'm:barPr/m:pos', 'm:val');
        $base = $this->getChildContent($node, 'm:e');

        if ($pos === 'bot') {
            return '\\underline{' . $base . '}';
        }
        return '\\overline{' . $base . '}';
    }

    /**
     * m:m — matrix
     */
    private function processMatrix(DOMNode $node): string
    {
        $rows = [];
        $mrNodes = $this->xpath->query('m:mr', $node);
        if ($mrNodes) {
            foreach ($mrNodes as $mrNode) {
                $cells = [];
                $eNodes = $this->xpath->query('m:e', $mrNode);
                if ($eNodes) {
                    foreach ($eNodes as $eNode) {
                        $cells[] = $this->processNode($eNode);
                    }
                }
                $rows[] = implode(' & ', $cells);
            }
        }

        return '\\begin{matrix}' . implode(' \\\\ ', $rows) . '\\end{matrix}';
    }

    /**
     * m:groupChr — group character (brace under/over)
     */
    private function processGroupChar(DOMNode $node): string
    {
        $chr = $this->getPropertyValue($node, 'm:groupChrPr/m:chr', 'm:val') ?? '⏟';
        $pos = $this->getPropertyValue($node, 'm:groupChrPr/m:pos', 'm:val') ?? 'bot';
        $base = $this->getChildContent($node, 'm:e');

        if ($pos === 'top') {
            return '\\overbrace{' . $base . '}';
        }
        return '\\underbrace{' . $base . '}';
    }

    /**
     * m:sPre — pre-sub/superscript (e.g., isotopes)
     */
    private function processPreSubSup(DOMNode $node): string
    {
        $sub = $this->getChildContent($node, 'm:sub');
        $sup = $this->getChildContent($node, 'm:sup');
        $base = $this->getChildContent($node, 'm:e');

        return '{}_{' . $sub . '}^{' . $sup . '}' . $base;
    }

    /**
     * m:borderBox — bordered box (just render content)
     */
    private function processBorderBox(DOMNode $node): string
    {
        return '\\boxed{' . $this->getChildContent($node, 'm:e') . '}';
    }

    /**
     * m:box — box (just render content)
     */
    private function processBox(DOMNode $node): string
    {
        return $this->getChildContent($node, 'm:e');
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Get processed LaTeX content of a specific child element.
     */
    private function getChildContent(DOMNode $parent, string $xpath): string
    {
        $nodes = $this->xpath->query($xpath, $parent);
        if ($nodes && $nodes->length > 0) {
            return $this->processNode($nodes->item(0));
        }
        return '';
    }

    /**
     * Get an attribute value from a property element.
     */
    private function getPropertyValue(DOMNode $parent, string $path, string $attr): ?string
    {
        $nodes = $this->xpath->query($path, $parent);
        if ($nodes && $nodes->length > 0) {
            $element = $nodes->item(0);
            // Try m:val attribute
            $val = $element->attributes?->getNamedItemNS(self::NS_MATH, 'val');
            if ($val) {
                return $val->nodeValue;
            }
            // Try plain val attribute
            $val = $element->attributes?->getNamedItem('val');
            if ($val) {
                return $val->nodeValue;
            }
            // Try w:val attribute
            $val = $element->attributes?->getNamedItemNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'val'
            );
            if ($val) {
                return $val->nodeValue;
            }
        }
        return null;
    }

    /**
     * Map Unicode delimiter characters to LaTeX.
     */
    private function mapDelimiter(string $char): string
    {
        return match ($char) {
            '('       => '(',
            ')'       => ')',
            '['       => '[',
            ']'       => ']',
            '{'       => '\\{',
            '}'       => '\\}',
            '|'       => '|',
            '‖', '∥'  => '\\|',
            '⌈'       => '\\lceil',
            '⌉'       => '\\rceil',
            '⌊'       => '\\lfloor',
            '⌋'       => '\\rfloor',
            '⟨', '〈'  => '\\langle',
            '⟩', '〉'  => '\\rangle',
            ''        => '.',
            default   => $char,
        };
    }

    /**
     * Escape special LaTeX characters in text, but preserve known LaTeX commands.
     */
    private function escapeLatex(string $text): string
    {
        // Don't escape if it's already a LaTeX command or operator
        if (str_starts_with($text, '\\')) {
            return $text;
        }

        // Map common Unicode math symbols to LaTeX
        $unicodeMap = [
            '×' => '\\times',
            '÷' => '\\div',
            '±' => '\\pm',
            '∓' => '\\mp',
            '≤' => '\\leq',
            '≥' => '\\geq',
            '≠' => '\\neq',
            '≈' => '\\approx',
            '∞' => '\\infty',
            '∈' => '\\in',
            '∉' => '\\notin',
            '⊂' => '\\subset',
            '⊃' => '\\supset',
            '⊆' => '\\subseteq',
            '⊇' => '\\supseteq',
            '∪' => '\\cup',
            '∩' => '\\cap',
            '∅' => '\\emptyset',
            '∀' => '\\forall',
            '∃' => '\\exists',
            '∄' => '\\nexists',
            '∧' => '\\land',
            '∨' => '\\lor',
            '¬' => '\\neg',
            '⟹' => '\\Rightarrow',
            '⟸' => '\\Leftarrow',
            '⟺' => '\\Leftrightarrow',
            '→' => '\\to',
            '←' => '\\leftarrow',
            '↔' => '\\leftrightarrow',
            'α' => '\\alpha',
            'β' => '\\beta',
            'γ' => '\\gamma',
            'δ' => '\\delta',
            'ε' => '\\epsilon',
            'ζ' => '\\zeta',
            'η' => '\\eta',
            'θ' => '\\theta',
            'ι' => '\\iota',
            'κ' => '\\kappa',
            'λ' => '\\lambda',
            'μ' => '\\mu',
            'ν' => '\\nu',
            'ξ' => '\\xi',
            'π' => '\\pi',
            'ρ' => '\\rho',
            'σ' => '\\sigma',
            'τ' => '\\tau',
            'υ' => '\\upsilon',
            'φ' => '\\phi',
            'χ' => '\\chi',
            'ψ' => '\\psi',
            'ω' => '\\omega',
            'Γ' => '\\Gamma',
            'Δ' => '\\Delta',
            'Θ' => '\\Theta',
            'Λ' => '\\Lambda',
            'Ξ' => '\\Xi',
            'Π' => '\\Pi',
            'Σ' => '\\Sigma',
            'Φ' => '\\Phi',
            'Ψ' => '\\Psi',
            'Ω' => '\\Omega',
            '∗' => '*',
            '·' => '\\cdot',
            '…' => '\\ldots',
            '⋯' => '\\cdots',
            '⋮' => '\\vdots',
            '⋱' => '\\ddots',
        ];

        $result = '';
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1);
            if (isset($unicodeMap[$char])) {
                $result .= $unicodeMap[$char] . ' ';
            } else {
                $result .= $char;
            }
        }

        return trim($result);
    }
}
