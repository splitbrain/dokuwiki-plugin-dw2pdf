<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\AbstractCollector;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\Writer;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class WriterInternalLinksTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Internal anchors must be rewritten to section IDs while untouched links remain unchanged.
     */
    public function testFixInternalLinksUpdatesKnownTargets(): void
    {
        $config = new Config();
        $collector = new WriterInternalLinksCollectorStub(
            ['playground:fixlinks', 'playground:other'],
            $config
        );

        $html = '<p>'
            . '<a href="doku.php?id=original" data-dw2pdf-target="playground:fixlinks" data-dw2pdf-hash="abc">First</a>'
            // missing:id must keep its original href because the collector will not output that page
            . '<a href="doku.php?id=missing" data-dw2pdf-target="missing:id" data-dw2pdf-hash="def">Second</a>'
            . '</p>';

        $writer = (new \ReflectionClass(Writer::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(Writer::class))->getMethod('fixInternalLinks');
        $method->setAccessible(true);
        $result = $method->invoke($writer, $collector, $html);

        $check = false;
        $section = sectionID('playground:fixlinks', $check);
        $this->assertStringContainsString('href="#' . $section . '__abc"', $result);
        $this->assertStringNotContainsString('data-dw2pdf-target', $result);
        $this->assertStringContainsString('href="doku.php?id=missing"', $result);
    }
}

class WriterInternalLinksCollectorStub extends AbstractCollector
{
    /** @var string[] */
    private array $collectedPages;

    public function __construct(array $pages, Config $config)
    {
        $this->collectedPages = $pages;
        $this->config = $config;
        $this->pages = $pages;
        $this->title = 'stub';
        $this->rev = null;
        $this->at = null;
    }

    protected function collect(): array
    {
        return $this->collectedPages;
    }
}
