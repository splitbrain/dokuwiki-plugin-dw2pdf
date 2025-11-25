<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\dw2pdf\MenuItem;
use dokuwiki\plugin\dw2pdf\src\AbstractCollector;
use dokuwiki\plugin\dw2pdf\src\Cache;
use dokuwiki\plugin\dw2pdf\src\CollectorFactory;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\DokuPdf;
use dokuwiki\plugin\dw2pdf\src\Styles;
use dokuwiki\plugin\dw2pdf\src\Template;
use dokuwiki\plugin\dw2pdf\src\Writer;
use Mpdf\MpdfException;

/**
 * dw2Pdf Plugin: Conversion from dokuwiki content to pdf.
 *
 * Export html content to pdf, for different url parameter configurations
 * DokuPDF which extends mPDF is used for generating the pdf from html.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
class action_plugin_dw2pdf extends ActionPlugin
{

    protected $currentBookChapter = 0;

    /**
     * Return the value of currentBookChapter, which is the order of the file to be added in a book generation
     */
    public function getCurrentBookChapter()
    {
        return $this->currentBookChapter;
    }

    /**
     * Register the events
     *
     * @param EventHandler $controller
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'convert');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addsvgbutton');
    }

    /**
     * Do the HTML to PDF conversion work
     *
     * @param Event $event
     */
    public function convert(Event $event)
    {
        global $REV, $DATE_AT;

        // our event?
        $allowedEvents = ['export_pdfbook', 'export_pdf', 'export_pdfns'];
        if (!in_array($event->data, $allowedEvents)) {
            return;
        }

        $this->loadConfig();
        $config = new Config($this->conf);
        $collector = CollectorFactory::create($event->data, $REV, $DATE_AT);
        $cache = new Cache($config, $collector);


        if (!$cache->useCache()) {
            // generating the pdf may take a long time for larger wikis / namespaces with many pages
            set_time_limit(0);

            try {
                $this->generatePDF($config, $collector, $cache->cache, $event);
            } catch (Exception $e) {
                // FIXME should we log here?
                // FIXME there was special handling for BookCreator with $INPUT->has('selection') before
                nice_die($e->getMessage());
            }
        }

        $event->preventDefault(); // after prevent, $event->data cannot be changed

        // deliver the file
        $this->sendPDFFile($cache->cache);  //exits
    }


    /**
     * Returns the parsed Wikitext in dw2pdf for the given id and revision
     *
     * @param string $id page id
     * @param string|int $rev revision timestamp or empty string
     * @param string $date_at
     * @return null|string
     */
    protected function wikiToDW2PDF($id, $rev = '', $date_at = '')
    {
        $file = wikiFN($id, $rev);

        if (!file_exists($file)) {
            return '';
        }

        //ensure $id is in global $ID (needed for parsing)
        global $ID;
        $keep = $ID;
        $ID = $id;

        if ($rev || $date_at) {
            //no caching on old revisions
            $ret = p_render('dw2pdf', p_get_instructions(io_readWikiPage($file, $id, $rev)), $info, $date_at);
        } else {
            $ret = p_cached_output($file, 'dw2pdf', $id);
        }

        //restore ID (just in case)
        $ID = $keep;

        return $ret;
    }

    /**
     * Build a pdf from the html
     *
     * @param string $cachefile
     * @param Event $event
     * @throws MpdfException
     */
    protected function generatePDF(Config $config, AbstractCollector $collector, $cachefile, $event)
    {
        global $INPUT;

        $mpdf = new DokuPDF($config, $collector->getLanguage());
        $styles = new Styles($config);
        $template = new Template($config);
        $writer = new Writer($mpdf, $config, $template, $styles, $config->isDebugEnabled());

        $writer->startDocument($collector->getTitle());
        $writer->cover();

        if ($config->hasToC()) {
            $writer->toc($this->getLang('tocheader'));
        }

        // loop over all pages
        $counter = 0;
        foreach ($collector->getPages() as $page) {
            $template->setContext($collector, $page, $INPUT->server->str('REMOTE_USER', '', true));
            $this->currentBookChapter = $counter++;  //FIXME I don't like this
            $writer->renderWikiPage($collector, $page);
        }

        // insert the back page
        $writer->back();
        $writer->endDocument();

        //Return html for debugging
        if ($config->isDebugEnabled()) {
            header('Content-Type: text/html; charset=utf-8');
            echo $writer->getDebugHTML();
            exit();
        }

        // write to cache file
        $mpdf->Output($cachefile, 'F');
    }

    /**
     * @param string $cachefile
     */
    protected function sendPDFFile($cachefile)
    {
        header('Content-Type: application/pdf');
        header('Cache-Control: must-revalidate, no-transform, post-check=0, pre-check=0');
        header('Pragma: public');
        http_conditionalRequest(filemtime($cachefile));
        global $INPUT;
        $outputTarget = $INPUT->str('outputTarget', $this->getConf('output'));

        $filename = rawurlencode(cleanID(strtr($this->title, ':/;"', '    ')));
        if ($outputTarget === 'file') {
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf";');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '.pdf";');
        }

        //Bookcreator uses jQuery.fileDownload.js, which requires a cookie.
        header('Set-Cookie: fileDownload=true; path=/');

        //try to send file, and exit if done
        http_sendfile($cachefile);

        $fp = @fopen($cachefile, "rb");
        if ($fp) {
            http_rangeRequest($fp, filesize($cachefile), 'application/pdf');
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            echo "Could not read file - bad permissions?";
        }
        exit();
    }

    /**
     * Returns array of pages which will be included in the exported pdf
     *
     * @return array
     */
    public function getExportedPages()
    {
        return $this->list;
    }


    /**
     * Collects settings from:
     *   1. url parameters
     *   2. plugin config
     *   3. global config
     */
    protected function loadExportConfig()
    {
        global $INPUT;
        global $conf;

        $this->exportConfig = [];

        // decide on the paper setup from param or config
        $this->exportConfig['pagesize'] = $INPUT->str('pagesize', $this->getConf('pagesize'), true);
        $this->exportConfig['orientation'] = $INPUT->str('orientation', $this->getConf('orientation'), true);

        // decide on the font-size from param or config
        $this->exportConfig['font-size'] = $INPUT->str('font-size', $this->getConf('font-size'), true);

        $doublesided = $INPUT->bool('doublesided', (bool)$this->getConf('doublesided'));
        $this->exportConfig['doublesided'] = $doublesided ? '1' : '0';

        $this->exportConfig['watermark'] = $INPUT->str('watermark', '');

        $hasToC = $INPUT->bool('toc', (bool)$this->getConf('toc'));
        $levels = [];
        if ($hasToC) {
            $toclevels = $INPUT->str('toclevels', $this->getConf('toclevels'), true);
            [$top_input, $max_input] = array_pad(explode('-', $toclevels, 2), 2, '');
            [$top_conf, $max_conf] = array_pad(explode('-', $this->getConf('toclevels'), 2), 2, '');
            $bounds_input = [
                'top' => [
                    (int)$top_input,
                    (int)$top_conf
                ],
                'max' => [
                    (int)$max_input,
                    (int)$max_conf
                ]
            ];
            $bounds = [
                'top' => $conf['toptoclevel'],
                'max' => $conf['maxtoclevel']

            ];
            foreach ($bounds_input as $bound => $values) {
                foreach ($values as $value) {
                    if ($value > 0 && $value <= 5) {
                        //stop at valid value and store
                        $bounds[$bound] = $value;
                        break;
                    }
                }
            }

            if ($bounds['max'] < $bounds['top']) {
                $bounds['max'] = $bounds['top'];
            }

            for ($level = $bounds['top']; $level <= $bounds['max']; $level++) {
                $levels["H$level"] = $level - 1;
            }
        }
        $this->exportConfig['hasToC'] = $hasToC;
        $this->exportConfig['levels'] = $levels;

        $this->exportConfig['maxbookmarks'] = $INPUT->int('maxbookmarks', $this->getConf('maxbookmarks'), true);

        $tplconf = $this->getConf('template');
        $tpl = $INPUT->str('tpl', $tplconf, true);
        if (!is_dir(DOKU_PLUGIN . 'dw2pdf/tpl/' . $tpl)) {
            $tpl = $tplconf;
        }
        if (!$tpl) {
            $tpl = 'default';
        }
        $this->exportConfig['template'] = $tpl;

        $this->exportConfig['isDebug'] = $conf['allowdebug'] && $INPUT->has('debughtml');
    }

    /**
     * Returns requested config
     *
     * @param string $name
     * @param mixed $notset
     * @return mixed|bool
     */
    public function getExportConfig($name, $notset = false)
    {
        if ($this->exportConfig === null) {
            $this->loadExportConfig();
        }

        return $this->exportConfig[$name] ?? $notset;
    }

    /**
     * Add 'export pdf' button to page tools, new SVG based mechanism
     *
     * @param Event $event
     */
    public function addsvgbutton(Event $event)
    {
        global $INFO;
        if ($event->data['view'] != 'page' || !$this->getConf('showexportbutton')) {
            return;
        }

        if (!$INFO['exists']) {
            return;
        }

        array_splice($event->data['items'], -1, 0, [new MenuItem()]);
    }


}
