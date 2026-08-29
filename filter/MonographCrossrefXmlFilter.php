<?php

/**
 * @file plugins/generic/crossref/filter/MonographCrossrefXmlFilter.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MonographCrossrefXmlFilter
 *
 * @brief Class that converts a monograph (and its chapters) to a Crossref book-deposit XML document.
 */

namespace APP\plugins\generic\crossref\filter;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\crossref\CrossrefExportDeployment;
use APP\submission\Submission;
use PKP\core\PKPApplication;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class MonographCrossrefXmlFilter extends NativeExportFilter
{
    /**
     * Constructor
     *
     * @param \PKP\filter\FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Crossref XML monograph export');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see \PKP\filter\Filter::process()
     *
     * @param array $pubObjects Array of Submissions (monographs)
     *
     * @return \DOMDocument
     */
    public function &process(&$pubObjects)
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        // Create the root node and the head node
        $rootNode = $this->createRootNode($doc);
        $doc->appendChild($rootNode);
        $rootNode->appendChild($this->createHeadNode($doc));

        // Create and append the 'body' node, that contains everything
        $deployment = $this->getDeployment();
        $bodyNode = $doc->createElementNS($deployment->getNamespace(), 'body');
        $rootNode->appendChild($bodyNode);

        foreach ($pubObjects as $pubObject) {
            /** @var Submission $pubObject */
            $bookNode = $this->createBookNode($doc, $pubObject);
            if ($bookNode) {
                $bodyNode->appendChild($bookNode);
            }
        }
        return $doc;
    }

    //
    // Document scaffolding
    //
    /**
     * Create and return the root node 'doi_batch'.
     *
     * @param \DOMDocument $doc
     *
     * @return \DOMElement
     */
    public function createRootNode($doc)
    {
        /** @var CrossrefExportDeployment $deployment */
        $deployment = $this->getDeployment();
        $rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRootElementName());
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $deployment->getXmlSchemaInstance());
        $rootNode->setAttribute('version', $deployment->getXmlSchemaVersion());
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());
        return $rootNode;
    }

    /**
     * Create and return the head node 'head'.
     *
     * @param \DOMDocument $doc
     *
     * @return \DOMElement
     */
    public function createHeadNode($doc)
    {
        /** @var CrossrefExportDeployment $deployment */
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $plugin = $deployment->getPlugin();
        $namespace = $deployment->getNamespace();

        $headNode = $doc->createElementNS($namespace, 'head');
        $headNode->appendChild($doc->createElementNS($namespace, 'doi_batch_id', htmlspecialchars($context->getData('acronym', $context->getPrimaryLocale()) . '_' . time(), ENT_COMPAT, 'UTF-8')));
        $headNode->appendChild($doc->createElementNS($namespace, 'timestamp', date('YmdHisv')));

        $depositorNode = $doc->createElementNS($namespace, 'depositor');
        $depositorName = $plugin->getSetting($context->getId(), 'depositorName');
        if (empty($depositorName)) {
            $depositorName = $context->getData('contactName');
        }
        $depositorEmail = $plugin->getSetting($context->getId(), 'depositorEmail');
        if (empty($depositorEmail)) {
            $depositorEmail = $context->getData('contactEmail');
        }
        $depositorNode->appendChild($doc->createElementNS($namespace, 'depositor_name', htmlspecialchars($depositorName, ENT_COMPAT, 'UTF-8')));
        $depositorNode->appendChild($doc->createElementNS($namespace, 'email_address', htmlspecialchars($depositorEmail, ENT_COMPAT, 'UTF-8')));
        $headNode->appendChild($depositorNode);

        $registrant = $context->getData('publisher') ?: $context->getName($context->getPrimaryLocale());
        $headNode->appendChild($doc->createElementNS($namespace, 'registrant', htmlspecialchars($registrant, ENT_COMPAT, 'UTF-8')));
        return $headNode;
    }

    //
    // Book conversion functions
    //
    /**
     * Create and return the 'book' node for a monograph.
     *
     * @param \DOMDocument $doc
     * @param Submission $submission
     *
     * @return \DOMElement
     */
    public function createBookNode($doc, $submission)
    {
        /** @var CrossrefExportDeployment $deployment */
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $namespace = $deployment->getNamespace();

        $publication = $submission->getCurrentPublication();
        $locale = $publication->getData('locale') ?: $context->getPrimaryLocale();

        $bookNode = $doc->createElementNS($namespace, 'book');
        $bookNode->setAttribute('book_type', 'monograph');

        $bookMetadataNode = $doc->createElementNS($namespace, 'book_metadata');
        $language = $this->_getShortLanguage($locale);
        if ($language) {
            $bookMetadataNode->setAttribute('language', $language);
        }

        // 1. contributors (book authors)
        $contributorsNode = $this->createContributorsNode($doc, $publication->getData('authors'), $locale);
        if ($contributorsNode) {
            $bookMetadataNode->appendChild($contributorsNode);
        }

        // 2. titles (required)
        $bookMetadataNode->appendChild($this->createTitlesNode(
            $doc,
            $publication->getData('title', $locale),
            $publication->getData('subtitle', $locale)
        ));

        // 3. publication_date (at least one required)
        $bookMetadataNode->appendChild($this->createPublicationDateNode(
            $doc,
            $publication->getData('datePublished'),
            $publication->getData('copyrightYear')
        ));

        // 4. isbn or noisbn (required)
        $isbnNodes = $this->createIsbnNodes($doc, $publication);
        if (!empty($isbnNodes)) {
            foreach ($isbnNodes as $isbnNode) {
                $bookMetadataNode->appendChild($isbnNode);
            }
        } else {
            $noIsbnNode = $doc->createElementNS($namespace, 'noisbn');
            $noIsbnNode->setAttribute('reason', 'monograph');
            $bookMetadataNode->appendChild($noIsbnNode);
        }

        // 5. publisher (required)
        $publisherNode = $doc->createElementNS($namespace, 'publisher');
        $publisherName = $context->getData('publisher') ?: $context->getName($context->getPrimaryLocale());
        $publisherNode->appendChild($doc->createElementNS($namespace, 'publisher_name', htmlspecialchars($publisherName, ENT_COMPAT, 'UTF-8')));
        if ($location = $context->getData('location')) {
            $publisherNode->appendChild($doc->createElementNS($namespace, 'publisher_place', htmlspecialchars($location, ENT_COMPAT, 'UTF-8')));
        }
        $bookMetadataNode->appendChild($publisherNode);

        // 6. doi_data for the monograph (only when the monograph has a DOI and the type is enabled)
        if ($context->isDoiTypeEnabled(Repo::doi()::TYPE_PUBLICATION) && $publication->getDoi()) {
            $request = Application::get()->getRequest();
            $dispatcher = $this->_getDispatcher($request);
            $url = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'catalog', 'book', [$submission->getBestId()]);
            $bookMetadataNode->appendChild($this->createDoiDataNode($doc, $publication->getDoi(), $url));
        }

        $bookNode->appendChild($bookMetadataNode);

        // content_item nodes for chapters
        $chapters = $publication->getData('chapters');
        if ($chapters && $context->isDoiTypeEnabled(Repo::doi()::TYPE_CHAPTER)) {
            foreach ($chapters as $chapter) {
                if (!$chapter->getDoi()) {
                    continue;
                }
                $contentItemNode = $this->createContentItemNode($doc, $submission, $publication, $chapter, $locale);
                if ($contentItemNode) {
                    $bookNode->appendChild($contentItemNode);
                }
            }
        }

        return $bookNode;
    }

    /**
     * Create and return a 'content_item' node for a chapter.
     *
     * @param \DOMDocument $doc
     * @param Submission $submission
     * @param \APP\publication\Publication $publication
     * @param \APP\monograph\Chapter $chapter
     * @param string $locale
     *
     * @return \DOMElement
     */
    public function createContentItemNode($doc, $submission, $publication, $chapter, $locale)
    {
        /** @var CrossrefExportDeployment $deployment */
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $namespace = $deployment->getNamespace();

        $contentItemNode = $doc->createElementNS($namespace, 'content_item');
        $contentItemNode->setAttribute('component_type', 'chapter');

        // 1. contributors (chapter authors)
        $contributorsNode = $this->createContributorsNode($doc, $chapter->getAuthors(), $locale);
        if ($contributorsNode) {
            $contentItemNode->appendChild($contributorsNode);
        }

        // 2. titles
        $chapterTitle = $chapter->getTitle($locale);
        if (empty($chapterTitle)) {
            $allTitles = $chapter->getTitle(null);
            $chapterTitle = is_array($allTitles) ? (string) reset($allTitles) : (string) $allTitles;
        }
        $contentItemNode->appendChild($this->createTitlesNode(
            $doc,
            $chapterTitle,
            $chapter->getSubtitle($locale)
        ));

        // 3. publication_date (fall back to the monograph date)
        $chapterDate = $chapter->getDatePublished() ?: $publication->getData('datePublished');
        if ($chapterDate) {
            $contentItemNode->appendChild($this->createPublicationDateNode($doc, $chapterDate, $publication->getData('copyrightYear')));
        }

        // 4. pages
        $pagesNode = $this->createPagesNode($doc, $chapter->getPages());
        if ($pagesNode) {
            $contentItemNode->appendChild($pagesNode);
        }

        // 5. doi_data (required for content_item)
        $request = Application::get()->getRequest();
        $dispatcher = $this->_getDispatcher($request);
        $url = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'catalog', 'book', [$submission->getBestId(), 'chapter', $chapter->getId()]);
        $contentItemNode->appendChild($this->createDoiDataNode($doc, $chapter->getDoi(), $url));

        return $contentItemNode;
    }

    //
    // Reusable node builders
    //
    /**
     * Create and return a 'contributors' node from a collection of authors, or null when empty.
     *
     * @param \DOMDocument $doc
     * @param iterable $authors
     * @param string $locale Locale to read author names in
     *
     * @return \DOMElement|null
     */
    public function createContributorsNode($doc, $authors, $locale)
    {
        $deployment = $this->getDeployment();
        $namespace = $deployment->getNamespace();

        $contributorsNode = null;
        $isFirst = true;
        foreach (is_iterable($authors) ? $authors : [] as $author) {
            $surname = (string) $author->getData('familyName', $locale);
            $givenName = (string) $author->getData('givenName', $locale);
            // Fall back to any available locale if the requested one is empty.
            if ($surname === '' && $givenName === '') {
                $familyNames = $author->getData('familyName');
                $givenNames = $author->getData('givenName');
                $surname = is_array($familyNames) ? (string) reset($familyNames) : (string) $familyNames;
                $givenName = is_array($givenNames) ? (string) reset($givenNames) : (string) $givenNames;
            }
            // Crossref requires a surname; if only a given name is available, use it as the surname.
            if (empty($surname)) {
                $surname = $givenName;
                $givenName = '';
            }
            if (empty($surname)) {
                continue;
            }

            if ($contributorsNode === null) {
                $contributorsNode = $doc->createElementNS($namespace, 'contributors');
            }

            $personNameNode = $doc->createElementNS($namespace, 'person_name');
            $personNameNode->setAttribute('sequence', $isFirst ? 'first' : 'additional');
            $personNameNode->setAttribute('contributor_role', 'author');
            if (!empty($givenName)) {
                $personNameNode->appendChild($doc->createElementNS($namespace, 'given_name', htmlspecialchars(substr($givenName, 0, 60), ENT_COMPAT, 'UTF-8')));
            }
            $personNameNode->appendChild($doc->createElementNS($namespace, 'surname', htmlspecialchars(substr($surname, 0, 60), ENT_COMPAT, 'UTF-8')));
            if ($orcid = $this->getDepositableOrcid($author)) {
                $verified = $author->getData('orcidIsVerified') ?? (bool) $author->getData('orcidAccessToken');
                $orcidNode = $doc->createElementNS($namespace, 'ORCID');
                $orcidNode->setAttribute('authenticated', $verified ? 'true' : 'false');
                $orcidNode->appendChild($doc->createTextNode($orcid));
                $personNameNode->appendChild($orcidNode);
            }
            $contributorsNode->appendChild($personNameNode);
            $isFirst = false;
        }

        return $contributorsNode;
    }

    /**
     * Return the ORCID iD that may be deposited for an author, or null when it must be skipped.
     *
     * The Crossref schema (`orcid_t`) only accepts `https?://orcid.org/NNNN-NNNN-NNNN-NNNX`.
     * Any other value - most notably the ORCID Sandbox iD that OJS/OMP stores as
     * `https://sandbox.orcid.org/...` when the ORCID integration runs against the sandbox
     * API - fails schema validation and aborts the whole export.
     *
     * Sandbox iDs are therefore rewritten to the production host while the plugin is in test
     * mode, so the Sandbox -> OMP -> Crossref workflow can be exercised against
     * test.crossref.org, and dropped otherwise, so that a sandbox iD is never deposited
     * against a live DOI. Anything else that does not match the schema is dropped as well,
     * instead of taking the export down with it.
     *
     * @param \PKP\author\Author $author
     */
    protected function getDepositableOrcid($author): ?string
    {
        $orcid = trim((string) $author->getData('orcid'));
        if ($orcid === '') {
            return null;
        }
        if (preg_match('#^https?://orcid\.org/\d{4}-\d{4}-\d{4}-\d{3}[\dX]$#', $orcid)) {
            return $orcid;
        }
        if (preg_match('#^https?://sandbox\.orcid\.org/(\d{4}-\d{4}-\d{4}-\d{3}[\dX])$#', $orcid, $matches)) {
            $deployment = $this->getDeployment();
            $plugin = $deployment->getPlugin();
            $context = $deployment->getContext();
            if ($plugin && $context && $plugin->getSetting($context->getId(), 'testMode')) {
                return 'https://orcid.org/' . $matches[1];
            }
        }
        return null;
    }

    /**
     * Create and return a 'titles' node.
     *
     * @param \DOMDocument $doc
     * @param string $title
     * @param string|null $subtitle
     *
     * @return \DOMElement
     */
    public function createTitlesNode($doc, $title, $subtitle = null)
    {
        $deployment = $this->getDeployment();
        $namespace = $deployment->getNamespace();

        $titlesNode = $doc->createElementNS($namespace, 'titles');
        $titlesNode->appendChild($doc->createElementNS($namespace, 'title', htmlspecialchars($title, ENT_COMPAT, 'UTF-8')));
        if (!empty($subtitle)) {
            $titlesNode->appendChild($doc->createElementNS($namespace, 'subtitle', htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8')));
        }
        return $titlesNode;
    }

    /**
     * Create and return a 'publication_date' node.
     *
     * @param \DOMDocument $doc
     * @param string|null $dateString A full date (Y-m-d) if available
     * @param string|null $fallbackYear A year to use when no full date is set
     *
     * @return \DOMElement
     */
    public function createPublicationDateNode($doc, $dateString, $fallbackYear = null)
    {
        $deployment = $this->getDeployment();
        $namespace = $deployment->getNamespace();

        $publicationDateNode = $doc->createElementNS($namespace, 'publication_date');
        $publicationDateNode->setAttribute('media_type', 'online');

        if (!empty($dateString)) {
            $publicationDate = strtotime($dateString);
            if (date('m', $publicationDate)) {
                $publicationDateNode->appendChild($doc->createElementNS($namespace, 'month', date('m', $publicationDate)));
            }
            if (date('d', $publicationDate)) {
                $publicationDateNode->appendChild($doc->createElementNS($namespace, 'day', date('d', $publicationDate)));
            }
            $year = date('Y', $publicationDate);
        } else {
            $year = $fallbackYear ?: date('Y');
        }
        $publicationDateNode->appendChild($doc->createElementNS($namespace, 'year', htmlspecialchars((string) $year, ENT_COMPAT, 'UTF-8')));
        return $publicationDateNode;
    }

    /**
     * Create and return ISBN nodes from a publication's publication formats.
     *
     * @param \DOMDocument $doc
     * @param \APP\publication\Publication $publication
     *
     * @return \DOMElement[]
     */
    public function createIsbnNodes($doc, $publication)
    {
        $deployment = $this->getDeployment();
        $namespace = $deployment->getNamespace();

        $nodes = [];
        $seen = [];
        $publicationFormats = $publication->getData('publicationFormats');
        foreach (is_iterable($publicationFormats) ? $publicationFormats : [] as $publicationFormat) {
            $mediaType = $publicationFormat->getPhysicalFormat() ? 'print' : 'electronic';
            foreach ($publicationFormat->getIdentificationCodes()->toArray() as $identificationCode) {
                // ONIX List 5: '02' = ISBN-10, '15' = ISBN-13
                if (!in_array($identificationCode->getCode(), ['02', '15'])) {
                    continue;
                }
                $value = preg_replace('/[^0-9Xx]/', '', $identificationCode->getValue());
                if (empty($value) || isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $isbnNode = $doc->createElementNS($namespace, 'isbn', htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
                $isbnNode->setAttribute('media_type', $mediaType);
                $nodes[] = $isbnNode;
                if (count($nodes) >= 6) {
                    return $nodes;
                }
            }
        }
        return $nodes;
    }

    /**
     * Create and return a 'pages' node from a chapter's page string, or null.
     *
     * @param \DOMDocument $doc
     * @param string|null $pages e.g. "12-34" or "12"
     *
     * @return \DOMElement|null
     */
    public function createPagesNode($doc, $pages)
    {
        if (empty($pages)) {
            return null;
        }
        $deployment = $this->getDeployment();
        $namespace = $deployment->getNamespace();

        $pagesParts = preg_split('/[-–—]/u', $pages, 2);
        $firstPage = trim($pagesParts[0]);
        if ($firstPage === '') {
            return null;
        }

        $pagesNode = $doc->createElementNS($namespace, 'pages');
        $pagesNode->appendChild($doc->createElementNS($namespace, 'first_page', htmlspecialchars($firstPage, ENT_COMPAT, 'UTF-8')));
        if (isset($pagesParts[1]) && trim($pagesParts[1]) !== '') {
            $pagesNode->appendChild($doc->createElementNS($namespace, 'last_page', htmlspecialchars(trim($pagesParts[1]), ENT_COMPAT, 'UTF-8')));
        }
        return $pagesNode;
    }

    /**
     * Create and return a 'doi_data' node.
     *
     * @param \DOMDocument $doc
     * @param string $doi
     * @param string $url
     *
     * @return \DOMElement
     */
    public function createDoiDataNode($doc, $doi, $url)
    {
        $deployment = $this->getDeployment();
        $namespace = $deployment->getNamespace();

        $doiDataNode = $doc->createElementNS($namespace, 'doi_data');
        $doiDataNode->appendChild($doc->createElementNS($namespace, 'doi', htmlspecialchars($doi, ENT_COMPAT, 'UTF-8')));
        $doiDataNode->appendChild($doc->createElementNS($namespace, 'resource', htmlspecialchars($url, ENT_COMPAT, 'UTF-8')));
        return $doiDataNode;
    }

    //
    // Helpers
    //
    /**
     * Reduce an OMP locale (e.g. "pt_BR") to a Crossref language code (e.g. "pt").
     */
    protected function _getShortLanguage(?string $locale): string
    {
        if (empty($locale)) {
            return '';
        }
        return substr($locale, 0, 2);
    }

    /**
     * Helper to ensure dispatcher is available even when called from CLI tools
     */
    protected function _getDispatcher(\APP\core\Request $request): \PKP\core\Dispatcher
    {
        $dispatcher = $request->getDispatcher();
        if ($dispatcher === null) {
            $dispatcher = Application::get()->getDispatcher();
        }
        return $dispatcher;
    }
}
