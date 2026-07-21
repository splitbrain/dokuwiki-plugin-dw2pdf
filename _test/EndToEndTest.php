<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\BookCreatorLiveSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\Cache;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\PageCollector;
use dokuwiki\plugin\dw2pdf\src\PdfExportService;
use DOMWrap\Document;
use DOMWrap\Element;

/**
 * End-to-end tests for the dw2pdf plugin
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class EndToEndTest extends \DokuWikiTest
{
    protected $pluginsEnabled = ['dw2pdf'];

    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }


    /**
     * Create the pages, render them through the PdfExportService in debug mode and return the resulting HTML.
     *
     * @param string|string[] $pages One or more pages to be included in the export
     * @return string Rendered HTML output
     */
    protected function getDebugHTML($pages, $conf = []): string
    {
        $pages = (array)$pages;

        foreach ($pages as $page) {
            $this->prepareFixturePage($page);
        }

        $config = new Config(array_merge(
            $conf,
            [
                'debug' => 1,
                'liveselection' => json_encode($pages)
            ]
        ));
        $collector = new BookCreatorLiveSelectionCollector($config);
        $cache = new Cache($config, $collector);
        $service = new PdfExportService($config, $collector, $cache, 'Contents', 'tester');
        return $service->getDebugHtml();
    }

    /**
     * Test that numbered headers are rendered correctly
     */
    public function testNumberedHeaders(): void
    {
        $html = $this->getDebugHTML('headers', ['headernumber' => 1]);

        $dom = (new Document())->html($html);

        $dom->find('h1')->each(function ($h) {
            $this->assertMatchesRegularExpression('/^1\. Header/', $h->text());
        });

        $dom->find('h2')->each(function ($h) {
            $this->assertMatchesRegularExpression('/^\d\.\d\. Header/', $h->text());
        });

        $dom->find('h3')->each(function ($h) {
            $this->assertMatchesRegularExpression('/^\d\.\d\.\d\. Header/', $h->text());
        });
    }

    /**
     * Test that numbered headers are rendered correctly across multiple pages
     *
     * Each new page should increase the top-level header number
     */
    public function testNumberedHeadersMultipage(): void
    {
        $html = $this->getDebugHTML(['headers', 'simple'], ['headernumber' => 1]);

        $dom = (new Document())->html($html);

        $count = 1;
        $dom->find('h1')->each(function ($h) use (&$count) {
            $this->assertMatchesRegularExpression('/^' . ($count++) . '\. /', $h->text());
        });
    }

    /**
     * Ensure each rendered page begins with an anchor that namespaces intra-page links.
     */
    public function testDocumentStartCreatesPageAnchors(): void
    {
        $html = $this->getDebugHTML(['renderer_features', 'target']);

        $dom = (new Document())->html($html);

        foreach (['renderer_features', 'target'] as $pageId) {
            $anchors = $dom->find('a[name="' . $pageId . '__"]');
            $this->assertSame(
                1,
                count($anchors),
                'Missing document_start anchor for ' . $pageId
            );
        }
    }

    /**
     * Bookmarks should only be produced up to the configured level and include numbering.
     */
    public function testBookmarksRespectConfiguredLevels(): void
    {
        $html = $this->getDebugHTML('headers', ['headernumber' => 1, 'maxbookmarks' => 2]);

        $dom = (new Document())->html($html);
        $bookmarks = $dom->find('bookmark');

        $this->assertGreaterThan(0, count($bookmarks));

        foreach ($bookmarks as $bookmark) {
            $this->assertLessThanOrEqual(
                1,
                (int)$bookmark->attr('level'),
                'Bookmark level exceeded configured maximum'
            );

            $content = trim((string)$bookmark->attr('content'));
            $this->assertMatchesRegularExpression('/^\d+(?:\.\d+)*\.\s+Header/', $content);
        }

        $this->assertSame(count($dom->find('h1, h2')), count($bookmarks));
    }

    /**
     * Local section links should include the page-specific prefix.
     */
    public function testLocallinksArePrefixedWithPageId(): void
    {
        $html = $this->getDebugHTML(['renderer_features', 'target']);
        $dom = (new Document())->html($html);

        $link = $this->findLinkByText($dom, 'Jump to Remote Section');
        $this->assertNotNull($link, 'Local section link missing');

        $this->assertSame('#renderer_features__remote_section', $link->attr('href'));
    }

    /**
     * Internal links must expose dw2pdf data attributes so the writer can retarget them.
     */
    public function testInternalLinksExposeDw2pdfMetadata(): void
    {
        $html = $this->getRawRendererHtml('renderer_features', [], ['target']);
        $dom = (new Document())->html($html);

        $pageLink = $this->findLinkByText($dom, 'Target page link');
        $this->assertNotNull($pageLink, 'Page link missing');
        $this->assertSame('target', $pageLink->attr('data-dw2pdf-target'));
        $this->assertSame('', $pageLink->attr('data-dw2pdf-hash'));

        $sectionLink = $this->findLinkByText($dom, 'Target section link');
        $this->assertNotNull($sectionLink, 'Section link missing');
        $this->assertSame('target', $sectionLink->attr('data-dw2pdf-target'));
        $this->assertSame('sub_section', $sectionLink->attr('data-dw2pdf-hash'));
    }

    /**
     * Centered media needs to be wrapped so CSS centering survives inside mPDF.
     */
    public function testCenteredMediaIsWrapped(): void
    {
        $html = $this->getDebugHTML('renderer_features');
        $dom = (new Document())->html($html);

        $wrappers = $dom->find('div[align="center"][style*="text-align: center"]');
        $this->assertGreaterThan(0, count($wrappers), 'Centered media wrapper missing');
        $this->assertGreaterThan(0, count($wrappers->first()->find('img')));
    }

    /**
     * Acronyms should render as plain text to avoid useless hover hints in PDFs.
     */
    public function testAcronymOutputDropsHover(): void
    {
        $html = $this->getDebugHTML('renderer_features');
        $dom = (new Document())->html($html);

        $this->assertSame(0, count($dom->find('acronym')));
        $this->assertStringContainsString('FAQ', $html);
        $this->assertStringNotContainsString('Frequently Asked Questions', $html);
    }

    /**
     * Email addresses must not be obfuscated so that mailto links remain readable.
     */
    public function testEmailLinksStayReadable(): void
    {
        $html = $this->getDebugHTML('renderer_features');
        $dom = (new Document())->html($html);

        $link = $this->findLinkByText($dom, 'test@example.com');
        $this->assertNotNull($link, 'Email link missing');
        $this->assertSame('mailto:test@example.com', $link->attr('href'));
    }

    /**
     * Interwiki links should be prefixed with the respective icon.
     */
    public function testInterwikiLinksArePrefixedWithIcon(): void
    {
        $html = $this->getDebugHTML('renderer_features');
        $dom = (new Document())->html($html);

        $link = $dom->find('a.interwiki')->first();
        $this->assertNotNull($link, 'Interwiki link missing');

        $icon = $link->children()->first();
        $this->assertNotNull($icon, 'Interwiki icon missing');
        $this->assertSame('img', strtolower($icon->nodeName));
        $this->assertStringContainsString('iw_doku', $icon->attr('class'));
    }

    /**
     * Render a single page through the dw2pdf renderer without writer post-processing.
     *
     * @param string $pageId
     * @param array $conf
     * @param string[] $additionalPages
     * @return string
     */
    protected function getRawRendererHtml(string $pageId, array $conf = [], array $additionalPages = []): string
    {
        $this->prepareFixturePage($pageId);
        foreach ($additionalPages as $related) {
            $this->prepareFixturePage($related);
        }

        /** @var \renderer_plugin_dw2pdf $renderer */
        $renderer = plugin_load('renderer', 'dw2pdf', true);
        $renderer->setConfig(new Config($conf));

        global $ID;
        $keep = $ID;
        $ID = $pageId;

        $file = wikiFN($pageId);
        $instructions = p_get_instructions(io_readWikiPage($file, $pageId));
        $info = [];
        $html = p_render('dw2pdf', $instructions, $info);

        $ID = $keep;

        return $html;
    }

    /**
     * Persist the given fixture page into the wiki so that it can be rendered.
     *
     * @param string $pageId
     * @return void
     */
    protected function prepareFixturePage(string $pageId): void
    {
        $data = file_get_contents(__DIR__ . '/pages/' . $pageId . '.txt');
        saveWikiText($pageId, $data, 'dw2pdf renderer test');
    }

    /**
     * Locate the first hyperlink whose trimmed text matches the expected label.
     *
     * @param Document $dom
     * @param string $text
     * @return Element|null
     */
    protected function findLinkByText(Document $dom, string $text): ?Element
    {
        foreach ($dom->find('a') as $anchor) {
            if (trim($anchor->text()) === $text) {
                return $anchor;
            }
        }

        return null;
    }
}
