<?php

namespace dokuwiki\plugin\dw2pdf\src;

class CollectorFactory
{

    /**
     * Returns the appropriate collector for the given export event
     *
     * @param string $event The name of the export event
     * @param int|null $rev A specific revision to export
     * @param int|null $at A specific dateat timestamp to export
     * @throws \InvalidArgumentException If the event is not recognized
     * @return AbstractCollector
     */
    static public function create(string $event, ?int $rev, ?int $at)
    {
        global $INPUT;

        switch ($event) {
            case 'export_page':
                return new PageCollector($rev, $at);
            case 'export_pdfns':
                return new NamespaceCollector($rev, $at);
            case 'export_pdfbook':
                if( $INPUT->has('selection') ) {
                    return new BookCreatorLiveSelectionCollector($rev, $at);
                } elseif($INPUT->has('savedselection')) {
                    return new BookCreatorSavedSelectionCollector($rev, $at);
                }
                // fallthrough
            default:
                throw new \InvalidArgumentException('Invalid export configuration');
        }
    }

}
