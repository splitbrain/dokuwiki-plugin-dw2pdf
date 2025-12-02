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
        /** @var action_plugin_bookcreator_handleselection $bcPlugin */
        $bcPlugin = plugin_load('action', 'bookcreator_handleselection');
        if (!$bcPlugin) return [];

        $savedSelectionId = $this->getConfig()->getSavedSelection();
        if ($savedSelectionId === null) return [];

        $savedselection = $bcPlugin->loadSavedSelection($savedSelectionId);
        if (!$this->title && !empty($savedselection['title'])) {
            $this->title = $savedselection['title'];
        }

        $list = (array) $savedselection['selection'];
        return array_filter($list, fn($page) => page_exists($page));
    }
}
