<?php

namespace dokuwiki\plugin\dw2pdf\src;

class PageCollector extends AbstractCollector
{
    /** @inheritdoc */
    protected function collect(): array
    {
        $exportID = $this->getConfig()->getExportId();
        if ($exportID === '') {
            return [];
        }

        // no export for non existing page
        if (!page_exists($exportID, $this->rev)) {
            return [];
        }

        return [$exportID];
    }
}
