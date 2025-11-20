<?php

namespace dokuwiki\plugin\dw2pdf\src;

class PageCollector extends AbstractCollector
{

    /** @inheritdoc */
    protected function collect(): array
    {
        global $ID;

        // no export for non existing page
        if (!page_exists($ID, $this->rev)) {
            return [];
        }

        return [$ID];
    }

}
