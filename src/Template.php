<?php

namespace dokuwiki\plugin\dw2pdf\src;

use dokuwiki\Extension\Event;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

class Template
{
    /** @var string The name of the template */
    protected string $name = 'default';

    /** @var string The directory where the template is stored */
    protected string $dir = '';

    /** @var float The scale of the QR code to generate (0.0 to disable) */
    protected float $qrScale = 0.0;

    /** @var bool Whether we are processing the first page */
    protected bool $isFirstPage = true;

    /** @var array The context of the currently processed page. Used for placeholder replacements */
    protected array $context = [
        'id' => '',
        'rev' => '',
        'at' => '',
        'title' => '',
        'username' => '',
    ];


    /**
     * Constructor
     *
     * @param Config $config The DW2PDF configuration
     */
    public function __construct(Config $config)
    {
        $this->name = $config->getTemplateName();
        $this->qrScale = $config->getQRScale();
        $this->dir = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->name;
        if (!is_dir($this->dir)) {
            throw new \RuntimeException("Template directory $this->dir does not exist");
        }
    }

    /**
     * Set the context for the current page
     *
     * This will be used in placeholders in headers/footers/cover/back pages.
     *
     * @param string $id The page ID
     * @param string $title The title of the page
     * @param string|null $rev The revision of the page (if any)
     * @param string|null $at The dateat mechanism in use (if any)
     * @param string|null $username The username of the user generating the PDF (if any)
     * @return void
     */
    public function setContext(AbstractCollector $collector, string $id, ?string $username): void
    {
        $this->context = [
            'title' => $collector->getTitle(),
            'id' => $id,
            'rev' => $collector->getRev() ?? '',
            'at' => $collector->getAt() ?? '',
            'username' => $username ?? '',
        ];
    }

    /**
     * Get the HTML content for the given type and order
     *
     * Will fall back to non-ordered version if ordered version is not found. Placeholders
     * will be replaced.
     *
     * @param string $type header, footer, cover, back, citation
     * @param string $order first, even, odd or empty string for default
     * @return string
     */
    public function getHTML(string $type, string $order = ''): string
    {
        if ($order) $order = "_$order";

        $file = $this->dir . '/' . $type . $order . '.html';
        if (!is_file($file)) $file = $this->dir . '/' . $type . '.html';
        if (!is_file($file)) return '';

        $html = file_get_contents($file);
        $html = $this->replacePlaceholders($html);
        return $html;
    }

    /**
     * Applies the placeholder replacements to the given HTML
     *
     * Called for headers, footers, cover and back pages on each call.
     *
     * Accesses global DokuWiki variables to fill in page specific data.
     *
     * @triggers PLUGIN_DW2PDF_REPLACE
     * @param string $html The template's HTML content
     * @return string The HTML with placeholders replaced
     */
    protected function replacePlaceholders(string $html): string
    {
        global $conf;

        $params = [];
        if(!empty($this->context['at'])) {
            $params['at'] = $this->context['at'];
        } elseif (!empty($this->context['rev'])) {
            $params['rev'] = $this->context['rev'];
        }
        $url = wl($this->context['id'], $params, true, "&");

        $replace = [
            '@PAGE@' => '{PAGENO}',
            '@PAGES@' => '{nbpg}', //see also $mpdf->pagenumSuffix = ' / '
            '@TITLE@' => hsc($this->context['title'] ?? ''),
            '@WIKI@' => $conf['title'],
            '@WIKIURL@' => DOKU_URL,
            '@DATE@' => dformat(time()),
            '@USERNAME@' => hsc($this->context['username'] ?? ''),
            '@BASE@' => DOKU_BASE,
            '@INC@' => DOKU_INC,
            '@TPLBASE@' => DOKU_BASE . 'lib/plugins/dw2pdf/tpl/' . $this->name . '/',
            '@TPLINC@' => DOKU_INC . 'lib/plugins/dw2pdf/tpl/' . $this->name . '/',
            // page dependent placeholders
            '@ID' => $this->context['id'] ?? '',
            '@UPDATE@' => dformat(filemtime(wikiFN($this->context['id'], $this->context['rev'] ?? ''))),
            '@PAGEURL@' => $url,
            '@QRCODE@' => $this->generateQRCode($url),
        ];

        // let other plugins define their own replacements
        $evdata = [
            'id' => $this->context['id'],
            'replace' => &$replace,
            'content' => &$html,
            'context' => $this->context
        ];
        $event = new Event('PLUGIN_DW2PDF_REPLACE', $evdata);
        if ($event->advise_before()) {
            $html = str_replace(array_keys($replace), array_values($replace), $html);
        }
        // plugins may post-process HTML, e.g to clean up unused replacements
        $event->advise_after();

        // @DATE(<date>[, <format>])@
        $html = preg_replace_callback(
            '/@DATE\((.*?)(?:,\s*(.*?))?\)@/',
            function ($match) {
                global $conf;
                //no 2nd argument for default date format
                if ($match[2] == null) {
                    $match[2] = $conf['dformat'];
                }
                return strftime($match[2], strtotime($match[1]));
            },
            $html
        );

        return $html;
    }

    /**
     * Generate QR code pseudo-HTML
     *
     * @param string $url The URL to encode
     * @return string
     */
    protected function generateQRCode($url): string
    {
        if ($this->qrScale <= 0.0) return '';

        $url = hsc($url);
        return sprintf(
            '<barcode type="QR" code="%s" error="Q" disableborder="1" class="qrcode" size="%s" />',
            $url,
            $this->qrScale
        );
    }
}
