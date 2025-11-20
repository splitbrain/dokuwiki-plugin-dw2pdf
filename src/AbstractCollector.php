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
        $this->pages = $this->collect();
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
     * Get the list of page ids to include in the PDF
     *
     * @return string[]
     */
    public function getPages(): array
    {
        return $this->pages;
    }
}
