<?php

namespace dokuwiki\plugin\dw2pdf\src;

use Mpdf\HTMLParserMode;
use Mpdf\MpdfException;

/**
 * @todo handle actual writing in a separate method to allow for centralized error handling and debug writing to HTML
 */
class Writer
{

    protected DokuPdf $mpdf;
    protected Template $template;
    protected bool $breakBeforeNext = false;

    /**
     * @param DokuPdf $mpdf
     * @param Template $template
     */
    public function __construct(DokuPdf $mpdf, Template $template)
    {
        $this->mpdf = $mpdf;
        $this->template = $template;
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

        // Set the styles FIXME to be moved into Styles class
        $styles = '@page landscape-page { size:landscape }';
        $styles .= 'div.dw2pdf-landscape { page:landscape-page }';
        $styles .= '@page portrait-page { size:portrait }';
        $styles .= 'div.dw2pdf-portrait { page:portrait-page }';
        // FIXME$styles .= $this->loadCSS();
        $this->mpdf->WriteHTML($styles, HTMLParserMode::HEADER_CSS);

        //start body html
        $this->mpdf->WriteHTML('<div class="dokuwiki">', HTMLParserMode::HTML_BODY, true, false);
    }

    /**
     * Insert a page break
     *
     * @return void
     * @throws MpdfException
     */
    public function pageBreak(): void
    {
        $this->mpdf->WriteHTML('<pagebreak />', 2, false, false);
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

        $this->mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY, false, false);

        // add citation box if any
        $cite = $this->template->getHTML('citation');
        if ($cite) {
            $this->mpdf->WriteHTML($cite, HTMLParserMode::HTML_BODY, false, false);
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

        $this->mpdf->WriteHTML('<tocpagebreak>', HTMLParserMode::HTML_BODY, false, false);
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

        $this->mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY, false, false);

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

        $this->mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY, false, false);
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
        $this->mpdf->WriteHTML('</div>', HTMLParserMode::HTML_BODY, false, true);
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
}
