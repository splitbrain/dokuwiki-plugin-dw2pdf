<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\AbstractCollector;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\PageCollector;
use dokuwiki\plugin\dw2pdf\src\Template;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class TemplateTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Template placeholders (title, username, QR codes, links) should be expanded from context data.
     */
    public function testPlaceholderReplacement(): void
    {
        global $ID, $INPUT, $conf;
        $INPUT->set('book_title', 'Export Title');
        $ID = 'playground:templatepage';
        saveWikiText($ID, 'template test', 'create');

        $config = new Config([
            'qrcodescale' => 1.5,
        ]);

        $collector = new PageCollector($config);
        $template = new Template($config);
        $template->setContext($collector, $ID, 'username');

        $html = $template->getHTML('unittest');

        $this->assertStringContainsString('Page Number: {PAGENO}', $html);
        $this->assertStringContainsString('Total Pages: {nbpg}', $html);
        $this->assertStringContainsString('Document Title: Export Title', $html);
        $this->assertStringContainsString('Wiki Title: ' . $conf['title'], $html);
        $this->assertStringContainsString('Wiki URL: ' . DOKU_URL, $html);
        $this->assertStringNotContainsString('@DATE@', $html);
        $this->assertStringContainsString('User: username', $html);
        $this->assertStringContainsString('Base Path: ' . DOKU_BASE, $html);
        $this->assertStringContainsString('Include Dir: ' . DOKU_INC, $html);
        $this->assertStringContainsString('Template Base Path: ' . DOKU_BASE . 'lib/plugins/dw2pdf/tpl/default/', $html);
        $this->assertStringContainsString('Template Include Dir: ' . DOKU_INC . 'lib/plugins/dw2pdf/tpl/default/', $html);
        $this->assertStringContainsString('Page ID: ' . $ID, $html);

        $revisionDate = dformat(filemtime(wikiFN($ID)));
        $this->assertStringContainsString('Revision: ' . $revisionDate, $html);

        $pageUrl = wl($ID, [], true, '&');
        $this->assertStringContainsString('Page URL: ' . $pageUrl, $html);
        $this->assertStringContainsString('<barcode', $html);
        $this->assertStringContainsString('size="1.5"', $html);
        $this->assertStringNotContainsString('@QRCODE@', $html);
    }
}
