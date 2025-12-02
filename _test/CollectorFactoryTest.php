<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\BookCreatorLiveSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\BookCreatorSavedSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\CollectorFactory;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\NamespaceCollector;
use dokuwiki\plugin\dw2pdf\src\PageCollector;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class CollectorFactoryTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Factory should return the single-page collector for simple exports.
     */
    public function testCreatesPageCollector(): void
    {
        $config = new Config();
        $collector = CollectorFactory::create('export_pdf', $config, null, null);
        $this->assertInstanceOf(PageCollector::class, $collector);
    }

    /**
     * Factory should switch to namespace collector when export_pdfns is requested.
     */
    public function testCreatesNamespaceCollector(): void
    {
        global $INPUT;
        $INPUT->set('book_ns', 'wiki');
        $config = new Config();
        $collector = CollectorFactory::create('export_pdfns', $config, null, null);
        $this->assertInstanceOf(NamespaceCollector::class, $collector);
    }

    /**
     * When a live selection payload is present, the live collector is used.
     */
    public function testCreatesLiveSelectionCollector(): void
    {
        global $INPUT;
        $INPUT->set('selection', '["wiki:start"]');
        $config = new Config();
        $collector = CollectorFactory::create('export_pdfbook', $config, null, null);
        $this->assertInstanceOf(BookCreatorLiveSelectionCollector::class, $collector);
    }

    /**
     * When only a saved selection identifier is present, use the saved collector.
     */
    public function testCreatesSavedSelectionCollector(): void
    {
        global $INPUT;
        $INPUT->set('savedselection', 'my-saved-selection');
        $config = new Config();
        $collector = CollectorFactory::create('export_pdfbook', $config, null, null);
        $this->assertInstanceOf(BookCreatorSavedSelectionCollector::class, $collector);
    }

    /**
     * A pdfbook export without any selection should be rejected.
     */
    public function testBadSelectionCollector(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $config = new Config();
        CollectorFactory::create('export_pdfbook', $config, null, null);
    }

    /**
     * Unknown export actions should be rejected early.
     */
    public function testRejectsUnknownEvent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $config = new Config();
        CollectorFactory::create('random', $config, null, null);
    }
}
