<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\BookCreatorLiveSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\Config;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class BookCreatorLiveSelectionCollectorTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Live selections should be decoded and normalized via cleanID().
     */
    public function testCollectsPagesFromJsonSelection(): void
    {
        global $INPUT;
        $pageA = 'playground:bookcreator:a';
        $pageB = 'playground:bookcreator:Child';
        $pageC = 'playground:bookcreator:nonexistent';
        saveWikiText($pageA, 'A', 'create');
        saveWikiText($pageB, 'B', 'create');

        // page B has mixed case to test cleanID()
        $INPUT->set('selection', json_encode([$pageA, $pageB, $pageC])); // page B has mixed case to test cleanID()

        $collector = new BookCreatorLiveSelectionCollector(new Config());
        $this->assertSame(
            [$pageA, 'playground:bookcreator:child'], // page C should be skipped as it does not exist
            $collector->getPages()
        );
    }

    /**
     * Invalid JSON payloads must bubble up as JsonException so the caller can handle them.
     */
    public function testThrowsOnInvalidJson(): void
    {
        global $INPUT;
        $INPUT->set('selection', '{invalid');

        $this->expectException(\JsonException::class);
        new BookCreatorLiveSelectionCollector(new Config());
    }
}
