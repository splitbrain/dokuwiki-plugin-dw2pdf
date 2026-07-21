<?php

namespace dokuwiki\plugin\dw2pdf\src;

abstract class AbstractCollector
{
    /** @var string */
    protected string $title = '';

    /** @var string[] */
    protected array $pages = [];

    /** @var string[] Pages removed from the selection because of missing read access */
    protected array $skipped = [];

    /** @var int|null */
    protected ?int $rev;
    /**
     * @var int|null
     */
    protected ?int $at;
    protected Config $config;

    /**
     * Collect the pages for this export and partition them by read access
     *
     * Pages the current user may not read are skipped but remembered, so callers can tell an
     * empty selection apart from one where every selected page was forbidden.
     *
     * @param Config $config Combined plugin and request configuration
     * @param int|null $rev Specific revision to export, if any
     * @param int|null $at Specific dateat timestamp to export, if any
     */
    public function __construct(Config $config, ?int $rev = null, ?int $at = null)
    {
        $this->config = $config;
        $this->rev = $rev;
        $this->at = $at;
        $this->title = $config->getBookTitle() ?? '';

        // clean and partition collected pages into readable and forbidden ones in a single pass
        foreach ($this->collect() as $page) {
            $page = cleanID($page);
            if (auth_quickaclcheck($page) >= AUTH_READ) {
                $this->pages[] = $page;
            } else {
                $this->skipped[] = $page;
            }
        }
    }

    /**
     * Get the combined configuration/request context for this export.
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Collect the pages to be included in the PDF
     *
     * The collected pages will be cleaned and checked for read access automatically.
     *
     * This method should check for page existence, though (might depend on $rev/$at).
     *
     * @return string[] The list of page ids
     */
    abstract protected function collect(): array;

    /**
     * Get the title to be used for the PDF
     *
     * @return string
     */
    public function getTitle(): string
    {
        if (!$this->title && $this->pages) {
            $this->title = p_get_first_heading($this->pages[0]) ?: noNS($this->pages[0]);
        }

        if (!$this->title) {
            $this->title = 'PDF Export';
        }

        return $this->title;
    }

    /**
     * Get the language to be used for the PDF
     *
     * Use the language of the first page if possible, otherwise fall back to the default language
     *
     * @return string
     */
    public function getLanguage()
    {
        global $conf;

        $lang = $conf['lang'];
        if ($this->pages == []) return $lang;


        /** @var helper_plugin_translation $trans */
        $trans = plugin_load('helper', 'translation');
        if (!$trans) return $lang;
        $tr = $trans->getLangPart($this->pages[0]);
        if ($tr) return $tr;

        return $lang;
    }

    /**
     * Get the set revision if any
     *
     * @return int|null
     */
    public function getRev(): ?int
    {
        return $this->rev;
    }

    /**
     * Get the set dateat timestamp if any
     *
     * @return int|null
     */
    public function getAt(): ?int
    {
        return $this->at;
    }

    /**
     * Get the list of page ids to include in the PDF
     *
     * @return string[]
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * Get the pages that were removed from the selection because of missing read access
     *
     * @return string[]
     */
    public function getSkippedPages(): array
    {
        return $this->skipped;
    }

    /**
     * Get the list of file paths to include in the PDF
     *
     * Handles $rev if set
     *
     * @return string[]
     * @todo no handling of $at yet
     */
    public function getFiles(): array
    {
        return array_map(fn($id) => wikiFN($id, $this->rev), $this->pages);
    }
}
