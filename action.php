<?php

use dokuwiki\Cache\Cache;
use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\dw2pdf\MenuItem;
use dokuwiki\StyleUtils;
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
        global $ID, $REV;
        global $INPUT;
        global $conf, $lang;

        // list of one or multiple pages
        $list = [];

        if ($event->data == 'export_pdf') {
            if (auth_quickaclcheck($ID) < AUTH_READ) {  // set more specific denied message
                throw new Exception($lang['accessdenied']);
            }
            $list[0] = $ID;
            $title = $INPUT->str('pdftitle'); //DEPRECATED
            $title = $INPUT->str('book_title', $title, true);
            if (empty($title)) {
                $title = p_get_first_heading($ID);
            }
            // use page name if title is still empty
            if (empty($title)) {
                $title = noNS($ID);
            }

            $filename = wikiFN($ID, $REV);
            if (!file_exists($filename)) {
                throw new Exception($this->getLang('notexist'));
            }
        } elseif ($event->data == 'export_pdfns') {
            //check input for title and ns
            if (!$title = $INPUT->str('book_title')) {
                throw new Exception($this->getLang('needtitle'));
            }
            $pdfnamespace = cleanID($INPUT->str('book_ns'));
            if (!@is_dir(dirname(wikiFN($pdfnamespace . ':dummy')))) {
                throw new Exception($this->getLang('needns'));
            }

            //sort order
            $order = $INPUT->str('book_order', 'natural', true);
            $sortoptions = ['pagename', 'date', 'natural'];
            if (!in_array($order, $sortoptions)) {
                $order = 'natural';
            }

            //search depth
            $depth = $INPUT->int('book_nsdepth', 0);
            if ($depth < 0) {
                $depth = 0;
            }

            //page search
            $result = [];
            $opts = ['depth' => $depth]; //recursive all levels
            $dir = utf8_encodeFN(str_replace(':', '/', $pdfnamespace));
            search($result, $conf['datadir'], 'search_allpages', $opts, $dir);

            // exclude ids
            $excludes = $INPUT->arr('excludes');
            if (!empty($excludes)) {
                $result = array_filter($result, function ($item) use ($excludes) {
                    return !in_array($item['id'], $excludes);
                });
            }
            // exclude namespaces
            $excludesns = $INPUT->arr('excludesns');
            if (!empty($excludesns)) {
                $result = array_filter($result, function ($item) use ($excludesns) {
                    foreach ($excludesns as $ns) {
                        if (strpos($item['id'], $ns . ':') === 0) {
                            return false;
                        }
                    }
                    return true;
                });
            }

            //sorting
            if (count($result) > 0) {
                if ($order == 'date') {
                    usort($result, [$this, 'cbDateSort']);
                } elseif ($order == 'pagename' || $order == 'natural') {
                    usort($result, [$this, 'cbPagenameSort']);
                }
            }

            foreach ($result as $item) {
                $list[] = $item['id'];
            }

            if ($pdfnamespace !== '') {
                if (!in_array($pdfnamespace . ':' . $conf['start'], $list, true)) {
                    if (file_exists(wikiFN(rtrim($pdfnamespace, ':')))) {
                        array_unshift($list, rtrim($pdfnamespace, ':'));
                    }
                }
            }
        } elseif (!empty($_COOKIE['list-pagelist'])) {
            /** @deprecated  April 2016 replaced by localStorage version of Bookcreator */
            //is in Bookmanager of bookcreator plugin a title given?
            $title = $INPUT->str('pdfbook_title'); //DEPRECATED
            $title = $INPUT->str('book_title', $title, true);
            if (empty($title)) {
                throw new Exception($this->getLang('needtitle'));
            }

            $list = explode("|", $_COOKIE['list-pagelist']);
        } elseif ($INPUT->has('selection')) {
            //handle Bookcreator requests based at localStorage
//            if(!checkSecurityToken()) {
//                http_status(403);
//                print $this->getLang('empty');
//                exit();
//            }

            $list = json_decode($INPUT->str('selection', '', true), true);
            if (!is_array($list) || $list === []) {
                throw new Exception($this->getLang('empty'));
            }

            $title = $INPUT->str('pdfbook_title'); //DEPRECATED
            $title = $INPUT->str('book_title', $title, true);
            if (empty($title)) {
                throw new Exception($this->getLang('needtitle'));
            }
        } elseif ($INPUT->has('savedselection')) {
            //export a saved selection of the Bookcreator Plugin
            if (plugin_isdisabled('bookcreator')) {
                throw new Exception($this->getLang('missingbookcreator'));
            }
            /** @var action_plugin_bookcreator_handleselection $SelectionHandling */
            $SelectionHandling = plugin_load('action', 'bookcreator_handleselection');
            $savedselection = $SelectionHandling->loadSavedSelection($INPUT->str('savedselection'));
            $title = $savedselection['title'];
            $title = $INPUT->str('book_title', $title, true);
            $list = $savedselection['selection'];

            if (empty($title)) {
                throw new Exception($this->getLang('needtitle'));
            }
        } else {
            //show empty bookcreator message
            throw new Exception($this->getLang('empty'));
        }

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

        //some shortcuts to export settings
        $hasToC = $this->getExportConfig('hasToC');
        $levels = $this->getExportConfig('levels');
        $isDebug = $this->getExportConfig('isDebug');
        $watermark = $this->getExportConfig('watermark');

        // initialize PDF library
        require_once(__DIR__ . "/DokuPDF.class.php");

        $mpdf = new DokuPDF(
            $this->getExportConfig('pagesize'),
            $this->getExportConfig('orientation'),
            $this->getExportConfig('font-size'),
            $this->getDocumentLanguage($this->list[0]) //use language of first page
        );

        // let mpdf fix local links
        $self = parse_url(DOKU_URL);
        $url = $self['scheme'] . '://' . $self['host'];
        if (!empty($self['port'])) {
            $url .= ':' . $self['port'];
        }
        $mpdf->SetBasePath($url);

        // Set the title
        $mpdf->SetTitle($this->title);

        // some default document settings
        //note: double-sided document, starts at an odd page (first page is a right-hand side page)
        //      single-side document has only odd pages
        $mpdf->mirrorMargins = $this->getExportConfig('doublesided');
        $mpdf->setAutoTopMargin = 'stretch';
        $mpdf->setAutoBottomMargin = 'stretch';
//            $mpdf->pagenumSuffix = '/'; //prefix for {nbpg}
        if ($hasToC) {
            $mpdf->h2toc = $levels;
        }
        $mpdf->PageNumSubstitutions[] = ['from' => 1, 'reset' => 0, 'type' => '1', 'suppress' => 'off'];

        // Watermarker
        if ($watermark) {
            $mpdf->SetWatermarkText($watermark);
            $mpdf->showWatermarkText = true;
        }

        // load the template
        $template = $this->loadTemplate();

        // prepare HTML header styles
        $html = '';
        if ($isDebug) {
            $html .= '<html><head>';
            $html .= '<style>';
        }

        $styles = '@page { size:auto; ' . $template['page'] . '}';
        $styles .= '@page :first {' . $template['first'] . '}';

        $styles .= '@page landscape-page { size:landscape }';
        $styles .= 'div.dw2pdf-landscape { page:landscape-page }';
        $styles .= '@page portrait-page { size:portrait }';
        $styles .= 'div.dw2pdf-portrait { page:portrait-page }';
        $styles .= $this->loadCSS();

        $mpdf->WriteHTML($styles, 1);

        if ($isDebug) {
            $html .= $styles;
            $html .= '</style>';
            $html .= '</head><body>';
        }

        $body_start = $template['html'];
        $body_start .= '<div class="dokuwiki">';

        // insert the cover page
        $body_start .= $template['cover'];

        $mpdf->WriteHTML($body_start, 2, true, false); //start body html
        if ($isDebug) {
            $html .= $body_start;
        }
        if ($hasToC) {
            //Note: - for double-sided document the ToC is always on an even number of pages, so that the
            //        following content is on a correct odd/even page
            //      - first page of ToC starts always at odd page (so eventually an additional blank page
            //        is included before)
            //      - there is no page numbering at the pages of the ToC
            $mpdf->TOCpagebreakByArray([
                'toc-preHTML' => '<h2>' . $this->getLang('tocheader') . '</h2>',
                'toc-bookmarkText' => $this->getLang('tocheader'),
                'links' => true,
                'outdent' => '1em',
                'pagenumstyle' => '1'
            ]);
            $html .= '<tocpagebreak>';
        }

        // loop over all pages
        $counter = 0;
        $no_pages = count($this->list);
        foreach ($this->list as $page) {
            $this->currentBookChapter = $counter;
            $counter++;

            $pagehtml = $this->wikiToDW2PDF($page, $rev, $date_at);
            //file doesn't exists
            if ($pagehtml == '') {
                continue;
            }
            $pagehtml .= $this->pageDependReplacements($template['cite'], $page);
            if ($counter < $no_pages) {
                $pagehtml .= '<pagebreak />';
            }

            $mpdf->WriteHTML($pagehtml, 2, false, false); //intermediate body html
            if ($isDebug) {
                $html .= $pagehtml;
            }
        }

        // insert the back page
        $body_end = $template['back'];

        $body_end .= '</div>';

        $mpdf->WriteHTML($body_end, 2, false); // finish body html
        if ($isDebug) {
            $html .= $body_end;
            $html .= '</body>';
            $html .= '</html>';
        }

        //Return html for debugging
        if ($isDebug) {
            if ($INPUT->str('debughtml', 'text', true) == 'text') {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo $html;
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
     * Load the various template files and prepare the HTML/CSS for insertion
     *
     * @return array
     */
    protected function loadTemplate()
    {
        global $ID;
        global $conf;
        global $INFO;

        // this is what we'll return
        $output = [
            'cover' => '',
            'back' => '',
            'html' => '',
            'page' => '',
            'first' => '',
            'cite' => '',
        ];

        // prepare header/footer elements
        $html = '';
        foreach (['header', 'footer'] as $section) {
            foreach (['', '_odd', '_even', '_first'] as $order) {
                $file = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/' . $section . $order . '.html';
                if (file_exists($file)) {
                    $html .= '<htmlpage' . $section . ' name="' . $section . $order . '">' . DOKU_LF;
                    $html .= file_get_contents($file) . DOKU_LF;
                    $html .= '</htmlpage' . $section . '>' . DOKU_LF;

                    // register the needed pseudo CSS
                    if ($order == '_first') {
                        $output['first'] .= $section . ': html_' . $section . $order . ';' . DOKU_LF;
                    } elseif ($order == '_even') {
                        $output['page'] .= 'even-' . $section . '-name: html_' . $section . $order . ';' . DOKU_LF;
                    } elseif ($order == '_odd') {
                        $output['page'] .= 'odd-' . $section . '-name: html_' . $section . $order . ';' . DOKU_LF;
                    } else {
                        $output['page'] .= $section . ': html_' . $section . $order . ';' . DOKU_LF;
                    }
                }
            }
        }

        // prepare replacements
        $replace = [
            '@PAGE@' => '{PAGENO}',
            '@PAGES@' => '{nbpg}', //see also $mpdf->pagenumSuffix = ' / '
            '@TITLE@' => hsc($this->title),
            '@WIKI@' => $conf['title'],
            '@WIKIURL@' => DOKU_URL,
            '@DATE@' => dformat(time()),
            '@USERNAME@' => $INFO['userinfo']['name'] ?? '',
            '@BASE@' => DOKU_BASE,
            '@INC@' => DOKU_INC,
            '@TPLBASE@' => DOKU_BASE . 'lib/plugins/dw2pdf/tpl/' . $this->tpl . '/',
            '@TPLINC@' => DOKU_INC . 'lib/plugins/dw2pdf/tpl/' . $this->tpl . '/'
        ];

        // set HTML element
        $html = str_replace(array_keys($replace), array_values($replace), $html);
        //TODO For bookcreator $ID (= bookmanager page) makes no sense
        $output['html'] = $this->pageDependReplacements($html, $ID);

        // cover page
        $coverfile = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/cover.html';
        if (file_exists($coverfile)) {
            $output['cover'] = file_get_contents($coverfile);
            $output['cover'] = str_replace(array_keys($replace), array_values($replace), $output['cover']);
            $output['cover'] = $this->pageDependReplacements($output['cover'], $ID);
            $output['cover'] .= '<pagebreak />';
        }

        // cover page
        $backfile = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/back.html';
        if (file_exists($backfile)) {
            $output['back'] = '<pagebreak />';
            $output['back'] .= file_get_contents($backfile);
            $output['back'] = str_replace(array_keys($replace), array_values($replace), $output['back']);
            $output['back'] = $this->pageDependReplacements($output['back'], $ID);
        }

        // citation box
        $citationfile = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/citation.html';
        if (file_exists($citationfile)) {
            $output['cite'] = file_get_contents($citationfile);
            $output['cite'] = str_replace(array_keys($replace), array_values($replace), $output['cite']);
        }

        return $output;
    }

    /**
     * @param string $raw code with placeholders
     * @param string $id pageid
     * @return string
     */
    protected function pageDependReplacements($raw, $id)
    {
        global $REV, $DATE_AT;

        // generate qr code for this page
        $qr_code = '';
        if ($this->getConf('qrcodescale')) {
            $url = hsc(wl($id, '', '&', true));
            $size = (float)$this->getConf('qrcodescale');
            $qr_code = sprintf(
                '<barcode type="QR" code="%s" error="Q" disableborder="1" class="qrcode" size="%s" />',
                $url,
                $size
            );
        }
        // prepare replacements
        $replace['@ID@'] = $id;
        $replace['@UPDATE@'] = dformat(filemtime(wikiFN($id, $REV)));

        $params = [];
        if ($DATE_AT) {
            $params['at'] = $DATE_AT;
        } elseif ($REV) {
            $params['rev'] = $REV;
        }
        $replace['@PAGEURL@'] = wl($id, $params, true, "&");
        $replace['@QRCODE@'] = $qr_code;

        $content = $raw;

        // let other plugins define their own replacements
        $evdata = ['id' => $id, 'replace' => &$replace, 'content' => &$content];
        $event = new Event('PLUGIN_DW2PDF_REPLACE', $evdata);
        if ($event->advise_before()) {
            $content = str_replace(array_keys($replace), array_values($replace), $raw);
        }

        // plugins may post-process HTML, e.g to clean up unused replacements
        $event->advise_after();

        // @DATE(<date>[, <format>])@
        $content = preg_replace_callback(
            '/@DATE\((.*?)(?:,\s*(.*?))?\)@/',
            [$this, 'replaceDate'],
            $content
        );

        return $content;
    }


    /**
     * (callback) Replace date by request datestring
     * e.g. '%m(30-11-1975)' is replaced by '11'
     *
     * @param array $match with [0]=>whole match, [1]=> first subpattern, [2] => second subpattern
     * @return string
     */
    public function replaceDate($match)
    {
        global $conf;
        //no 2nd argument for default date format
        if ($match[2] == null) {
            $match[2] = $conf['dformat'];
        }
        return strftime($match[2], strtotime($match[1]));
    }

    /**
     * Load all the style sheets and apply the needed replacements
     *
     * @return string css styles
     */
    protected function loadCSS()
    {
        global $conf;
        //reuse the CSS dispatcher functions without triggering the main function
        define('SIMPLE_TEST', 1);
        require_once(DOKU_INC . 'lib/exe/css.php');

        // prepare CSS files
        $files = array_merge(
            [
                DOKU_INC . 'lib/styles/screen.css' => DOKU_BASE . 'lib/styles/',
                DOKU_INC . 'lib/styles/print.css' => DOKU_BASE . 'lib/styles/',
            ],
            $this->cssPluginPDFstyles(),
            [
                DOKU_PLUGIN . 'dw2pdf/conf/style.css' => DOKU_BASE . 'lib/plugins/dw2pdf/conf/',
                DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/style.css' =>
                    DOKU_BASE . 'lib/plugins/dw2pdf/tpl/' . $this->tpl . '/',
                DOKU_PLUGIN . 'dw2pdf/conf/style.local.css' => DOKU_BASE . 'lib/plugins/dw2pdf/conf/',
            ]
        );
        $css = '';
        foreach ($files as $file => $location) {
            $display = str_replace(fullpath(DOKU_INC), '', fullpath($file));
            $css .= "\n/* XXXXXXXXX $display XXXXXXXXX */\n";
            $css .= css_loadfile($file, $location);
        }

        // apply pattern replacements
        if (function_exists('css_styleini')) {
            // compatiblity layer for pre-Greebo releases of DokuWiki
            $styleini = css_styleini($conf['template']);
        } else {
            // Greebo functionality
            $styleUtils = new StyleUtils();
            $styleini = $styleUtils->cssStyleini($conf['template']); // older versions need still the template
        }
        $css = css_applystyle($css, $styleini['replacements']);

        // parse less
        return css_parseless($css);
    }

    /**
     * Returns a list of possible Plugin PDF Styles
     *
     * Checks for a pdf.css, falls back to print.css
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    protected function cssPluginPDFstyles()
    {
        $list = [];
        $plugins = plugin_list();

        $usestyle = explode(',', $this->getConf('usestyles'));
        foreach ($plugins as $p) {
            if (in_array($p, $usestyle)) {
                $list[DOKU_PLUGIN . "$p/screen.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/screen.less"] = DOKU_BASE . "lib/plugins/$p/";

                $list[DOKU_PLUGIN . "$p/style.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/style.less"] = DOKU_BASE . "lib/plugins/$p/";
            }

            $list[DOKU_PLUGIN . "$p/all.css"] = DOKU_BASE . "lib/plugins/$p/";
            $list[DOKU_PLUGIN . "$p/all.less"] = DOKU_BASE . "lib/plugins/$p/";

            if (file_exists(DOKU_PLUGIN . "$p/pdf.css") || file_exists(DOKU_PLUGIN . "$p/pdf.less")) {
                $list[DOKU_PLUGIN . "$p/pdf.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/pdf.less"] = DOKU_BASE . "lib/plugins/$p/";
            } else {
                $list[DOKU_PLUGIN . "$p/print.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/print.less"] = DOKU_BASE . "lib/plugins/$p/";
            }
        }

        // template support
        foreach (
            [
                     'pdf.css',
                     'pdf.less',
                     'css/pdf.css',
                     'css/pdf.less',
                     'styles/pdf.css',
                     'styles/pdf.less'
                 ] as $file
        ) {
            if (file_exists(tpl_incdir() . $file)) {
                $list[tpl_incdir() . $file] = tpl_basedir() . $file;
            }
        }

        return $list;
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
     * usort callback to sort by file lastmodified time
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    public function cbDateSort($a, $b)
    {
        if ($b['rev'] < $a['rev']) {
            return -1;
        }
        if ($b['rev'] > $a['rev']) {
            return 1;
        }
        return strcmp($b['id'], $a['id']);
    }

    /**
     * usort callback to sort by page id
     * @param array $a
     * @param array $b
     * @return int
     */
    public function cbPagenameSort($a, $b)
    {
        global $conf;

        $partsA = explode(':', $a['id']);
        $countA = count($partsA);
        $partsB = explode(':', $b['id']);
        $countB = count($partsB);
        $max = max($countA, $countB);


        // compare namepsace by namespace
        for ($i = 0; $i < $max; $i++) {
            $partA = $partsA[$i] ?: null;
            $partB = $partsB[$i] ?: null;

            // have we reached the page level?
            if ($i === ($countA - 1) || $i === ($countB - 1)) {
                // start page first
                if ($partA == $conf['start']) {
                    return -1;
                }
                if ($partB == $conf['start']) {
                    return 1;
                }
            }

            // prefer page over namespace
            if ($partA === $partB) {
                if (!isset($partsA[$i + 1])) {
                    return -1;
                }
                if (!isset($partsB[$i + 1])) {
                    return 1;
                }
                continue;
            }


            // simply compare
            return strnatcmp($partA, $partB);
        }

        return strnatcmp($a['id'], $b['id']);
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
