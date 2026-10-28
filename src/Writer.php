<?php

namespace dokuwiki\plugin\dw2pdf\src;

use DOMDocument;
use dokuwiki\ErrorHandler;
use Mpdf\HTMLParserMode;
use Mpdf\MpdfException;

class Writer
{
    /** @var DokuMpdf Our MPDF instance */
    protected DokuMpdf $mpdf;

    /** @var Config The configuration */
    protected Config $config;

    /** @var Template The template used */
    protected Template $template;

    /** @var Styles The style parser */
    protected Styles $styles;

    /** @var HeadingResolver Resolves the heading markers emitted by the renderer */
    protected HeadingResolver $headings;

    /** @var bool Signal to output a page break before the next output */
    protected bool $breakBeforeNext = false;

    /** @var bool Are we debugging? */
    protected bool $debug = false;

    /** @var string Store HTML when debugging */
    protected string $debugHTML = '';

    /**
     * @param DokuMpdf $mpdf
     * @param Template $template
     * @param Styles $styles
     */
    public function __construct(DokuMpdf $mpdf, Config $config, Template $template, Styles $styles)
    {
        $this->mpdf = $mpdf;
        $this->config = $config;
        $this->template = $template;
        $this->styles = $styles;
        $this->debug = $config->isDebugEnabled();
        $this->headings = new HeadingResolver($config);
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
        $styles .= $this->defineHeaderFooters();

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
        // Redefine the odd/even headers/footers for this page *before* the break: mPDF captures a
        // page's header and footer when the page begins.
        $this->applyHeaderFooters();
        $this->conditionalPageBreak();
        $this->write($html, HTMLParserMode::HTML_BODY, false, false);

        // add citation box if any
        $cite = $this->template->getHTML('citation');
        if ($cite) {
            $this->write($cite, HTMLParserMode::HTML_BODY, false, false);
        }

        $this->breakAfterMe();
    }

    /**
     * Render and write a wiki page into the PDF
     *
     * This caches the rendered page individually (unless a specific revision is requested). So even
     * when PDF needs to be regenerated, pages that have not changed will be loaded from cache.
     *
     * @param AbstractCollector $collector The collector providing the page context
     * @param string $pageId The page ID to render
     * @return void
     * @throws MpdfException
     */
    public function renderWikiPage(AbstractCollector $collector, string $pageId): void
    {
        $rev = $collector->getRev();
        $at = $collector->getAt();
        $file = wikiFN($pageId, $rev);

        //ensure $id is in global $ID (needed for parsing)
        global $ID;
        $keep = $ID;
        $ID = $pageId;

        if ($collector->getRev()) {
            //no caching on old revisions
            $html = p_render('dw2pdf', p_get_instructions(io_readWikiPage($file, $pageId, $rev)), $info, $at);
        } else {
            $html = p_cached_output($file, 'dw2pdf', $pageId);
        }

        //restore ID (just in case)
        $ID = $keep;

        // Resolve headings, fix internal links then write the page
        $html = $this->headings->resolvePage($html);
        $html = $this->fixInternalLinks($collector, $html);
        $this->wikiPage($html);
    }

    /**
     * If the given HTML contains internal links to pages that are part of the exported PDF,
     * fix the links to point to the correct section within the PDF.
     *
     * @param AbstractCollector $collector
     * @param string $html The rendered HTML of the wiki page
     * @return string
     */
    protected function fixInternalLinks(AbstractCollector $collector, string $html): string
    {
        if ($html === '') return $html;

        // quick bail out if the page has no internal link markers
        if (!str_contains($html, 'data-dw2pdf-target')) {
            return $html;
        }

        $pages = $collector->getPages();
        if ($pages === []) {
            return $html;
        }
        $pages = array_fill_keys($pages, true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8"?>' . '<div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $html;
        }

        $anchors = $dom->getElementsByTagName('a');
        if ($anchors->length === 0) {
            return $html;
        }

        $pageAnchors = [];
        foreach ($anchors as $anchor) {
            /** @var \DOMElement $anchor */
            if (!$anchor->hasAttribute('data-dw2pdf-target')) {
                continue;
            }

            $target = $anchor->getAttribute('data-dw2pdf-target');
            if ($target === '' || !isset($pages[$target])) {
                $anchor->removeAttribute('data-dw2pdf-target');
                $anchor->removeAttribute('data-dw2pdf-hash');
                continue;
            }

            if (!isset($pageAnchors[$target])) {
                $check = false;
                $pageAnchors[$target] = sectionID($target, $check);
            }

            $hash = $anchor->getAttribute('data-dw2pdf-hash');
            $anchor->setAttribute(
                'href',
                '#' . $pageAnchors[$target] . '__' . $hash
            );

            $anchor->removeAttribute('data-dw2pdf-target');
            $anchor->removeAttribute('data-dw2pdf-hash');
        }

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if (!$wrapper) {
            return $html;
        }

        $result = '';
        foreach ($wrapper->childNodes as $node) {
            $result .= $dom->saveHTML($node);
        }

        return $result;
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
        $html = $this->template->getHTML('cover');
        if (!$html) return;

        $this->applyHeaderFooters();
        $this->conditionalPageBreak();
        $this->write($html, HTMLParserMode::HTML_BODY, false, false);

        $this->breakAfterMe();
    }

    /**
     * Insert a back page and settle the export-wide header/footer context
     *
     * Should be called once at the end of the PDF generation. Even when no back page is
     * configured it restores the export context, so the table of contents (which mPDF renders
     * at output time) does not inherit the last rendered wiki page's placeholders.
     *
     * @return void
     * @throws MpdfException
     */
    public function back(): void
    {
        // The back page - and the table of contents, which mPDF renders at output time after every
        // page - belong to the export as a whole. Return to the export context and refresh the
        // header/footer blocks so they do not carry the last rendered wiki page's placeholders.
        $this->template->resetContext();
        $this->applyHeaderFooters();

        $html = $this->template->getHTML('back');
        if (!$html) return;

        $this->conditionalPageBreak();
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
     * Define the named headers/footers and return the CSS that binds them to the pages
     *
     * mPDF selects a page's header and footer through @page CSS rules that reference named blocks.
     * The "first" block is bound to "@page :first" so mPDF places it on the first physical page
     * only. The odd/even blocks are bound to "@page" and apply to every following page.
     *
     * The blocks defined here resolve their placeholders against the first page (the current
     * context when startDocument() runs). The first-page block keeps that content, while the
     * odd/even blocks are redefined per wiki page by applyHeaderFooters().
     *
     * Templates that do not exist are skipped, so their pages simply carry no header/footer.
     *
     * @return string The @page CSS binding the defined blocks
     * @throws MpdfException
     */
    protected function defineHeaderFooters(): string
    {
        $firstRules = '';
        $pageRules = '';
        foreach (['header', 'footer'] as $section) {
            foreach (['first', 'odd', 'even'] as $order) {
                if (!$this->defineNamedBlock($section, $order)) continue;

                if ($order === 'first') {
                    $firstRules .= $section . ': html_' . $section . '_first;';
                } else {
                    $pageRules .= $order . '-' . $section . '-name: html_' . $section . '_' . $order . ';';
                }
            }
        }

        $css = '';
        if ($firstRules !== '') $css .= '@page :first { ' . $firstRules . ' }';
        if ($pageRules !== '') $css .= '@page { ' . $pageRules . ' }';
        return $css;
    }

    /**
     * Redefine the odd/even headers and footers from the current template context
     *
     * Must run *before* the page break that starts a page, because mPDF captures a page's header
     * and footer when the page begins. The blocks are re-read from the template on every call so
     * per-page placeholders (@ID@, @PAGEURL@, @QRCODE@, ...) resolve against the current context.
     *
     * The first-page block is left untouched: "@page :first" consumes it on the first physical page
     * only, so it never needs a per-page value.
     *
     * @return void
     * @throws MpdfException
     */
    protected function applyHeaderFooters(): void
    {
        foreach (['header', 'footer'] as $section) {
            foreach (['odd', 'even'] as $order) {
                $this->defineNamedBlock($section, $order);
            }
        }
    }

    /**
     * Define a single named header or footer block from its template
     *
     * The block is named "<section>_<order>" (e.g. "header_odd") and can be referenced in CSS as
     * "html_<section>_<order>". Placeholders are resolved against the template's current context.
     *
     * @param string $section Either 'header' or 'footer'
     * @param string $order The variant to load: 'first', 'odd' or 'even'
     * @return bool True if the template existed and the block was defined, false otherwise
     * @throws MpdfException
     */
    protected function defineNamedBlock(string $section, string $order): bool
    {
        $html = $this->template->getHTML($section, $order);
        if ($html === '') return false;

        $name = $section . '_' . $order;
        if ($section === 'header') {
            $this->mpdf->DefHTMLHeaderByName($name, $html);
        } else {
            $this->mpdf->DefHTMLFooterByName($name, $html);
        }
        return true;
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
     * Persist the generated PDF to the provided destination file.
     *
     * @param string $cacheFile Absolute file path that should receive the PDF output
     * @return void
     * @throws MpdfException
     */
    public function outputToFile(string $cacheFile): void
    {
        $this->mpdf->Output($cacheFile, 'F');
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
        int $mode = HTMLParserMode::DEFAULT_MODE,
        bool $init = true,
        bool $close = true
    ) {
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
