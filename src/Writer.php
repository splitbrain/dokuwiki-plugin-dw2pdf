<?php

namespace dokuwiki\plugin\dw2pdf\src;

use dokuwiki\ErrorHandler;
use Mpdf\HTMLParserMode;
use Mpdf\MpdfException;

class Writer
{
    /** @var DokuPdf Our MPDF instance */
    protected DokuPdf $mpdf;

    /** @var Template The template used */
    protected Template $template;

    /** @var Styles The style parser */
    protected Styles $styles;

    /** @var bool Signal to output a page break before the next output */
    protected bool $breakBeforeNext = false;

    /** @var bool Are we debugging? */
    protected bool $debug = false;

    /** @var string Store HTML when debugging */
    protected string $debugHTML = '';

    /**
     * @param DokuPdf $mpdf
     * @param Template $template
     * @param bool $debug
     */
    public function __construct(DokuPdf $mpdf, Template $template, Styles $styles, bool $debug = false)
    {
        $this->mpdf = $mpdf;
        $this->template = $template;
        $this->styles = $styles;
        $this->debug = $debug;
    }

    /**
     * Initialize the document
     *
     * @param string $title
     * @return void
     * @throws MpdfException
     */
    public function startDocument(string $title): void
    {
        $this->mpdf->SetTitle($title);

        // Set the styles
        $styles = '@page landscape-page { size:landscape }';
        $styles .= 'div.dw2pdf-landscape { page:landscape-page }';
        $styles .= '@page portrait-page { size:portrait }';
        $styles .= 'div.dw2pdf-portrait { page:portrait-page }';
        $styles .= $this->styles->getCSS();
        $this->write($styles, HTMLParserMode::HEADER_CSS);

        //start body html
        $this->write('<div class="dokuwiki">', HTMLParserMode::HTML_BODY, true, false);
    }

    /**
     * Insert a page break
     *
     * @return void
     * @throws MpdfException
     */
    public function pageBreak(): void
    {
        $this->write('<pagebreak />', 2, false, false);
    }

    /**
     * Write a wiki page into the PDF
     *
     * @param string $html The rendered HTML of the wiki page
     * @return void
     * @throws MpdfException
     */
    public function wikiPage(string $html): void
    {
        $this->conditionalPageBreak();

        $this->applyHeaderFooters();

        $this->write($html, HTMLParserMode::HTML_BODY, false, false);

        // add citation box if any
        $cite = $this->template->getHTML('citation');
        if ($cite) {
            $this->write($cite, HTMLParserMode::HTML_BODY, false, false);
        }

        $this->breakAfterMe();
    }

    /**
     * Write the Table of Contents
     *
     * For double-sided documents the ToC is always on an even number of pages, so that the
     * following content is on the correct odd/even page.
     * The first page of ToC starts always at an odd page, so an additional blank page might
     * be included before.
     * There is no page numbering at the pages of the ToC.
     *
     * @param string $header The header text for the ToC (localized))
     * @return void
     * @throws MpdfException
     */
    public function toc(string $header): void
    {
        $this->mpdf->TOCpagebreakByArray([
            'toc-preHTML' => '<h2>' . $header . '</h2>',
            'toc-bookmarkText' => $header,
            'links' => true,
            'outdent' => '1em',
            'pagenumstyle' => '1'
        ]);

        $this->write('<tocpagebreak>', HTMLParserMode::HTML_BODY, false, false);
    }

    /**
     * Insert a cover page
     *
     * Should be called once at the beginning of the PDF generation. Will do nothing if
     * no cover page is configured.
     *
     * @return void
     * @throws MpdfException
     */
    public function cover(): void
    {
        $this->conditionalPageBreak();

        $html = $this->template->getHTML('cover');
        if (!$html) return;

        $this->write($html, HTMLParserMode::HTML_BODY, false, false);

        $this->breakAfterMe();
    }

    /**
     * Insert a back page
     *
     * Should be called once at the end of the PDF generation. Will do nothing if
     * no back page is configured.
     *
     * @return void
     * @throws MpdfException
     */
    public function back(): void
    {
        $this->conditionalPageBreak();

        $html = $this->template->getHTML('back');
        if (!$html) return;

        $this->write($html, HTMLParserMode::HTML_BODY, false, false);
    }

    /**
     * Finalize the document
     *
     * @return void
     * @throws MpdfException
     */
    public function endDocument(): void
    {
        // adds the closing div and finalizes the document
        $this->write('</div>', HTMLParserMode::HTML_BODY, false, true);
    }

    /**
     * Set new headers and footers
     *
     * This will call the appropriate mpdf methods to set headers and footers. It should be called
     * before each wiki page is added to the PDF.
     *
     * On first call on this instance it will set the headers/footers for the first page, afterwards
     * it will use the standard headers/footers.
     *
     * We always set even and odd headers/footers, though they may be identical.
     * @return void
     */
    protected function applyHeaderFooters(): void
    {
        if ($this->isFirstPage) {
            $header = $this->template->getHTML('header', 'first');
            $footer = $this->template->getHTML('footer', 'first');

            if ($header) {
                $this->mpdf->SetHTMLHeader($header, 'O');
                $this->mpdf->SetHTMLHeader($header, 'E');
            }
            if ($footer) {
                $this->mpdf->SetHTMLFooter($footer, 'O');
                $this->mpdf->SetHTMLFooter($footer, 'E');
            }
            $this->isFirstPage = false;
        } else {
            $headerOdd = $this->template->getHTML('header', 'odd');
            $headerEven = $this->template->getHTML('header', 'even');
            $footerOdd = $this->template->getHTML('footer', 'odd');
            $footerEven = $this->template->getHTML('footer', 'even');

            if ($headerOdd) {
                $this->mpdf->SetHTMLHeader($headerOdd, 'O');
            }
            if ($headerEven) {
                $this->mpdf->SetHTMLHeader($headerEven, 'E');
            }
            if ($footerOdd) {
                $this->mpdf->SetHTMLFooter($footerOdd, 'O');
            }
            if ($footerEven) {
                $this->mpdf->SetHTMLFooter($footerEven, 'E');
            }
        }
    }

    /**
     * Insert a page break if there was previous content
     *
     * @return void
     * @throws MpdfException
     */
    protected function conditionalPageBreak(): void
    {
        if ($this->breakBeforeNext) {
            $this->pageBreak();
            $this->breakBeforeNext = false;
        }
    }

    /**
     * Signal that a page break should be inserted before the next content
     *
     * @return void
     */
    protected function breakAfterMe(): void
    {
        $this->breakBeforeNext = true;
    }

    /**
     * Return the debug HTML collected so far
     *
     * Will return an empty string if debugging is not enabled.
     *
     * @return string The collected debug HTML
     */
    public function getDebugHTML(): string
    {
        return $this->debugHTML;
    }

    /**
     * A wrapper around MPDF::WriteHTML
     *
     * When debugging is enabled, the output is written to a debug buffer instead of the PDF.
     *
     * @param string $html The HTML code to write
     * @param int $mode Use HTMLParserMode constants. Controls what parts of the $html code is parsed.
     * @param bool $init Clears and sets buffers to Top level block etc.
     * @param bool $close If false leaves buffers etc. in current state, so that it can continue a block etc.
     * @throws MpdfException
     */
    protected function write(
        string $html,
        int    $mode = HTMLParserMode::DEFAULT_MODE,
        bool   $init = true,
        bool   $close = true
    )
    {
        if (!$this->debug) {
            try {
                $this->mpdf->WriteHTML($html, $mode, $init, $close);
            } catch (MpdfException $e) {
                ErrorHandler::logException($e); // ensure the issue is logged
                throw $e;
            }
            return;
        }

        // when debugging, just store the HTML
        if ($mode === HTMLParserMode::HEADER_CSS) {
            $this->debugHTML .= "\n<style>\n" . $html . "\n</style>\n";
        } else {
            $this->debugHTML .= "\n" . $html . "\n";
        }
    }
}
