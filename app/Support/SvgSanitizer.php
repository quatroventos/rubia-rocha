<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Higieniza SVG enviado pelo CMS por lista de permissão: só elementos e atributos de
 * desenho sobrevivem. Remove script, foreignObject, eventos on*, referências externas
 * (href, url(), @import) e qualquer DOCTYPE/entidade.
 */
class SvgSanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath', 'defs', 'lineargradient', 'radialgradient', 'stop',
        'clippath', 'mask', 'pattern', 'symbol', 'use', 'title', 'desc', 'style', 'marker',
        'filter', 'fegaussianblur', 'feoffset', 'feblend', 'fecolormatrix', 'fecomposite',
        'feflood', 'femerge', 'femergenode', 'femorphology', 'fedropshadow',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'style', 'viewbox', 'width', 'height', 'x', 'y', 'x1', 'x2', 'y1', 'y2',
        'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'transform', 'fill', 'fill-opacity',
        'fill-rule', 'clip-rule', 'clip-path', 'clippathunits', 'mask', 'maskunits',
        'maskcontentunits', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'opacity', 'color', 'display', 'visibility', 'offset', 'stop-color', 'stop-opacity',
        'gradientunits', 'gradienttransform', 'spreadmethod', 'fx', 'fy', 'fr',
        'patternunits', 'patterncontentunits', 'patterntransform', 'preserveaspectratio',
        'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing',
        'text-anchor', 'dominant-baseline', 'dx', 'dy', 'version', 'xml:space',
        'enable-background', 'filter', 'filterunits', 'primitiveunits', 'in', 'in2',
        'result', 'stddeviation', 'mode', 'type', 'values', 'operator', 'k1', 'k2', 'k3',
        'k4', 'flood-color', 'flood-opacity', 'radius', 'markerwidth', 'markerheight',
        'refx', 'refy', 'orient', 'markerunits', 'marker-start', 'marker-mid', 'marker-end',
        'href', 'xlink:href', 'mix-blend-mode', 'isolation', 'vector-effect',
    ];

    public function clean(string $svg): ?string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if (! $loaded || ! $root || strtolower($root->localName) !== 'svg') {
            return null;
        }

        $this->cleanNode($root);

        $output = $document->saveXML($root);

        return $output === false ? null : $output;
    }

    private function cleanNode(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = strtolower($attribute->nodeName);

            if (str_starts_with($name, 'xmlns')) {
                continue;
            }

            if (! in_array($name, self::ATTRIBUTES, true) || ! $this->safeValue($name, $attribute->value)) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            if ($name === 'style') {
                $attribute->value = $this->cleanCss($attribute->value);
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            /** @var DOMNode $child */
            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                    $element->removeChild($child);

                    continue;
                }

                if (strtolower($child->localName) === 'style') {
                    $child->textContent = $this->cleanCss($child->textContent);

                    continue;
                }

                $this->cleanNode($child);

                continue;
            }

            if (! in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $element->removeChild($child);
            }
        }
    }

    private function safeValue(string $name, string $value): bool
    {
        $normalized = strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', $value));

        if ($name === 'href' || $name === 'xlink:href') {
            return str_starts_with($normalized, '#');
        }

        return ! str_contains($normalized, 'javascript:') && ! str_contains($normalized, 'data:') && ! str_contains($normalized, 'expression(');
    }

    private function cleanCss(string $css): string
    {
        $css = (string) preg_replace('/@import[^;]*;?/i', '', $css);
        $css = (string) preg_replace('/expression\s*\(/i', '', $css);
        $css = (string) preg_replace('/javascript\s*:/i', '', $css);

        return (string) preg_replace_callback(
            '/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i',
            static fn (array $match): string => str_starts_with(trim($match[2]), '#') ? $match[0] : 'none',
            $css,
        );
    }
}
