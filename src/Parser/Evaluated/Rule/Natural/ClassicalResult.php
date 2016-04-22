<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterace;
use Serps\SearchEngine\Google\NaturalResultType;

class ClassicalResult implements ParsingRuleInterace
{

    public function match(GoogleDom $dom, \DOMElement $node)
    {
        /*
         * The code below catches the "srg" element which sometimes
         * comes as an intermediate descendant of the "rso" div.
         *
         * The <div class="srg"> element contains <div class="g">
         * elements which are returned for further parsing.
         */
        $xpath = $dom->getXpath();

        /* @var $srg \DOMElement */
        $srg = $xpath->query("descendant::div[@class='srg'][1]", $node)
                     ->item(0);
        if ($srg)
            return $srg->childNodes;

        /*
         * Parse simple Google result instead.
         */
        if ($node->getAttribute('class') == 'g') {
            foreach ($node->childNodes as $node) {
                if ($node instanceof \DOMElement && $node->getAttribute('class') == 'rc') {
                    return self::RULE_MATCH_MATCHED;
                }
            }
        }
        return self::RULE_MATCH_NOMATCH;
    }

    protected function parseNode(GoogleDom $dom, \DomElement $node)
    {
        $xpath = $dom->getXpath();

        // find the tilte/url
        /* @var $aTag \DOMElement */
        $aTag=$xpath
            ->query("descendant::h3[@class='r'][1]/a", $node)
            ->item(0);
        if (!$aTag) {
            return;
        }

        $destinationTag = $xpath
            ->query("descendant::div[@class='f kv _SWb']/cite", $node)
            ->item(0);

        $descriptionTag = $xpath
            ->query("descendant::span[@class='st']", $node)
            ->item(0);




        return [
            'title'   => $aTag->nodeValue,
            'url'     => $dom->getUrl()->resolve($aTag->getAttribute('href')),
            'destination' => $destinationTag ? $destinationTag->nodeValue : null,
            'description' => $descriptionTag ? $descriptionTag->nodeValue : null
        ];
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet)
    {
        $data = $this->parseNode($dom, $node);

        $resultTypes = [NaturalResultType::CLASSICAL];

        // classical result can have a video thumbnail
        $videoThumb = $dom->getXpath()
            ->query("descendant::g-img[@class='_ygd']/img", $node)
            ->item(0);

        if ($videoThumb) {
            array_unshift($resultTypes, NaturalResultType::CLASSICAL_VIDEO);
            $data['videoLarge'] = false;

            $data['videoCover'] = function () use ($videoThumb) {
                if ($videoThumb) {
                    return $videoThumb->getAttribute('src');
                } else {
                    return null;
                }
            };
        }

        $item = new BaseResult($resultTypes, $data);
        $resultSet->addItem($item);
    }
}
