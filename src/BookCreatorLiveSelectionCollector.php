<?php

namespace dokuwiki\plugin\dw2pdf\src;

class BookCreatorLiveSelectionCollector extends AbstractCollector
{

    /**
     * @inheritdoc
     * @throws \JsonException
     */
    protected function collect(): array
    {
        $selection = $this->getConfig()->getLiveSelection();
        if ($selection === null) return [];
        $list = json_decode($selection, true, 512, JSON_THROW_ON_ERROR);
        return (array) $list;
    }
}
