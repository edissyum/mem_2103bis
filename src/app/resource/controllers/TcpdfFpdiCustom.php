<?php

namespace Resource\controllers;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\Tcpdf\Fpdi;


class TcpdfFpdiCustom extends Fpdi
{
    public $pagesList;

    public function importPage($pageNumber, $box = PageBoundaries::CROP_BOX, $groupXObject = true)
    {
        $pageId = parent::importPage($pageNumber, $box, $groupXObject);

        $links = [];
        $reader = $this->getPdfReader($this->currentReaderId);
        $parser = $reader->getParser();

        if (empty($this->pagesList)) {
            $this->readAllPages($parser);
        }

        $pageObj = $reader->getPage($pageNumber)->getPageObject();
        $annotationsObject = PdfDictionary::get(PdfType::resolve($pageObj, $parser), 'Annots');
        $annotations = PdfType::resolve($annotationsObject, $parser);
        if ($annotations->value) {
            foreach ($annotations->value as $annotationRef) {
                $annotation = PdfType::resolve($annotationRef, $parser);

                if ( PdfDictionary::get($annotation, 'Subtype')->value !== 'Link' )
                    continue;

                $a = PdfDictionary::get($annotation, 'A');

                if ( !$a || $a instanceof PdfNull )
                    continue;

                $link = PdfType::resolve($a, $parser);
                $linkType = PdfDictionary::get($link, 'S')->value;

                if (in_array($linkType, ['URI', 'GoTo']) &&
                    ($rect = PdfDictionary::get($annotation, 'Rect')) &&
                    $rect instanceof PdfArray
                ) {
                    $rect = $rect->value;

                    $links[] = [
                        $rect[0]->value,
                        $rect[1]->value,
                        $rect[2]->value - $rect[0]->value,
                        $rect[1]->value - $rect[3]->value,
                        $this->getAnnotationLink($link, $linkType)
                    ];
                }
            }
        }

        $this->importedPages[$pageId]['links'] = $links;

        return $pageId;
    }

    public function useTemplate($tpl, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false)
    {
        $size = parent::useTemplate($tpl, $x, $y, $width, $height, $adjustPageSize);

        $links = $this->importedPages[$tpl]['links'];
        $pxToU = $this->pixelsToUnits(1);
        foreach ($links as $link) {
            // When is integer, it means that is an internal link
            if (is_int($link[4])) {
                $l = $this->AddLink();
                $this->SetLink($l, 0, $link[4]);
                $link[4] = $l;
            }

            $this->Link(
                $link[0] * $pxToU,
                $this->getPageHeight() - $link[1] * $pxToU,
                $link[2] * $pxToU,
                $link[3] * $pxToU,
                $link[4]
            );
        }

        return $size;
    }

    public function readAllPages(PdfParser $parser)
    {
        $readPages = function ($kids, $count) use (&$readPages, $parser) {
            $kids = PdfArray::ensure($kids);
            $isLeaf = ($count->value === \count($kids->value));

            foreach ($kids->value as $reference) {
                $reference = PdfIndirectObjectReference::ensure($reference);

                if ($isLeaf) {
                    $this->pagesList[] = $reference;
                    continue;
                }

                $object = $parser->getIndirectObject($reference->value);
                $type = PdfDictionary::get($object->value, 'Type');

                if ($type->value === 'Pages') {
                    $readPages(PdfDictionary::get($object->value, 'Kids'), PdfDictionary::get($object->value, 'Count'));
                } else {
                    $this->pagesList[] = $object;
                }
            }
        };

        $catalog = $parser->getCatalog();
        $pages = PdfType::resolve(PdfDictionary::get($catalog, 'Pages'), $parser);
        $count = PdfType::resolve(PdfDictionary::get($pages, 'Count'), $parser);
        $kids = PdfType::resolve(PdfDictionary::get($pages, 'Kids'), $parser);
        $readPages($kids, $count);
    }

    public function getAnnotationLink(PdfType $link, string $linkType)
    {
        // External links
        if ($linkType === 'URI') {
            return PdfDictionary::get($link, 'URI')->value;
        }

        // Internal links
        if (!empty($this->pagesList)) {
            $pageObj = PdfDictionary::get($link, 'D')->value[0];
            foreach ($this->pagesList as $index => $page) {
                if ($page->generationNumber === $pageObj->generationNumber && $page->value === $pageObj->value) {
                    return $index + 1;
                }
            }
        }

        return null;
    }
}
