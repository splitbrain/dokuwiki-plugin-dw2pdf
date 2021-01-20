<?php

namespace dokuwiki\plugin\dw2pdf;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * Class MenuItem
 *
 * Implements the PDF export button for DokuWiki's menu system
 *
 * @package dokuwiki\plugin\dw2pdf
 */
class MenuItem extends AbstractItem {

    /** @var string do action for this plugin */
    protected $type = 'export_pdf';

    /** @var string icon file */
    protected $svg = __DIR__ . '/file-pdf.svg';

    /** @var string template name */
    protected $template = '';

    /**
     * MenuItem constructor.
     */
    public function __construct($template = null) {
        parent::__construct();
        global $REV, $DATE_AT;

        if($DATE_AT) {
            $this->params['at'] = $DATE_AT;
        } elseif($REV) {
            $this->params['rev'] = $REV;
        }

        if(!is_null($template)) {
            $this->template = $template;
            $this->params['template'] = $template;
        }
    }

    /**
     * Get label from plugin language file
     *
     * @return string
     */
    public function getLabel() {
        $hlp = plugin_load('action', 'dw2pdf');

        $suffix = '';

        if(strlen($this->template) > 0) {
            $suffix = ' ('.$this->template.')';
        }

        return $hlp->getLang('export_pdf_button').$suffix;
    }
}
