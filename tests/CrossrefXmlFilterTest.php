<?php

/**
 * @file plugins/generic/crossref/tests/CrossrefXmlFilterTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrossrefXmlFilterTest
 *
 * @brief Node builders of the monograph filter that Crossref's schema is strict about.
 */

namespace APP\plugins\generic\crossref\tests;

use APP\facades\Repo;
use APP\plugins\generic\crossref\CrossrefExportDeployment;
use APP\plugins\generic\crossref\filter\MonographCrossrefXmlFilter;
use DOMDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\filter\FilterGroup;
use PKP\tests\PKPTestCase;

#[CoversClass(MonographCrossrefXmlFilter::class)]
class CrossrefXmlFilterTest extends PKPTestCase
{
    private function filter(bool $testMode = false): MonographCrossrefXmlFilter
    {
        $plugin = new class ($testMode) {
            public function __construct(private bool $testMode)
            {
            }

            public function getSetting($contextId, $name)
            {
                return $name === 'testMode' ? $this->testMode : null;
            }
        };
        $context = new class () {
            public function getId()
            {
                return 1;
            }
        };
        $group = new FilterGroup();
        $group->setInputType('class::classes.submission.Submission[]');
        $group->setOutputType('xml::schema(https://www.crossref.org/schemas/crossref5.3.1.xsd)');
        $filter = new MonographCrossrefXmlFilter($group);
        $filter->setDeployment(new CrossrefExportDeployment($context, $plugin));
        return $filter;
    }

    private function author(array $data)
    {
        return Repo::author()->newDataObject($data);
    }

    private function names(?\DOMElement $contributors): array
    {
        $names = [];
        foreach ($contributors ? $contributors->childNodes : [] as $person) {
            $given = $person->getElementsByTagName('given_name')->item(0);
            $names[] = [$person->getAttribute('sequence'), $given?->textContent, $person->getElementsByTagName('surname')->item(0)->textContent];
        }
        return $names;
    }

    public function testBlankNamesAreNeverDepositedAndOtherLocalesFillIn(): void
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $node = $this->filter()->createContributorsNode($doc, [
            $this->author(['givenName' => ['pt_BR' => 'Ana', 'en' => ' '], 'familyName' => ['pt_BR' => 'Souza', 'en' => ' ']]),
            $this->author(['givenName' => ['en' => ' Maria '], 'familyName' => ['en' => '  ']]),
            $this->author(['givenName' => ['en' => ' '], 'familyName' => ['en' => ' ']]),
        ], 'en');
        $this->assertSame([['first', 'Ana', 'Souza'], ['additional', null, 'Maria']], $this->names($node));
        $this->assertSame(null, $this->filter()->createContributorsNode($doc, [$this->author(['familyName' => ['en' => ' ']])], 'en'));
    }

    public function testLongNamesAreCutByCharacterAndStayValidUtf8(): void
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $surname = str_repeat('a', 59) . 'ção';
        $node = $this->filter()->createContributorsNode($doc, [$this->author(['familyName' => ['en' => $surname]])], 'en');
        $value = $node->getElementsByTagName('surname')->item(0)->textContent;
        $this->assertSame(str_repeat('a', 59) . 'ç', $value);
        $this->assertTrue(mb_check_encoding($doc->saveXML(), 'UTF-8'));
    }

    public function testOnlyProductionOrcidIdsAreDepositedOutsideTestMode(): void
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $authors = [
            $this->author(['familyName' => ['en' => 'A'], 'orcid' => 'https://orcid.org/0000-0002-1825-0097']),
            $this->author(['familyName' => ['en' => 'B'], 'orcid' => 'https://sandbox.orcid.org/0000-0002-1825-0097']),
            $this->author(['familyName' => ['en' => 'C'], 'orcid' => '0000-0002-1825-0097']),
        ];
        $orcids = fn ($node) => array_map(fn ($person) => $person->getElementsByTagName('ORCID')->item(0)?->textContent, iterator_to_array($node->childNodes));
        $this->assertSame(['https://orcid.org/0000-0002-1825-0097', null, null], $orcids($this->filter()->createContributorsNode($doc, $authors, 'en')));
        $this->assertSame(['https://orcid.org/0000-0002-1825-0097', 'https://orcid.org/0000-0002-1825-0097', null], $orcids($this->filter(true)->createContributorsNode($doc, $authors, 'en')));
    }

    public function testPagesAcceptRangesWithAnyDash(): void
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $pages = fn ($value) => ($node = $this->filter()->createPagesNode($doc, $value)) ? [$node->getElementsByTagName('first_page')->item(0)->textContent, $node->getElementsByTagName('last_page')->item(0)?->textContent] : null;
        $this->assertSame(['12', '34'], $pages('12 – 34'));
        $this->assertSame(['7', null], $pages('7'));
        $this->assertSame(null, $pages(''));
        $this->assertSame(null, $pages(' - 3'));
    }

    /** The text of the title and subtitle nodes the filter builds for a book. */
    private function bookTitles(array $data, string $locale = 'it'): array
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $publication = Repo::publication()->newDataObject($data);
        $titles = $this->filter()->createBookTitlesNode($doc, $publication, $locale);
        $doc->appendChild($titles);

        return [
            $titles->getElementsByTagName('title')->item(0)?->textContent,
            $titles->getElementsByTagName('subtitle')->item(0)?->textContent,
            $doc->saveXML($titles),
        ];
    }

    public function testTheBookTitleKeepsItsPrefix(): void
    {
        // OJSBR/crossref#2: a book whose title starts with an article entered as
        // prefix ("Le", "The") was deposited without it, Crossref did not match the
        // title it already had for the ISBN, and refused the whole update.
        [$title] = $this->bookTitles([
            'prefix' => ['it' => 'Le'],
            'title' => ['it' => 'tipologie di albero nelle drupacee'],
        ]);
        $this->assertSame('Le tipologie di albero nelle drupacee', $title);

        // Without a prefix, the title as it is.
        [$title, $subtitle] = $this->bookTitles(['title' => ['it' => 'Drupacee']]);
        $this->assertSame('Drupacee', $title);
        $this->assertNull($subtitle);
    }

    public function testTheBookTitleAndSubtitleGoAsTextNotAsEscapedMarkup(): void
    {
        // Titles of OMP 3.4+ may carry markup; Crossref must get the words, not "&lt;i&gt;".
        [$title, $subtitle, $xml] = $this->bookTitles([
            'prefix' => ['en' => 'The'],
            'title' => ['en' => 'genus <i>Prunus</i> &amp; its kin'],
            'subtitle' => ['en' => 'A study of <b>stone</b> fruit'],
        ], 'en');
        $this->assertSame('The genus Prunus & its kin', $title);
        $this->assertSame('A study of stone fruit', $subtitle);
        $this->assertStringNotContainsString('&lt;', $xml);
        $this->assertStringContainsString('&amp;', $xml, 'an ampersand still escaped for the XML');
    }

    public function testTheBookTitleOfTheLocaleIsUsed(): void
    {
        [$title] = $this->bookTitles([
            'prefix' => ['it' => 'Le', 'en' => 'The'],
            'title' => ['it' => 'drupacee', 'en' => 'stone fruits'],
        ], 'en');
        $this->assertSame('The stone fruits', $title);
    }

    public function testABookWithoutATitleInTheLocaleUsesTheOneItHas(): void
    {
        [$title] = $this->bookTitles([
            'prefix' => ['pt_BR' => 'Os'],
            'title' => ['pt_BR' => 'caroços'],
        ], 'en');
        $this->assertSame('Os caroços', $title, 'the prefix of the same locale as the title');
    }

    public function testTheBookTitleIsNotBuiltFromTheRawTitleAnyMore(): void
    {
        // The regression itself: the raw title field lost the prefix.
        $source = (string) file_get_contents(dirname(__DIR__) . '/filter/MonographCrossrefXmlFilter.php');
        $this->assertFalse(strpos($source, "\$publication->getData('title', \$locale)") !== false);
        $this->assertTrue(strpos($source, '$this->createBookTitlesNode($doc, $publication, $locale)') !== false);
    }

    public function testTheBatchTimestampHasRealMilliseconds(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/filter/MonographCrossrefXmlFilter.php');
        $this->assertTrue(strpos($source, "(new \\DateTime())->format('YmdHisv')") !== false, 'date() has no milliseconds: two deposits in a second share a version.');
        $this->assertFalse(strpos($source, "date('YmdHisv')") !== false);
    }
}
