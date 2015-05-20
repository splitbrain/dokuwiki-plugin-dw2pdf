<?php
/**
 * dw2Pdf Plugin: Conversion from dokuwiki content to pdf.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class action_plugin_dw2pdf
 *
 * Export hmtl content to pdf, for different url parameter configurations
 * DokuPDF which extends mPDF is used for generating the pdf from html.
 */
class action_plugin_dw2pdf extends DokuWiki_Action_Plugin {
    /**
     * Settings for current export, collected from url param, plugin config, global config
     *
     * @var array
     */
    protected $exportConfig = null;
    protected $tpl;
    protected $list = array();

    /**
     * Constructor. Sets the correct template
     */
    public function __construct() {
        $this->tpl = $this->getExportConfig('template');
    }

    /**
     * Register the events
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'convert', array());
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'addbutton', array());
    }

    /**
     * Do the HTML to PDF conversion work
     *
     * @param Doku_Event $event
     * @param array      $param
     * @return bool
     */
    public function convert(Doku_Event $event, $param) {
        global $ACT;
        global $REV;
        global $ID;

        // our event?
        if(($ACT != 'export_pdfbook') && ($ACT != 'export_pdf') && ($ACT != 'export_pdfns')) return false;

        // check user's rights
        if(auth_quickaclcheck($ID) < AUTH_READ) return false;

        if($data = $this->collectExportPages($event)) {
            list($title, $this->list) = $data;
        } else {
            return false;
        }

        // it's ours, no one else's
        $event->preventDefault();

        // prepare cache
        $cachekey = join(',', $this->list)
                    . $REV
                    . $this->getExportConfig('template')
                    . $this->getExportConfig('pagesize')
                    . $this->getExportConfig('orientation')
                    . $this->getExportConfig('doublesided')
                    . ($this->getExportConfig('hasToC') ? join('-', $this->getExportConfig('levels')) : '0')
                    . $title;
        $cache = new cache($cachekey, '.dw2.pdf');

        $dependencies = array();
        foreach($this->list as $pageid) {
            $relations = p_get_metadata($pageid, 'relation');

            if (is_array($relations)) {
                if(array_key_exists('media', $relations) && is_array($relations['media'])) {
                    foreach($relations['media'] as $mediaid => $exists) {
                        if($exists) {
                            $dependencies[] = mediaFN($mediaid);
                        }
                    }
                }

                if(array_key_exists('haspart', $relations) && is_array($relations['haspart'])) {
                    foreach($relations['haspart'] as $part_pageid => $exists) {
                        if($exists) {
                            $dependencies[] = wikiFN($part_pageid);
                        }
                    }
                }
            }

            $dependencies[] = metaFN($pageid,'.meta');
        }

        $depends['files']   = array_map('wikiFN', $this->list);
        $depends['files'][] = __FILE__;
        $depends['files'][] = dirname(__FILE__) . '/renderer.php';
        $depends['files'][] = dirname(__FILE__) . '/mpdf/mpdf.php';
        $depends['files']   = array_merge(
                                $depends['files'],
                                $dependencies,
                                getConfigFiles('main')
                              );

        // hard work only when no cache available
        if(!$this->getConf('usecache') || !$cache->useCache($depends)) {
            $this->generatePDF($cache->cache, $title);
        }

        // deliver the file
        $this->sendPDFFile($cache->cache, $title);
        return true;
    }


    /**
     * Obtain list of pages and title, based on url parameters
     *
     * @param Doku_Event $event
     * @return string|bool
     */
    protected function collectExportPages(Doku_Event $event) {
        global $ACT;
        global $ID;
        global $INPUT;
        global $conf;

        // list of one or multiple pages
        $list = array();

        if($ACT == 'export_pdf') {
            $list[0] = $ID;
            $title = $INPUT->str('pdftitle');
            if(!$title) {
                $title = p_get_first_heading($ID);
            }

        } elseif($ACT == 'export_pdfns') {
            //check input for title and ns
            if(!$title = $INPUT->str('pdfns_title')) {
                $this->showPageWithErrorMsg($event, 'needtitle');
                return false;
            }
            $pdfnamespace = cleanID($INPUT->str('pdfns_ns'));
            if(!@is_dir(dirname(wikiFN($pdfnamespace . ':dummy')))) {
                $this->showPageWithErrorMsg($event, 'needns');
                return false;
            }

            //sort order
            $order = $INPUT->str('pdfns_order', 'natural', true);
            $sortoptions = array('pagename', 'date', 'natural');
            if(!in_array($order, $sortoptions)) {
                $order = 'natural';
            }

            //search depth
            $depth = $INPUT->int('pdfns_depth', 0);
            if($depth < 0) {
                $depth = 0;
            }

            //page search
            $result = array();
            $opts = array('depth' => $depth); //recursive all levels
            $dir = utf8_encodeFN(str_replace(':', '/', $pdfnamespace));
            search($result, $conf['datadir'], 'search_allpages', $opts, $dir);

            //sorting
            if(count($result) > 0) {
                if($order == 'date') {
                    usort($result, array($this, '_datesort'));
                } elseif($order == 'pagename') {
                    usort($result, array($this, '_pagenamesort'));
                }
            }

            foreach($result as $item) {
                $list[] = $item['id'];
            }

        } elseif(isset($_COOKIE['list-pagelist']) && !empty($_COOKIE['list-pagelist'])) {
            //is in Bookmanager of bookcreator plugin a title given?
            if(!$title = $INPUT->str('pdfbook_title')) {
                $this->showPageWithErrorMsg($event, 'needtitle');
                return false;
            } else {
                $list = explode("|", $_COOKIE['list-pagelist']);
            }

        } else {
            //show empty bookcreator message
            $this->showPageWithErrorMsg($event, 'empty');
            return false;
        }

        $list = array_map('cleanID', $list);
        return array($title, $list);
    }


    /**
     * Set error notification and reload page again
     *
     * @param Doku_Event $event
     * @param string     $msglangkey key of translation key
     */
    private function showPageWithErrorMsg(Doku_Event $event, $msglangkey) {
        msg($this->getLang($msglangkey), -1);

        $event->data = 'show';
        $_SERVER['REQUEST_METHOD'] = 'POST'; //clears url
    }

    /**
     * Build a pdf from the html
     *
     * @param string $cachefile
     * @param string $title
     */
    protected function generatePDF($cachefile, $title) {
        global $ID;
        global $REV;
        global $INPUT;

        //some shortcuts to export settings
        $hasToC = $this->getExportConfig('hasToC');
        $levels = $this->getExportConfig('levels');
        $isDebug = $this->getExportConfig('isDebug');

        // initialize PDF library
        require_once(dirname(__FILE__) . "/DokuPDF.class.php");

        $mpdf = new DokuPDF($this->getExportConfig('pagesize'), $this->getExportConfig('orientation'));

        // let mpdf fix local links
        $self = parse_url(DOKU_URL);
        $url = $self['scheme'] . '://' . $self['host'];
        if($self['port']) {
            $url .= ':' . $self['port'];
        }
        $mpdf->setBasePath($url);

        // Set the title
        $mpdf->SetTitle($title);

        // some default document settings
        //note: double-sided document, starts at an odd page (first page is a right-hand side page)
        //      single-side document has only odd pages
        $mpdf->mirrorMargins = $this->getExportConfig('doublesided');
        //$mpdf->useOddEven = $this->getExportConfig('doublesided'); //duplicate of mirrorMargins (not longer available since mpdf6.0)
        $mpdf->setAutoTopMargin = 'stretch';
        $mpdf->setAutoBottomMargin = 'stretch';
//            $mpdf->pagenumSuffix = '/'; //prefix for {nbpg}
        if($hasToC) {
            $mpdf->PageNumSubstitutions[] = array('from' => 1, 'reset' => 0, 'type' => 'i', 'suppress' => 'off'); //use italic pageno until ToC
            $mpdf->h2toc = $levels;
        } else {
            $mpdf->PageNumSubstitutions[] = array('from' => 1, 'reset' => 0, 'type' => '1', 'suppress' => 'off');
        }

        // load the template
        $template = $this->load_template($title);

        // prepare HTML header styles
        $html = '';
        if($isDebug) {
            $html .= '<html><head>';
            $html .= '<style type="text/css">';
        }
        $styles = $this->load_css();
        $styles .= '@page { size:auto; ' . $template['page'] . '}';
        $styles .= '@page :first {' . $template['first'] . '}';

        $styles .= '@page landscape-page { size:landscape }';
        $styles .= 'div.dw2pdf-landscape { page:landscape-page }';
        $styles .= '@page portrait-page { size:portrait }';
        $styles .= 'div.dw2pdf-portrait { page:portrait-page }';

        $mpdf->WriteHTML($styles, 1);

        if($isDebug) {
            $html .= $styles;
            $html .= '</style>';
            $html .= '</head><body>';
        }

        $body_start = $template['html'];
        $body_start .= '<div class="dokuwiki">';

        // insert the cover page
        $body_start .= $template['cover'];

        $mpdf->WriteHTML($body_start, 2, true, false); //start body html
        if($isDebug) {
            $html .= $body_start;
        }
        if($hasToC) {
            //Note: - for double-sided document the ToC is always on an even number of pages, so that the following content is on a correct odd/even page
            //      - first page of ToC starts always at odd page (so eventually an additional blank page is included before)
            //      - there is no page numbering at the pages of the ToC
            $mpdf->TOCpagebreakByArray(
                array(
                    'toc-preHTML' => '<h2>' . $this->getLang('tocheader') . '</h2>',
                    'toc-bookmarkText' => $this->getLang('tocheader'),
                    'links' => true,
                    'outdent' => '1em',
                    'resetpagenum' => true, //start pagenumbering after ToC
                    'pagenumstyle' => '1'
                )
            );
            $html .= '<tocpagebreak>';
        }

        // store original pageid
        $keep = $ID;

        // loop over all pages
        $cnt = count($this->list);
        for($n = 0; $n < $cnt; $n++) {
            $page = $this->list[$n];

            // set global pageid to the rendered page
            $ID = $page;

            $pagehtml = p_cached_output(wikiFN($page, $REV), 'dw2pdf', $page);
            $pagehtml .= $this->page_depend_replacements($template['cite'], $page);
            if($n < ($cnt - 1)) {
                $pagehtml .= '<pagebreak />';
            }

            $mpdf->WriteHTML($pagehtml, 2, false, false); //intermediate body html
            if($isDebug) {
                $html .= $pagehtml;
            }
        }
        //restore ID
        $ID = $keep;

        // insert the back page
        $body_end = $template['back'];

        $body_end .= '</div>';

        $mpdf->WriteHTML($body_end, 2, false, true); // finish body html
        if($isDebug) {
            $html .= $body_end;
            $html .= '</body>';
            $html .= '</html>';
        }

        //Return html for debugging
        if($isDebug) {
            if($INPUT->str('debughtml', 'text', true) == 'html') {
                echo $html;
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                echo $html;
            }
            exit();
        };

        // write to cache file
        $mpdf->Output($cachefile, 'F');
    }

    /**
     * @param string $cachefile
     * @param string $title
     */
    protected function sendPDFFile($cachefile, $title) {
        header('Content-Type: application/pdf');
        header('Cache-Control: must-revalidate, no-transform, post-check=0, pre-check=0');
        header('Pragma: public');
        http_conditionalRequest(filemtime($cachefile));

        $filename = rawurlencode(cleanID(strtr($title, ':/;"', '    ')));
        if($this->getConf('output') == 'file') {
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf";');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '.pdf";');
        }

        //try to send file, and exit if done
        http_sendfile($cachefile);

        $fp = @fopen($cachefile, "rb");
        if($fp) {
            http_rangeRequest($fp, filesize($cachefile), 'application/pdf');
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            print "Could not read file - bad permissions?";
        }
        exit();
    }

    /**
     * Load the various template files and prepare the HTML/CSS for insertion
     */
    protected function load_template($title) {
        global $ID;
        global $conf;

        // this is what we'll return
        $output = array(
            'cover' => '',
            'html'  => '',
            'page'  => '',
            'first' => '',
            'cite'  => '',
        );

        // prepare header/footer elements
        $html = '';
        foreach(array('header', 'footer') as $section) {
            foreach(array('', '_odd', '_even', '_first') as $order) {
                $file = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/' . $section . $order . '.html';
                if(file_exists($file)) {
                    $html .= '<htmlpage' . $section . ' name="' . $section . $order . '">' . DOKU_LF;
                    $html .= file_get_contents($file) . DOKU_LF;
                    $html .= '</htmlpage' . $section . '>' . DOKU_LF;

                    // register the needed pseudo CSS
                    if($order == '_first') {
                        $output['first'] .= $section . ': html_' . $section . $order . ';' . DOKU_LF;
                    } elseif($order == '_even') {
                        $output['page'] .= 'even-' . $section . '-name: html_' . $section . $order . ';' . DOKU_LF;
                    } elseif($order == '_odd') {
                        $output['page'] .= 'odd-' . $section . '-name: html_' . $section . $order . ';' . DOKU_LF;
                    } else {
                        $output['page'] .= $section . ': html_' . $section . $order . ';' . DOKU_LF;
                    }
                }
            }
        }

        // prepare replacements
        $replace = array(
            '@PAGE@'    => '{PAGENO}',
            '@PAGES@'   => '{nbpg}', //see also $mpdf->pagenumSuffix = ' / '
            '@TITLE@'   => hsc($title),
            '@WIKI@'    => $conf['title'],
            '@WIKIURL@' => DOKU_URL,
            '@DATE@'    => dformat(time()),
            '@BASE@'    => DOKU_BASE,
            '@TPLBASE@' => DOKU_BASE . 'lib/plugins/dw2pdf/tpl/' . $this->tpl . '/'
        );

        // set HTML element
        $html = str_replace(array_keys($replace), array_values($replace), $html);
        //TODO For bookcreator $ID (= bookmanager page) makes no sense
        $output['html'] = $this->page_depend_replacements($html, $ID);

        // cover page
        $coverfile = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/cover.html';
        if(file_exists($coverfile)) {
            $output['cover'] = file_get_contents($coverfile);
            $output['cover'] = str_replace(array_keys($replace), array_values($replace), $output['cover']);
            $output['cover'] .= '<pagebreak />';
        }

        // cover page
        $backfile = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/back.html';
        if(file_exists($backfile)) {
            $output['back'] = '<pagebreak />';
            $output['back'] .= file_get_contents($backfile);
            $output['back'] = str_replace(array_keys($replace), array_values($replace), $output['back']);
        }

        // citation box
        $citationfile = DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/citation.html';
        if(file_exists($citationfile)) {
            $output['cite'] = file_get_contents($citationfile);
            $output['cite'] = str_replace(array_keys($replace), array_values($replace), $output['cite']);
        }

        return $output;
    }

    /**
     * @param string $raw code with placeholders
     * @param string $id  pageid
     * @return string
     */
    protected function page_depend_replacements($raw, $id) {
        global $REV;

        // generate qr code for this page using google infographics api
        $qr_code = '';
        if($this->getConf('qrcodesize')) {
            $url = urlencode(wl($id, '', '&', true));
            $qr_code = '<img src="https://chart.googleapis.com/chart?chs=' .
                $this->getConf('qrcodesize') . '&cht=qr&chl=' . $url . '" />';
        }
        // prepare replacements
        $replace['@ID@']      = $id;
        $replace['@UPDATE@']  = dformat(filemtime(wikiFN($id, $REV)));
        $replace['@PAGEURL@'] = wl($id, ($REV) ? array('rev' => $REV) : false, true, "&");
        $replace['@QRCODE@']  = $qr_code;

        $content = str_replace(array_keys($replace), array_values($replace), $raw);

        // @DATE(<date>[, <format>])@
        $content = preg_replace_callback(
            '/@DATE\((.*?)(?:,\s*(.*?))?\)@/',
            array($this, 'replacedate'),
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
    function replacedate($match) {
        global $conf;
        //no 2nd argument for default date format
        if($match[2] == null) {
            $match[2] = $conf['dformat'];
        }
        return strftime($match[2], strtotime($match[1]));
    }


    /**
     * Load all the style sheets and apply the needed replacements
     */
    protected function load_css() {
        global $conf;
        //reusue the CSS dispatcher functions without triggering the main function
        define('SIMPLE_TEST', 1);
        require_once(DOKU_INC . 'lib/exe/css.php');

        // prepare CSS files
        $files = array_merge(
            array(
                DOKU_INC . 'lib/styles/screen.css'
                    => DOKU_BASE . 'lib/styles/',
                DOKU_INC . 'lib/styles/print.css'
                    => DOKU_BASE . 'lib/styles/',
            ),
            css_pluginstyles('all'),
            $this->css_pluginPDFstyles(),
            array(
                DOKU_PLUGIN . 'dw2pdf/conf/style.css'
                    => DOKU_BASE . 'lib/plugins/dw2pdf/conf/',
                DOKU_PLUGIN . 'dw2pdf/tpl/' . $this->tpl . '/style.css'
                    => DOKU_BASE . 'lib/plugins/dw2pdf/tpl/' . $this->tpl . '/',
                DOKU_PLUGIN . 'dw2pdf/conf/style.local.css'
                    => DOKU_BASE . 'lib/plugins/dw2pdf/conf/',
            )
        );
        $css = '';
        foreach($files as $file => $location) {
            $display = str_replace(fullpath(DOKU_INC), '', fullpath($file));
            $css .= "\n/* XXXXXXXXX $display XXXXXXXXX */\n";
            $css .= css_loadfile($file, $location);
        }

        if(function_exists('css_parseless')) {
            // apply pattern replacements
            $styleini = css_styleini($conf['template']);
            $css = css_applystyle($css, $styleini['replacements']);

            // parse less
            $css = css_parseless($css);
        } else {
            // @deprecated 2013-12-19: fix backward compatibility
            $css = css_applystyle($css, DOKU_INC . 'lib/tpl/' . $conf['template'] . '/');
        }

        return $css;
    }

    /**
     * Returns a list of possible Plugin PDF Styles
     *
     * Checks for a pdf.css, falls back to print.css
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    protected function css_pluginPDFstyles() {
        $list = array();
        $plugins = plugin_list();

        $usestyle = explode(',', $this->getConf('usestyles'));
        foreach($plugins as $p) {
            if(in_array($p, $usestyle)) {
                $list[DOKU_PLUGIN . "$p/screen.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/style.css"] = DOKU_BASE . "lib/plugins/$p/";
            }

            if(file_exists(DOKU_PLUGIN . "$p/pdf.css")) {
                $list[DOKU_PLUGIN . "$p/pdf.css"] = DOKU_BASE . "lib/plugins/$p/";
            } else {
                $list[DOKU_PLUGIN . "$p/print.css"] = DOKU_BASE . "lib/plugins/$p/";
            }
        }
        return $list;
    }

    /**
     * Returns array of pages which will be included in the exported pdf
     *
     * @return array
     */
    public function getExportedPages() {
        return $this->list;
    }

    /**
     * usort callback to sort by file lastmodified time
     */
    public function _datesort($a, $b) {
        if($b['rev'] < $a['rev']) return -1;
        if($b['rev'] > $a['rev']) return 1;
        return strcmp($b['id'], $a['id']);
    }

    /**
     * usort callback to sort by page id
     */
    public function _pagenamesort($a, $b) {
        if($a['id'] <= $b['id']) return -1;
        if($a['id'] > $b['id']) return 1;
        return 0;
    }

    /**
     * Return settings read from:
     *   1. url parameters
     *   2. plugin config
     *   3. global config
     *
     * @return array
     */
    protected function loadExportConfig() {
        global $INPUT;
        global $conf;

        $this->exportConfig = array();

        // decide on the paper setup from param or config
        $this->exportConfig['pagesize'] = $INPUT->str('pagesize', $this->getConf('pagesize'), true);
        $this->exportConfig['orientation'] = $INPUT->str('orientation', $this->getConf('orientation'), true);

        $doublesided = $INPUT->bool('doublesided', (bool) $this->getConf('doublesided'));
        $this->exportConfig['doublesided'] = $doublesided ? '1' : '0';

        $hasToC = $INPUT->bool('toc', (bool) $this->getConf('toc'));
        $levels = array();
        if($hasToC) {
            $toclevels = $INPUT->str('toclevels', $this->getConf('toclevels'), true);
            list($top_input, $max_input) = explode('-', $toclevels, 2);
            list($top_conf, $max_conf) = explode('-', $this->getConf('toclevels'), 2);
            $bounds_input = array(
                'top' => array(
                    (int) $top_input,
                    (int) $top_conf
                ),
                'max' => array(
                    (int) $max_input,
                    (int) $max_conf
                )
            );
            $bounds = array(
                'top' => $conf['toptoclevel'],
                'max' => $conf['maxtoclevel']

            );
            foreach($bounds_input as $bound => $values) {
                foreach($values as $value) {
                    if($value > 0 && $value <= 5) {
                        //stop at valid value and store
                        $bounds[$bound] = $value;
                        break;
                    }
                }
            }

            if($bounds['max'] < $bounds['top']) {
                $bounds['max'] = $bounds['top'];
            }

            for($level = $bounds['top']; $level <= $bounds['max']; $level++) {
                $levels["H$level"] = $level - 1;
            }
        }
        $this->exportConfig['hasToC'] = $hasToC;
        $this->exportConfig['levels'] = $levels;

        $this->exportConfig['maxbookmarks'] = $INPUT->int('maxbookmarks', $this->getConf('maxbookmarks'), true);

        $tplconf = $this->getConf('template');
        $tpl = $INPUT->str('tpl', $tplconf, true);
        if(!is_dir(DOKU_PLUGIN . 'dw2pdf/tpl/' . $tpl)) {
            $tpl = $tplconf;
        }
        if(!$tpl){
            $tpl = 'default';
        }
        $this->exportConfig['template'] = $tpl;

        $this->exportConfig['isDebug'] = $conf['allowdebug'] && $INPUT->has('debughtml');
    }

    /**
     * Returns requested config
     *
     * @param string $name
     * @param mixed  $notset
     * @return mixed|bool
     */
    public function getExportConfig($name, $notset = false) {
        if ($this->exportConfig === null){
            $this->loadExportConfig();
        }

        if(isset($this->exportConfig[$name])){
            return $this->exportConfig[$name];
        }else{
            return $notset;
        }
    }

    /**
     * Add 'export pdf'-button to pagetools
     *
     * @param Doku_Event $event
     * @param mixed      $param not defined
     */
    public function addbutton(Doku_Event $event, $param) {
        global $ID, $REV;

        if($this->getConf('showexportbutton') && $event->data['view'] == 'main') {
            $params = array('do' => 'export_pdf');
            if($REV) {
                $params['rev'] = $REV;
            }

            // insert button at position before last (up to top)
            $event->data['items'] = array_slice($event->data['items'], 0, -1, true) +
                array('export_pdf' =>
                          '<li>'
                          . '<a href="' . wl($ID, $params) . '"  class="action export_pdf" rel="nofollow" title="' . $this->getLang('export_pdf_button') . '">'
                          . '<span>' . $this->getLang('export_pdf_button') . '</span>'
                          . '</a>'
                          . '</li>'
                ) +
                array_slice($event->data['items'], -1, 1, true);
        }
    }
}
