<?php

use dokuwiki\Cache\Cache;
use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\dw2pdf\MenuItem;
use dokuwiki\plugin\dw2pdf\src\CollectorFactory;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\DokuPdf;
use dokuwiki\plugin\dw2pdf\src\Styles;
use dokuwiki\plugin\dw2pdf\src\Template;
use dokuwiki\plugin\dw2pdf\src\Writer;
use dokuwiki\StyleUtils;
use Mpdf\HTMLParserMode;
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
    /**
     * Settings for current export, collected from url param, plugin config, global config
     *
     * @var array
     */
    protected $exportConfig;
    /** @var string template name, to use templates from dw2pdf/tpl/<template name> */
    protected $tpl;
    /** @var string title of exported pdf */
    protected $title;
    /** @var array list of pages included in exported pdf */
    protected $list = [];
    /** @var bool|string path to temporary cachefile */
    protected $onetimefile = false;
    protected $currentBookChapter = 0;

    /**
     * Constructor. Sets the correct template
     */
    public function __construct()
    {
        require_once __DIR__ . '/vendor/autoload.php';

        $this->tpl = $this->getExportConfig('template');
    }

    /**
     * Delete cached files that were for one-time use
     */
    public function __destruct()
    {
        if ($this->onetimefile) {
            unlink($this->onetimefile);
        }
    }

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
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'addbutton');
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
        global $conf, $INPUT;

        // our event?
        $allowedEvents = ['export_pdfbook', 'export_pdf', 'export_pdfns'];
        if (!in_array($event->data, $allowedEvents)) {
            return;
        }

        try {
            //collect pages and check permissions
            [$this->title, $this->list] = $this->collectExportablePages($event);

            if ($event->data === 'export_pdf' && ($REV || $DATE_AT)) {
                $cachefile = tempnam($conf['tmpdir'] . '/dwpdf', 'dw2pdf_');
                $this->onetimefile = $cachefile;
                $generateNewPdf = true;
            } else {
                // prepare cache and its dependencies
                $depends = [];
                $cache = $this->prepareCache($depends);
                $cachefile = $cache->cache;
                $generateNewPdf = !$this->getConf('usecache')
                    || $this->getExportConfig('isDebug')
                    || !$cache->useCache($depends);
            }

            // hard work only when no cache available or needed for debugging
            if ($generateNewPdf) {
                // generating the pdf may take a long time for larger wikis / namespaces with many pages
                set_time_limit(0);
                //may throw Mpdf\MpdfException as well
                $this->generatePDF($cachefile, $event);
            }
        } catch (Exception $e) {
            if ($INPUT->has('selection')) {
                http_status(400);
                echo $e->getMessage();
                exit();
            } else {
                //prevent Action/Export()
                msg($e->getMessage(), -1);
                $event->data = 'redirect';
                return;
            }
        }
        $event->preventDefault(); // after prevent, $event->data cannot be changed

        // deliver the file
        $this->sendPDFFile($cachefile);  //exits
    }

    /**
     * Obtain list of pages and title, for different methods of exporting the pdf.
     *  - Return a title and selection, throw otherwise an exception
     *  - Check permisions
     *
     * @param Event $event
     * @return array
     * @throws Exception
     */
    protected function collectExportablePages(Event $event)
    {
        global $REV, $DATE_AT;
        global $INPUT;

        $collector = CollectorFactory::create($event->data, $REV, $DATE_AT);
        $list = $collector->getPages();
        $title = $collector->getTitle();

        $list = array_map('cleanID', $list);

        $skippedpages = [];
        foreach ($list as $index => $pageid) {
            if (auth_quickaclcheck($pageid) < AUTH_READ) {
                $skippedpages[] = $pageid;
                unset($list[$index]);
            }
        }
        $list = array_filter($list, 'strlen'); //use of strlen() callback prevents removal of pagename '0'

        //if selection contains forbidden pages throw (overridable) warning
        if (!$INPUT->bool('book_skipforbiddenpages') && $skippedpages !== []) {
            $msg = hsc(implode(', ', $skippedpages));
            throw new Exception(sprintf($this->getLang('forbidden'), $msg));
        }

        return [$title, $list];
    }

    /**
     * Prepare cache
     *
     * @param array $depends (reference) array with dependencies
     * @return cache
     */
    protected function prepareCache(&$depends)
    {
        global $REV;

        $cachekey = implode(',', $this->list)
            . $REV
            . $this->getExportConfig('template')
            . $this->getExportConfig('pagesize')
            . $this->getExportConfig('orientation')
            . $this->getExportConfig('font-size')
            . $this->getExportConfig('doublesided')
            . $this->getExportConfig('headernumber')
            . ($this->getExportConfig('hasToC') ? implode('-', $this->getExportConfig('levels')) : '0')
            . $this->title;
        $cache = new Cache($cachekey, '.dw2.pdf');

        $dependencies = [];
        foreach ($this->list as $pageid) {
            $relations = p_get_metadata($pageid, 'relation');

            if (is_array($relations)) {
                if (array_key_exists('media', $relations) && is_array($relations['media'])) {
                    foreach ($relations['media'] as $mediaid => $exists) {
                        if ($exists) {
                            $dependencies[] = mediaFN($mediaid);
                        }
                    }
                }

                if (array_key_exists('haspart', $relations) && is_array($relations['haspart'])) {
                    foreach ($relations['haspart'] as $part_pageid => $exists) {
                        if ($exists) {
                            $dependencies[] = wikiFN($part_pageid);
                        }
                    }
                }
            }

            $dependencies[] = metaFN($pageid, '.meta');
        }

        $depends['files'] = array_map('wikiFN', $this->list);
        $depends['files'][] = __FILE__;
        $depends['files'][] = __DIR__ . '/renderer.php';
        $depends['files'][] = __DIR__ . '/mpdf/mpdf.php';
        $depends['files'] = array_merge(
            $depends['files'],
            $dependencies,
            getConfigFiles('main')
        );
        return $cache;
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
    protected function generatePDF($cachefile, $event)
    {
        global $REV, $INPUT, $DATE_AT;

        if ($event->data == 'export_pdf') { //only one page is exported
            $rev = $REV;
            $date_at = $DATE_AT;
        } else {
            //we are exporting entire namespace, ommit revisions
            $rev = '';
            $date_at = '';
        }

        $config = new Config($this->conf, $this->getDocumentLanguage($this->list[0]));
        $mpdf = new DokuPDF($config);
        $styles = new Styles($config);
        $template = new Template($this->getConf('template'), $this->getConf('qrcodescale'));
        $writer = new Writer($mpdf, $template, $styles, $config->isDebugEnabled());

        $writer->startDocument($this->title);
        $writer->cover();

        if($config->hasToC()) {
            $writer->toc($this->getLang('tocheader'));
        }

        // loop over all pages
        $counter = 0;
        foreach ($this->list as $page) {
            $template->setContext($page, $this->title, $rev, $date_at, $INPUT->server->str('REMOTE_USER', '', true));

            $this->currentBookChapter = $counter;
            $counter++;
            $pagehtml = $this->wikiToDW2PDF($page, $rev, $date_at);
            $writer->wikiPage($pagehtml);
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
     * Add 'export pdf'-button to pagetools
     *
     * @param Event $event
     */
    public function addbutton(Event $event)
    {
        global $ID, $REV, $DATE_AT;

        if ($this->getConf('showexportbutton') && $event->data['view'] == 'main') {
            $params = ['do' => 'export_pdf'];
            if ($DATE_AT) {
                $params['at'] = $DATE_AT;
            } elseif ($REV) {
                $params['rev'] = $REV;
            }

            // insert button at position before last (up to top)
            $event->data['items'] = array_slice($event->data['items'], 0, -1, true) +
                ['export_pdf' => sprintf(
                    '<li><a href="%s" class="%s" rel="nofollow" title="%s"><span>%s</span></a></li>',
                    wl($ID, $params),
                    'action export_pdf',
                    $this->getLang('export_pdf_button'),
                    $this->getLang('export_pdf_button')
                )] +
                array_slice($event->data['items'], -1, 1, true);
        }
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

    /**
     * Get the language of the current document
     *
     * Uses the translation plugin if available
     * @return string
     */
    protected function getDocumentLanguage($pageid)
    {
        global $conf;

        $lang = $conf['lang'];
        /** @var helper_plugin_translation $trans */
        $trans = plugin_load('helper', 'translation');
        if ($trans) {
            $tr = $trans->getLangPart($pageid);
            if ($tr) {
                $lang = $tr;
            }
        }

        return $lang;
    }
}
