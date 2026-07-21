<?php

namespace dokuwiki\plugin\dw2pdf\src;

class PageCollector extends AbstractCollector
{
    /**
     * @inheritdoc
     * @throws ExportException When no page is given or the requested page does not exist
     */
    protected function collect(): array
    {
        $exportID = $this->getConfig()->getExportId();
        if ($exportID === '') {
            throw new ExportException('empty');
        }

        // no export for non existing page
        if (!page_exists($exportID, $this->rev)) {
            throw new ExportException('notexist');
        }

        return [$exportID];
    }
}
