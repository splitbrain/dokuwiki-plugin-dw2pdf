<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\NamespaceCollector;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class NamespaceCollectorCollectTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Namespace collector should remove individually excluded pages and sub-namespaces.
     */
    public function testCollectHonorsExcludes(): void
    {
        global $INPUT;
        $ns = 'playground:dw2pdfns';

        $this->createPage("$ns:start", 'start page');
        $this->createPage("$ns:keep", 'keep me');
        $this->createPage("$ns:skip", 'skip me');
        $this->createPage("$ns:child:page", 'child namespace');

        $INPUT->set('book_ns', $ns);
        $INPUT->set('book_order', 'pagename');
        $INPUT->set('excludes', ["$ns:skip"]);
        $INPUT->set('excludesns', ["$ns:child"]);

        $collector = new NamespaceCollector(new Config());
        $this->assertSame(
            ["$ns:start", "$ns:keep"],
            $collector->getPages()
        );
    }

    /**
     * Invalid namespaces must be ignored gracefully.
     */
    public function testCollectReturnsEmptyForMissingNamespace(): void
    {
        global $INPUT;
        $INPUT->set('book_ns', 'missing:dw2pdfns');

        $collector = new NamespaceCollector(new Config());
        $this->assertSame([], $collector->getPages());
    }

    private function createPage(string $id, string $content): void
    {
        saveWikiText($id, $content, 'dw2pdf namespace test');
    }
}
