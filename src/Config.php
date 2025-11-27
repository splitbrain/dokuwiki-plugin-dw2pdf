<?php

namespace dokuwiki\plugin\dw2pdf\src;

use dokuwiki\plugin\dw2pdf\src\attributes\FromConfig;
use dokuwiki\plugin\dw2pdf\src\attributes\FromInput;

class Config
{
    protected string $tempDir = '';

    // General PDF configuration
    #[FromConfig, FromInput]
    protected string $pagesize = 'A4';
    #[FromConfig('orientation'), FromInput('orientation')]
    protected bool $isLandscape = false;
    #[FromConfig('font-size'), FromInput('font-size')]
    protected int $fontSize = 11;
    #[FromConfig('doublesided'), FromInput('doublesided')]
    protected bool $isDoublesided = false;
    #[FromConfig('toc')]
    protected bool $hasToC = false;
    #[FromConfig, FromInput('toclevels')]
    protected array $tocLevels = [];
    #[FromConfig]
    protected int $maxBookmarks = 5;
    #[FromConfig('headernumber')]
    protected bool $numberedHeaders = false;
    #[FromConfig, FromInput]
    protected string $watermark = '';
    #[FromConfig('tpl'), FromInput]
    protected string $template = 'default';
    #[FromConfig('debug'), FromInput('debug')]
    protected bool $isDebug = false;
    #[FromConfig]
    protected array $useStyles = [];
    #[FromConfig]
    protected float $qrCodeScale = 0.0;
    #[FromConfig, FromInput('outputTarget')]
    protected string $outputTarget = 'file';

    // Collector-specific request data
    #[FromConfig, FromInput('book_title')]
    protected ?string $bookTitle = null;
    #[FromConfig, FromInput('book_ns')]
    protected string $bookNamespace = '';
    #[FromConfig, FromInput('book_order')]
    protected string $bookSortOrder = 'natural';
    #[FromConfig, FromInput('book_nsdepth')]
    protected int $bookNamespaceDepth = 0;
    #[FromConfig, FromInput('excludes')]
    protected array $bookExcludePages = [];
    #[FromConfig, FromInput('excludesns')]
    protected array $bookExcludeNamespaces = [];
    #[FromConfig, FromInput('selection')]
    protected ?string $liveSelection = null;
    #[FromConfig, FromInput('savedselection')]
    protected ?string $savedSelection = null;
    #[FromConfig, FromInput('id')]
    protected string $exportId = '';

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
     * Set a property with type casting and custom parsing
     *
     * @param string $prop The property name to set
     * @param string|null $type The property type
     * @param mixed $value The value to set
     * @return void
     * @see loadPluginConfig
     * @see loadInputConfig
     */
    protected function setProperty(string $prop, ?string $type, $value)
    {
        // custom parsing
        $value = match ($prop) {
            'isLandscape' => ($value === 'landscape'),
            'toclevels' => $this->parseTocLevels((string)$value),
            'exportId' => cleanID((string)$value),
            default => $value,
        };

        // standard type casting
        $this->$prop = match ($type) {
            'int' => (int)$value,
            'bool' => (bool)$value,
            'float' => (float)$value,
            'array' => is_array($value)
                ? $value
                : array_filter(array_map('trim', explode(',', (string)$value))),
            default => $value,
        };
    }

    /**
     * Apply the given configuration
     *
     * This will set all properties annotated with FromConfig
     *
     * @param array $conf (Plugin) configuration
     */
    public function loadPluginConfig(array $conf = [])
    {
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(FromConfig::class);
            if ($attributes === []) continue;
            $attribute = $attributes[0]->newInstance(); // we only expect one

            $prop = $property->getName();
            $confName = $attribute->name ?? strtolower($prop);
            $type = $property->getType()?->getName();

            if (!isset($conf[$confName])) continue;
            $this->setProperty($prop, $type, $conf[$confName]);
        }
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
        global $INPUT, $ID;

        if ($ID) $this->exportId = $ID; // default exportId to current page ID

        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(FromInput::class);
            if ($attributes === []) continue;
            $attribute = $attributes[0]->newInstance(); // we only expect one

            $prop = $property->getName();
            $confName = $attribute->name ?? strtolower($prop);
            $type = $property->getType()?->getName();

            if (!$INPUT->has($confName)) continue;
            $this->setProperty($prop, $type, $INPUT->param($confName));
        }
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
     * Get desired PDF delivery target (inline or file download)
     *
     * @return string
     */
    public function getOutputTarget(): string
    {
        return $this->outputTarget;
    }

    /**
     * Get a unique cache key for the current configuration
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return implode(',', [
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
     * Note: mode and writing direction are set in DokuPDF based on the language
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

    /**
     * Get the requested book title override if provided.
     *
     * @return string|null
     */
    public function getBookTitle(): ?string
    {
        return $this->bookTitle;
    }

    /**
     * Get the namespace to export for namespace/book selections.
     *
     * @return string
     */
    public function getBookNamespace(): string
    {
        return $this->bookNamespace;
    }

    /**
     * Get the page sort order selected for namespace exports.
     *
     * @return string
     */
    public function getBookSortOrder(): string
    {
        return $this->bookSortOrder;
    }

    /**
     * Get the maximum namespace depth to traverse for namespace exports.
     *
     * @return int
     */
    public function getBookNamespaceDepth(): int
    {
        return $this->bookNamespaceDepth;
    }

    /**
     * Get the list of explicitly excluded page IDs.
     *
     * @return string[]
     */
    public function getBookExcludedPages(): array
    {
        return $this->bookExcludePages;
    }

    /**
     * Get the list of excluded namespaces.
     *
     * @return string[]
     */
    public function getBookExcludedNamespaces(): array
    {
        return $this->bookExcludeNamespaces;
    }

    /**
     * Get the raw JSON payload representing a live book selection.
     *
     * @return string|null
     */
    public function getLiveSelection(): ?string
    {
        return $this->liveSelection;
    }

    /**
     * Check whether a live selection payload was supplied.
     *
     * @return bool
     */
    public function hasLiveSelection(): bool
    {
        return $this->liveSelection !== null;
    }

    /**
     * Get the identifier of a saved book selection.
     *
     * @return string|null
     */
    public function getSavedSelection(): ?string
    {
        return $this->savedSelection;
    }

    /**
     * Check whether a saved selection identifier was supplied.
     *
     * @return bool
     */
    public function hasSavedSelection(): bool
    {
        return $this->savedSelection !== null;
    }

    /**
     * Get the requested page ID for single page exports.
     *
     * @return string
     */
    public function getExportId(): string
    {
        return $this->exportId;
    }
}
