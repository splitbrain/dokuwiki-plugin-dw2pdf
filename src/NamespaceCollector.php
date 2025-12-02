<?php

namespace dokuwiki\plugin\dw2pdf\src;

use dokuwiki\Extension\Event;

class NamespaceCollector extends AbstractCollector
{
    protected string $namespace;
    protected $sortorder;
    protected int $depth;
    protected array $excludePages;
    protected array $excludeNamespaces;


    /**
     * Initialize variables from global input
     *
     * @return void
     * @throws \Exception
     */
    protected function initVars(): void
    {
        $config = $this->getConfig();

        $this->namespace = $config->getBookNamespace();
        $this->sortorder = $config->getBookSortOrder();
        $this->depth = $config->getBookNamespaceDepth();
        if ($this->depth < 0) $this->depth = 0;
        $this->excludePages = $config->getBookExcludedPages();
        $this->excludeNamespaces = $config->getBookExcludedNamespaces();

        // check namespace exists
        $nsdir = dirname(wikiFN($this->namespace . ':dummy'));
        if (!@is_dir($nsdir)) throw new \Exception('needns');
    }

    /**
     * @inheritdoc
     * @triggers DW2PDF_NAMESPACEEXPORT_SORT
     * @todo currently we do not support the 'at' parameter. We would need to search pages in the attic for this.
     */
    protected function collect(): array
    {
        global $conf;

        try {
            $this->initVars();
        } catch (\Exception $e) {
            return [];
        }

        //page search
        $result = [];
        $opts = ['depth' => $this->depth]; //recursive all levels
        $dir = utf8_encodeFN(str_replace(':', '/', $this->namespace));
        search($result, $conf['datadir'], 'search_allpages', $opts, $dir);

        // remove excluded pages and namespaces
        $result = $this->excludePages($result);



        // Sort pages, let plugins modify sorting
        $eventData = ['pages' => &$result, 'sort' => $this->sortorder];
        $event = new Event('DW2PDF_NAMESPACEEXPORT_SORT', $eventData);
        if ($event->advise_before()) {
            $result = $this->sortPages($result);
        }
        $event->advise_after();

        // extract page ids
        $pages = array_column($result, 'id');

        // if a there is a namespace start page outside the namespace, add it at the beginning
        if ($this->namespace !== '') {
            if (!in_array($this->namespace . ':' . $conf['start'], $pages, true)) {
                if (file_exists(wikiFN(rtrim($this->namespace, ':')))) {
                    array_unshift($pages, rtrim($this->namespace, ':'));
                }
            }
        }

        return $pages;
    }

    /**
     * Remove excluded pages and namespaces from the given list of pages
     *
     * @param array $pages The list of pages as returned by search()
     * @return array The filtered list of pages
     */
    protected function excludePages(array $pages)
    {
        $pages = array_filter($pages, fn($page) => !in_array($page['id'], $this->excludePages));
        $pages = array_filter($pages, function ($page) {
            foreach ($this->excludeNamespaces as $ns) {
                if (str_starts_with($page['id'], $ns . ':')) {
                    return false;
                }
            }
            return true;
        });
        return $pages;
    }

    /**
     * Sort the given list of pages according to the selected sort order
     *
     * @param array $pages The list of pages as returned by search()
     * @return array The sorted list of pages
     */
    protected function sortPages(array $pages): array
    {
        $sortoptions = ['pagename', 'date', 'natural'];
        if (!in_array($this->sortorder, $sortoptions)) {
            $this->sortorder = 'natural';
        }

        if ($this->sortorder == 'date') {
            usort($pages, [$this, 'cbDateSort']);
        } else {
            usort($pages, [$this, 'cbPagenameSort']);
        }

        return $pages;
    }

    /**
     * usort callback to sort by file lastmodified time
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    public function cbDateSort($a, $b)
    {
        if ($b['rev'] < $a['rev']) {
            return -1;
        }
        if ($b['rev'] > $a['rev']) {
            return 1;
        }
        return strcmp($b['id'], $a['id']);
    }

    /**
     * usort callback to sort by page id
     * @param array $a
     * @param array $b
     * @return int
     */
    public function cbPagenameSort($a, $b)
    {
        global $conf;

        $partsA = explode(':', $a['id']);
        $countA = count($partsA);
        $partsB = explode(':', $b['id']);
        $countB = count($partsB);
        $max = max($countA, $countB);


        // compare namepsace by namespace
        for ($i = 0; $i < $max; $i++) {
            $partA = $partsA[$i] ?: null;
            $partB = $partsB[$i] ?: null;

            // have we reached the page level?
            if ($i === ($countA - 1) || $i === ($countB - 1)) {
                // start page first
                if ($partA == $conf['start']) {
                    return -1;
                }
                if ($partB == $conf['start']) {
                    return 1;
                }
            }

            // prefer page over namespace
            if ($partA === $partB) {
                if (!isset($partsA[$i + 1])) {
                    return -1;
                }
                if (!isset($partsB[$i + 1])) {
                    return 1;
                }
                continue;
            }


            // simply compare
            return strnatcmp($partA, $partB);
        }

        return strnatcmp($a['id'], $b['id']);
    }
}
