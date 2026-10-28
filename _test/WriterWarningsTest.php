<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\DokuMpdf;
use dokuwiki\plugin\dw2pdf\src\Styles;
use dokuwiki\plugin\dw2pdf\src\Template;
use dokuwiki\plugin\dw2pdf\src\Writer;
use DokuWikiTest;
use Mpdf\HTMLParserMode;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class WriterWarningsTest extends DokuWikiTest
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
        $mpdf = new DokuMpdf(new Config(), 'en');
        $mpdf->WriteHTML(self::ORPHAN_LI, HTMLParserMode::DEFAULT_MODE);

        $this->assertContains('Undefined array key "list_style_type"', $this->logged);
    }

    /**
     * Warnings raised inside mPDF are not passed on while writing and outputting
     */
    public function testWriteSilencesMpdfWarnings(): void
    {
        $writer = $this->getWriter();
        self::callInaccessibleMethod($writer, 'write', [self::ORPHAN_LI, HTMLParserMode::DEFAULT_MODE]);

        $file = tempnam(sys_get_temp_dir(), 'dw2pdf');
        try {
            $writer->outputToFile($file);
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
        self::callInaccessibleMethod($this->getWriter(), 'silenceMpdfWarnings', [
            static fn() => trigger_error('not from mpdf', E_USER_WARNING)
        ]);

        $this->assertSame(['not from mpdf'], $this->logged);
    }

    /**
     * A PHP warning raised outside mPDF still reaches the previous handler
     */
    public function testPhpWarningsOutsideMpdfArePassedOn(): void
    {
        self::callInaccessibleMethod($this->getWriter(), 'silenceMpdfWarnings', [
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
        self::callInaccessibleMethod($this->getWriter(), 'silenceMpdfWarnings', [static fn() => null]);

        trigger_error('after the callback', E_USER_WARNING);
        $this->assertSame(['after the callback'], $this->logged);
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
