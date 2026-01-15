<?php

namespace dokuwiki\plugin\dw2pdf\src;

use Mpdf\MpdfException;

/**
 * Coordinates PDF generation and delivery
 */
class PdfExportService
{
    /** @var Config */
    protected Config $config;

    /** @var AbstractCollector */
    protected AbstractCollector $collector;

    /** @var Cache */
    protected Cache $cache;

    /** @var string */
    protected string $tocHeader;

    /** @var string */
    protected string $remoteUser;

    /**
     * @param Config $config Active configuration controlling the export
     * @param AbstractCollector $collector Collector providing the export pages and metadata
     * @param Cache $cache Cache wrapper governing reuse and storage of the PDF file
     * @param string $tocHeader Localized header to display above the table of contents
     * @param string $remoteUser Authenticated user name for template context
     */
    public function __construct(
        Config $config,
        AbstractCollector $collector,
        Cache $cache,
        string $tocHeader,
        string $remoteUser = ''
    ) {
        $this->config = $config;
        $this->collector = $collector;
        $this->cache = $cache;
        $this->tocHeader = $tocHeader;
        $this->remoteUser = $remoteUser;
    }

    /**
     * Build the PDF (or return cached version) and provide its filesystem path.
     *
     * @return string
     * @throws MpdfException
     */
    public function getPdf(): string
    {
        if (!$this->config->useCache() || !$this->cache->useCache() || $this->config->isDebugEnabled()) {
            set_time_limit(0);
            $this->buildDocument($this->cache->cache);
        }

        return $this->cache->cache;
    }

    /**
     * Send the PDF to the browser. When $cacheFile is omitted, the PDF will be built (or loaded) first.
     *
     * @param string|null $cacheFile Absolute path to an already generated PDF file, if available
     * @return void
     * @throws MpdfException
     */
    public function sendPdf(?string $cacheFile = null): void
    {
        $cacheFile ??= $this->getPdf();
        $title = $this->collector->getTitle();

        header('Content-Type: application/pdf');
        header('Cache-Control: must-revalidate, no-transform, post-check=0, pre-check=0');
        header('Pragma: public');
        http_conditionalRequest(filemtime($cacheFile));

        $outputTarget = $this->config->getOutputTarget();
        $filename = rawurlencode(cleanID(strtr($title, ':/;"', '    ')));
        if ($outputTarget === 'file') {
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf";');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '.pdf";');
        }

        header('Set-Cookie: fileDownload=true; path=/');

        http_sendfile($cacheFile);

        $fp = @fopen($cacheFile, 'rb');
        if ($fp) {
            http_rangeRequest($fp, filesize($cacheFile), 'application/pdf');
        } else {
            header('HTTP/1.0 500 Internal Server Error');
            echo 'Could not read file - bad permissions?';
        }
        exit();
    }

    /**
     * Build the PDF document and write it to the cache file.
     *
     * @param string $cacheFile Destination path for the generated PDF file
     * @return void
     * @throws MpdfException
     */
    protected function buildDocument(string $cacheFile): void
    {
        $writer = $this->renderDocument();

        if ($this->config->isDebugEnabled()) {
            header('Content-Type: text/html; charset=utf-8');
            echo $writer->getDebugHTML();
            exit();
        }

        $writer->outputToFile($cacheFile);
    }

    /**
     * Build the PDF document without writing it to disk and expose the debug HTML.
     *
     * @return string Debug HTML collected while rendering the document
     * @throws MpdfException
     */
    public function getDebugHtml(): string
    {
        if (!$this->config->isDebugEnabled()) {
            throw new \RuntimeException('Debug HTML is only available when debug mode is enabled');
        }

        return $this->renderDocument()->getDebugHTML();
    }

    /**
     * Compose the document using the collector and return the writer.
     *
     * @return Writer Writer instance containing the rendered document
     * @throws MpdfException
     */
    protected function renderDocument(): Writer
    {
        $mpdf = new DokuPdf($this->config, $this->collector->getLanguage());
        $styles = new Styles($this->config);
        $template = new Template($this->config);
        $writer = new Writer($mpdf, $this->config, $template, $styles);

        $writer->startDocument($this->collector->getTitle());
        $writer->cover();

        if ($this->config->hasToC()) {
            $writer->toc($this->tocHeader);
        }

        foreach ($this->collector->getPages() as $page) {
            $template->setContext($this->collector, $page, $this->remoteUser);
            $writer->renderWikiPage($this->collector, $page);
        }

        $writer->back();
        $writer->endDocument();
        return $writer;
    }
}
