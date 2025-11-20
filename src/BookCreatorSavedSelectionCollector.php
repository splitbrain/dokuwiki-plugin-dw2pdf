<?php

namespace dokuwiki\plugin\dw2pdf\src;

class BookCreatorSavedSelectionCollector extends AbstractCollector
{

    /**
     * @inheritdoc
     * @throws \JsonException
     */
    protected function collect(): array
    {
        global $INPUT;

        /** @var action_plugin_bookcreator_handleselection $bcPlugin */
        $bcPlugin = plugin_load('action', 'bookcreator_handleselection');
        if( !$bcPlugin ) return [];

        $savedselection = $bcPlugin->loadSavedSelection($INPUT->str('savedselection'));
        if(!$this->title && !empty($savedselection['title'])) {
            $this->title = $savedselection['title'];
        }

        return (array) $savedselection['selection'];
    }
}
