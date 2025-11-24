<?php

namespace dokuwiki\plugin\dw2pdf\src;

abstract class AbstractCollector
{
    /** @var string */
    protected string $title = '';

    /** @var string[] */
    protected array $pages = [];

    /** @var int|null */
    protected ?int $rev;
    /**
     * @var int|null
     */
    protected ?int $at;

    /**
     * Constructor
     */
    public function __construct(?int $rev = null, ?int $at = null)
    {
        global $INPUT;

        $this->rev = $rev;
        $this->at = $at;
        $this->title = $INPUT->str('book_title');

        // collected pages are cleaned and checked for read access
        $this->pages = array_filter(
            array_map('cleanID', $this->collect()),
            fn($page) => auth_quickaclcheck($page) >= AUTH_READ
        );
    }

    /**
     * Collect the pages to be included in the PDF
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
