<?php

namespace dokuwiki\plugin\dw2pdf\test;

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
     * Create the page, render it through the PdfExportService in debug mode and return the resulting HTML.
     *
     * @param string $pageId
     * @return string
     * @throws \Mpdf\MpdfException
     */
    protected function getDebugHTML(string $pageId, $conf = []): string
    {
        $data = file_get_contents(__DIR__ . '/pages/' . $pageId . '.txt');
        saveWikiText($pageId, $data, 'dw2pdf end-to-end test');

        $config = new Config(array_merge(
            $conf,
            ['debug' => 1, 'exportid' => $pageId]
        ));
        $collector = new PageCollector($config);
        $cache = new Cache($config, $collector);
        $service = new PdfExportService($config, $collector, $cache, 'Contents', 'tester');
        return $service->getDebugHtml();
    }


    public function testNumberedHeaders(): void
    {
        global $conf;
        $conf['plugin']['dw2pdf']['headernumber'] = 1; // Currently Config values are not passed to the renderer
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
}
