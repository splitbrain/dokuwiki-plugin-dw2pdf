<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\PageCollector;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class PageCollectorTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Exporting a single page should return the current page ID when it exists.
     */
    public function testCollectsExistingPage(): void
    {
        global $ID, $INPUT;
        $ID = 'playground:dw2pdfpage';
        $INPUT->set('id', $ID);
        saveWikiText($ID, 'DW2PDF page content', 'create test page');

        $collector = new PageCollector(new Config());
        $this->assertSame([$ID], $collector->getPages());
    }

    /**
     * Missing pages should not be exported.
     */
    public function testReturnsEmptyForMissingPage(): void
    {
        global $ID, $INPUT;
        $ID = 'playground:missingdw2pdf';
        $INPUT->set('id', $ID);

        @unlink(wikiFN($ID));

        $collector = new PageCollector(new Config());
        $this->assertSame([], $collector->getPages());
    }
}
