<?php

namespace dokuwiki\plugin\dw2pdf\src;

class Config
{

    protected string $tempDir = '';
    protected string $pagesize = 'A4';
    protected bool $isLandscape = false;
    protected int $fontSize = 11;
    protected bool $isDoublesided = false;
    protected bool $hasToC = false;
    protected array $tocLevels = [];
    protected int $maxBookmarks = 5;
    protected bool $numberedHeaders = false;
    protected string $watermark = '';
    protected string $template = 'default';
    protected bool $isDebug = false;
    protected array $useStyles = [];
    protected float $qrCodeScale = 0.0;

    /**
     * @param array $pluginConf Plugin configuration
     */
    public function __construct(array $pluginConf = [])
    {
        global $conf;
        $this->tempDir = $conf['tmpdir'] . '/mpdf';
        io_mkdir_p($this->tempDir);

        // set default ToC levels from main config
        $this->tocLevels = $this->parseTocLevels($conf['toptoclevel'] . '-' . $conf['maxtoclevel']);

        $this->loadPluginConfig($pluginConf);
        $this->loadInputConfig();
    }

    /**
     * Apply the given configuration
     *
     * @param array $conf Plugin configuration
     */
    public function loadPluginConfig(array $conf)
    {
        if (isset($conf['pagesize'])) $this->pagesize = $conf['pagesize'];
        if (isset($conf['orientation'])) $this->isLandscape = ($conf['orientation'] === 'landscape');
        if (isset($conf['font-size'])) $this->fontSize = (int)$conf['font-size'];
        if (isset($conf['doublesided'])) $this->isDoublesided = (bool)$conf['doublesided'];
        if (isset($conf['toc'])) $this->hasToC = (bool)$conf['toc'];
        if (isset($conf['toclevels'])) $this->tocLevels = $this->parseTocLevels($conf['toclevels']);
        if (isset($conf['maxbookmarks'])) $this->maxBookmarks = (int)$conf['maxbookmarks'];
        if (isset($conf['headernumber'])) $this->numberedHeaders = (bool)$conf['headernumber'];
        if (isset($conf['template'])) $this->template = $conf['template'];
        if (isset($conf['usestyles'])) {
            $this->useStyles = explode(',', $conf['usestyles']);
            $this->useStyles = array_map('trim', $this->useStyles);
            $this->useStyles = array_filter($this->useStyles);
        }
        if (isset($conf['watermark'])) $this->watermark = $conf['watermark']; // FIXME currently not in default config
        if (isset($conf['qrcodescale'])) $this->qrCodeScale = (float)$conf['qrcodescale'];
    }

    /**
     * Load configuration provided by INPUT parameters
     *
     * Not all parameters are overridable here
     *
     * @return void
     */
    public function loadInputConfig()
    {
        global $INPUT;
        $this->pagesize = $INPUT->str('pagesize', $this->pagesize);
        if ($INPUT->has('orientation')) {
            $this->isLandscape = $INPUT->str('orientation') === 'landscape';
        }
        $this->fontSize = $INPUT->int('font-size', $this->fontSize);
        $this->isDoublesided = $INPUT->bool('doublesided', $this->isDoublesided);
        if ($INPUT->has('toclevels')) {
            $this->tocLevels = $this->parseTocLevels($INPUT->str('toclevels'));
        }
        $this->watermark = $INPUT->str('watermark', $this->watermark);
        $this->isDebug = $INPUT->bool('debug', $this->isDebug);
    }

    /**
     * Check whether ToC is enabled
     *
     * @return bool
     */
    public function hasToc(): bool
    {
        return $this->hasToC;
    }

    /**
     * Check whether debug mode is enabled
     *
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return $this->isDebug;
    }

    /**
     * Get a list of extensions whose screen styles should be applied
     *
     * @return string[]
     */
    public function getStyledExtensions(): array
    {
        return $this->useStyles;
    }

    /**
     * Get the name of the selected template
     *
     * @return string
     */
    public function getTemplateName(): string
    {
        return $this->template;
    }

    /**
     * Get the maximum number of bookmarks to include
     *
     * @return int
     */
    public function getMaxBookmarks(): int
    {
        return $this->maxBookmarks;
    }

    /**
     * Check whether numbered headers are to be used
     *
     * @return bool
     */
    public function useNumberedHeaders(): bool
    {
        return $this->numberedHeaders;
    }

    /**
     * Get the QR code scale
     *
     * @return float
     */
    public function getQRScale(): float
    {
        return $this->qrCodeScale;
    }

    /**
     * Get a unique cache key for the current configuration
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return join(',', [
            $this->template,
            $this->pagesize,
            $this->isLandscape ? 'L' : 'P',
            $this->fontSize,
            $this->isDoublesided ? 'D' : 'S',
            $this->hasToC ? 'T' : 'N',
            $this->maxBookmarks,
            $this->numberedHeaders ? 'H' : 'N',
            implode('-', $this->tocLevels)
        ]);
    }

    /**
     * Parses the ToC levels configuration into an array
     *
     * @param string $toclevels eg. "2-4"
     * @return array
     */
    protected function parseTocLevels(string $toclevels): array
    {
        $levels = [];
        [$top, $max] = sexplode('-', $toclevels, 2);
        $top = max(1, min(5, (int)$top));
        $max = max(1, min(5, (int)$max));

        if ($max < $top) {
            $max = $top;
        }

        for ($level = $top; $level <= $max; $level++) {
            $levels["H$level"] = $level - 1;
        }

        return $levels;
    }


    /**
     * Return the paper format
     *
     * @return string
     */
    public function getFormat()
    {
        $format = $this->pagesize;
        if ($this->isLandscape) {
            $format .= '-L';
        }
        return $format;
    }

    /**
     * Return the watermark text if any
     *
     * @return string
     */
    public function getWatermarkText(): string
    {
        return $this->watermark;
    }

    /**
     * Get all configuration for mpdf as array
     *
     * Note: mode and wrtiting direction are set in DokuPDF based on the language
     *
     * @link https://mpdf.github.io/reference/mpdf-variables/overview.html
     * @return array
     */
    public function getMPdfConfig(): array
    {
        return [
            'format' => $this->getFormat(),
            'default_font_size' => $this->fontSize,
            'tempDir' => $this->tempDir,
            'mirrorMargins' => $this->isDoublesided,
            'h2toc' => $this->hasToC ? $this->tocLevels : [],
            'showWatermarkText' => $this->watermark !== '',

            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'autoScriptToLang' => true,
            'baseScript' => 1,
            'autoVietnamese' => true,
            'autoArabic' => true,
            'autoLangToFont' => true,
            'ignore_invalid_utf8' => true,
            'tabSpaces' => 4,
        ];
    }
}
