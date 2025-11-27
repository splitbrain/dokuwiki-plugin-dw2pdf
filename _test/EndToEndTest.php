<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\BookCreatorLiveSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\Cache;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\PageCollector;
use dokuwiki\plugin\dw2pdf\src\PdfExportService;
use DOMWrap\Document;

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
            $data = file_get_contents(__DIR__ . '/pages/' . $page . '.txt');
            saveWikiText($page, $data, 'dw2pdf end-to-end test');
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
}
