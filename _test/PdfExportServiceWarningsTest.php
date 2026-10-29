<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\BookCreatorLiveSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\Cache;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\DokuMpdf;
use dokuwiki\plugin\dw2pdf\src\PdfExportService;
use dokuwiki\plugin\dw2pdf\src\Styles;
use dokuwiki\plugin\dw2pdf\src\Template;
use dokuwiki\plugin\dw2pdf\src\Writer;
use DokuWikiTest;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class PdfExportServiceWarningsTest extends DokuWikiTest
{
    /** @var string A list item outside of a list, mPDF reads list styles it never set for it */
    protected const ORPHAN_LI = '<div><li>orphan</li></div>';

    /** @var string[] Messages received by the handler standing in for DokuWiki's ErrorHandler */
    protected array $logged = [];

    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];

        $this->logged = [];
        set_error_handler(function (int $errno, string $errstr): bool {
            $this->logged[] = $errstr;
            return true;
        }, E_WARNING | E_USER_WARNING);
    }

    public function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    /**
     * The HTML used below must make mPDF warn, otherwise the other tests prove nothing
     */
    public function testOrphanListItemWarnsInMpdf(): void
    {
        $mpdf = new Mpdf((new Config())->getMPdfConfig());
        $mpdf->WriteHTML(self::ORPHAN_LI, HTMLParserMode::DEFAULT_MODE);

        $this->assertContains('Undefined array key "list_style_type"', $this->logged);
    }

    /**
     * Warnings raised inside mPDF are not passed on while writing and outputting
     */
    public function testWriteSilencesMpdfWarnings(): void
    {
        $writer = $this->getWriter();
        $file = tempnam(sys_get_temp_dir(), 'dw2pdf');
        try {
            self::callInaccessibleMethod($this->getService(), 'silenceMpdfWarnings', [
                static function () use ($writer, $file) {
                    self::callInaccessibleMethod($writer, 'write', [self::ORPHAN_LI, HTMLParserMode::DEFAULT_MODE]);
                    $writer->outputToFile($file);
                }
            ]);
            $this->assertStringStartsWith('%PDF-', file_get_contents($file));
        } finally {
            unlink($file);
        }

        $this->assertSame([], $this->logged);
    }

    /**
     * A user warning raised outside mPDF still reaches the previous handler
     */
    public function testUserWarningsOutsideMpdfArePassedOn(): void
    {
        self::callInaccessibleMethod($this->getService(), 'silenceMpdfWarnings', [
            static fn() => trigger_error('not from mpdf', E_USER_WARNING)
        ]);

        $this->assertSame(['not from mpdf'], $this->logged);
    }

    /**
     * A PHP warning raised outside mPDF still reaches the previous handler
     */
    public function testPhpWarningsOutsideMpdfArePassedOn(): void
    {
        self::callInaccessibleMethod($this->getService(), 'silenceMpdfWarnings', [
            static function () {
                $data = [];
                return $data['missing'];
            }
        ]);

        $this->assertSame(['Undefined array key "missing"'], $this->logged);
    }

    /**
     * The previous handler is active again once the callback is done
     */
    public function testHandlerIsRestored(): void
    {
        self::callInaccessibleMethod($this->getService(), 'silenceMpdfWarnings', [static fn() => null]);

        trigger_error('after the callback', E_USER_WARNING);
        $this->assertSame(['after the callback'], $this->logged);
    }

    /**
     * Create a service for an empty selection, enough to reach its error handling
     *
     * @return PdfExportService
     */
    protected function getService(): PdfExportService
    {
        $config = new Config(['liveselection' => json_encode([])]);
        $collector = new BookCreatorLiveSelectionCollector($config);
        return new PdfExportService($config, $collector, new Cache($config, $collector), 'Contents');
    }

    /**
     * Create a writer the way PdfExportService does
     *
     * @return Writer
     */
    protected function getWriter(): Writer
    {
        $config = new Config();
        return new Writer(new DokuMpdf($config, 'en'), $config, new Template($config), new Styles($config));
    }
}
