<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\BookCreatorLiveSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\Cache;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\ExportException;
use dokuwiki\plugin\dw2pdf\src\PdfExportService;
use dokuwiki\test\mock\AuthPlugin;
use DokuWikiTest;

/**
 * Tests the feedback given when an export yields no usable pages
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class ExportFeedbackTest extends DokuWikiTest
{
    /** @var string[] Plugins to enable for these tests */
    protected $pluginsEnabled = ['dw2pdf'];

    /** @var array|null Saved ACL rules to restore after each test */
    protected $oldAuthAcl;

    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];

        global $AUTH_ACL, $auth;
        $this->oldAuthAcl = $AUTH_ACL;
        $auth = new AuthPlugin();
    }

    public function tearDown(): void
    {
        global $AUTH_ACL;
        $AUTH_ACL = $this->oldAuthAcl;
        parent::tearDown();
    }

    /**
     * Deny read access to everybody
     */
    protected function denyAll(): void
    {
        global $conf, $AUTH_ACL;
        $conf['useacl'] = 1;
        $AUTH_ACL = ['*    @ALL    0'];
    }

    /**
     * When every selected page is unreadable, they are dropped from the export but remembered.
     */
    public function testForbiddenPagesArePartitioned(): void
    {
        global $INPUT;
        $pageA = 'playground:secret:a';
        $pageB = 'playground:secret:b';
        saveWikiText($pageA, 'A', 'create');
        saveWikiText($pageB, 'B', 'create');

        $this->denyAll();
        $INPUT->set('selection', json_encode([$pageA, $pageB]));

        $collector = new BookCreatorLiveSelectionCollector(new Config());
        $this->assertSame([], $collector->getPages());
        $this->assertSame([$pageA, $pageB], $collector->getSkippedPages());
    }

    /**
     * Readable pages are kept while forbidden ones are skipped (always-skip behavior).
     */
    public function testMixedAccessKeepsReadablePages(): void
    {
        global $INPUT, $conf, $AUTH_ACL;
        $readable = 'playground:public:a';
        $secret = 'playground:secret:b';
        saveWikiText($readable, 'A', 'create');
        saveWikiText($secret, 'B', 'create');

        $conf['useacl'] = 1;
        $AUTH_ACL = [
            '*                    @ALL    8',
            'playground:secret:*  @ALL    0',
        ];
        $INPUT->set('selection', json_encode([$readable, $secret]));

        $collector = new BookCreatorLiveSelectionCollector(new Config());
        $this->assertSame([$readable], $collector->getPages());
        $this->assertSame([$secret], $collector->getSkippedPages());
    }

    /**
     * An export where every page is forbidden reports only how many pages were skipped.
     */
    public function testServiceThrowsForbiddenWhenAllSkipped(): void
    {
        $pageA = 'playground:secret:a';
        saveWikiText($pageA, 'A', 'create');
        $this->denyAll();

        $service = $this->makeService([$pageA]);

        try {
            $service->getDebugHtml();
            $this->fail('Expected ExportException was not thrown');
        } catch (ExportException $e) {
            $this->assertSame('forbidden', $e->getMessage());
            $this->assertSame([], $e->getArgs(), 'No details about the skipped pages should be exposed');
        }
    }

    /**
     * An export with no selection at all reports an empty selection.
     */
    public function testServiceThrowsEmptyWhenNothingSelected(): void
    {
        $service = $this->makeService([]);

        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('empty');
        $service->getDebugHtml();
    }

    /**
     * Build a debug PdfExportService for a live book selection.
     *
     * @param string[] $selection Page ids to export
     * @return PdfExportService
     */
    protected function makeService(array $selection): PdfExportService
    {
        $config = new Config(['debug' => 1, 'liveselection' => json_encode($selection)]);
        $collector = new BookCreatorLiveSelectionCollector($config);
        $cache = new Cache($config, $collector);
        return new PdfExportService($config, $collector, $cache, 'Contents', 'tester');
    }
}
