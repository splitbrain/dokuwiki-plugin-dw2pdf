<?php

namespace dokuwiki\plugin\dw2pdf\src;

class CollectorFactory
{
    /**
     * Returns the appropriate collector for the given export event
     *
     * @param string $event The name of the export event
     * @param Config $config Combined plugin and request configuration
     * @param int|null $rev A specific revision to export
     * @param int|null $at A specific dateat timestamp to export
     * @throws \InvalidArgumentException If the event is not recognized
     * @return AbstractCollector
     */
    public static function create(string $event, Config $config, ?int $rev, ?int $at)
    {
        switch ($event) {
            case 'export_pdf':
                return new PageCollector($config, $rev, $at);
            case 'export_pdfns':
                return new NamespaceCollector($config, $rev, $at); // $at would make sense but is not supported yet
            case 'export_pdfbook':
                if ($config->hasLiveSelection()) {
                    return new BookCreatorLiveSelectionCollector($config, $rev, $at);
                } elseif ($config->hasSavedSelection()) {
                    return new BookCreatorSavedSelectionCollector($config, $rev, $at);
                }
                // fallthrough
            default:
                throw new \InvalidArgumentException('Invalid export configuration');
        }
    }
}
