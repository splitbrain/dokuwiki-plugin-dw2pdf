<?php
 /**
  * dw2Pdf Plugin: Conversion from dokuwiki content to pdf.
  *
  * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
  * @author     Luigi Micco <l.micco@tiscali.it>
  * @author     Andreas Gohr <andi@splitbrain.org>
  */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

class action_plugin_dw2pdf extends DokuWiki_Action_Plugin {

    private $tpl;

    /**
     * Constructor. Sets the correct template
     */
    function __construct(){
        $tpl = false;
        if(isset($_REQUEST['tpl'])){
            $tpl = trim(preg_replace('/[^A-Za-z0-9_\-]+/','',$_REQUEST['tpl']));
        }
        if(!$tpl) $tpl = $this->getConf('template');
        if(!$tpl) $tpl = 'default';
        if(!is_dir(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl)) $tpl = 'default';

        $this->tpl = $tpl;
    }

    /**
     * Register the events
     */
    function register(&$controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'convert',array());
    }

    /**
     * Do the HTML to PDF conversion work
     *
     * @param Doku_Event $event
     * @param array      $param
     * @return bool
     */
    function convert(&$event, $param) {
        global $ACT;
        global $REV;
        global $ID;

        // our event?
        if (( $ACT != 'export_pdfbook' ) && ( $ACT != 'export_pdf' )) return false;

        // check user's rights
        if ( auth_quickaclcheck($ID) < AUTH_READ ) return false;

        // one or multiple pages?
        $list  = array();
        if($ACT == 'export_pdf') {
            $list[0] = $ID;
            $title = p_get_first_heading($ID);
        } elseif(isset($_COOKIE['list-pagelist']) && !empty($_COOKIE['list-pagelist'])) {
            //is in Bookmanager of bookcreator plugin title given
            if(!$title = $_GET['pdfbook_title']) {  //TODO when title is changed, the cached file contains the old title
                /** @var $bookcreator action_plugin_bookcreator */
                $bookcreator =& plugin_load('action', 'bookcreator');
                msg($bookcreator->getLang('needtitle'), -1);

                $event->data               = 'show';
                $_SERVER['REQUEST_METHOD'] = 'POST'; //clears url
                return false;
            }
            $list = explode("|", $_COOKIE['list-pagelist']);
        } else {
            /** @var $bookcreator action_plugin_bookcreator */
            $bookcreator =& plugin_load('action', 'bookcreator');
            msg($bookcreator->getLang('empty'), -1);

            $event->data               = 'show';
            $_SERVER['REQUEST_METHOD'] = 'POST'; //clears url
            return false;
        }

        // it's ours, no one else's
        $event->preventDefault();

        // prepare cache
        $cache = new cache(join(',',$list).$REV.$this->tpl,'.dw2.pdf');
        $depends['files']   = array_map('wikiFN',$list);
        $depends['files'][] = __FILE__;
        $depends['files'][] = dirname(__FILE__).'/renderer.php';
        $depends['files'][] = dirname(__FILE__).'/mpdf/mpdf.php';
        $depends['files']   = array_merge($depends['files'], getConfigFiles('main'));

        // hard work only when no cache available
        if(!$this->getConf('usecache') || !$cache->useCache($depends)){
            $this->generate_pdf($list, $title, $cache->cache);
        }

        // Send PDF to the user
        $this->send_pdf($title, $cache->cache);
    }

    /**
     * Generate a PDF from a given set of wiki pages.
     * 
     * @param array $pages List of page IDs to include
     * @param string $title The title of the whole PDF document
     * @param string $filename The cache filename to write the PDF to
     * @return void The 
     */
    public function generate_pdf($pages, $title, $filename) {
        global $REV;

        // initialize PDF library
        require_once(dirname(__FILE__)."/DokuPDF.class.php");
        $mpdf = new DokuPDF();

        // let mpdf fix local links
        $self = parse_url(DOKU_URL);
        $url  = $self['scheme'].'://'.$self['host'];
        if($self['port']) $url .= ':'.$self['port'];
        $mpdf->setBasePath($url);

        // Set the title
        $mpdf->SetTitle($title);

        // some default settings
        $mpdf->mirrorMargins = 1;
        $mpdf->useOddEven    = 1;
        $mpdf->setAutoTopMargin = 'stretch';
        $mpdf->setAutoBottomMargin = 'stretch';

        // load the template
        $template = $this->load_template($title);

        // prepare HTML header styles
        $html  = '<html><head>';
        $html .= '<style type="text/css">';
        $html .= $this->load_css();
        $html .= '@page { size:auto; '.$template['page'].'}';
        $html .= '@page :first {'.$template['first'].'}';
        $html .= '</style>';
        $html .= '</head><body>';
        $html .= $template['html'];
        $html .= '<div class="dokuwiki">';

        // loop over all pages
        $cnt = count($pages);
        for($n=0; $n<$cnt; $n++){
            $page = $pages[$n];

            $html .= p_cached_output(wikiFN($page,$REV),'dw2pdf',$page);
            $html .= $this->page_depend_replacements($template['cite'], cleanID($page));
            if ($n < ($cnt - 1)){
                $html .= '<pagebreak />';
            }
        }

        $html .= '</div>';
        $mpdf->WriteHTML($html);

        // Write to cache file
        $mpdf->Output($filename, 'F');
    }

    /**
     * Send a cached PDF file to the user with a given title/filename.
     * 
     * @param string $title Title to be used as the output filename (special characters will be removed)
     * @param string $cache_filename Full path to the PDF file to send
     * @return void Does not return
     */
    protected function send_pdf($title, $cache_filename) {
        // deliver the file
        header('Content-Type: application/pdf');
        header('Cache-Control: must-revalidate, no-transform, post-check=0, pre-check=0');
        header('Pragma: public');
        http_conditionalRequest(filemtime($cache_filename));

        $filename = rawurlencode(cleanID(strtr($title, ':/;"','    ')));
        if($this->getConf('output') == 'file'){
            header('Content-Disposition: attachment; filename="'.$filename.'.pdf";');
        }else{
            header('Content-Disposition: inline; filename="'.$filename.'.pdf";');
        }

        if (http_sendfile($cache_filename)) exit;

        $fp = @fopen($cache_filename,"rb");
        if($fp){
            http_rangeRequest($fp,filesize($cache_filename),'application/pdf');
        }else{
            header("HTTP/1.0 500 Internal Server Error");
            print "Could not read file - bad permissions?";
        }
        exit();
    }

    /**
     * Load the various template files and prepare the HTML/CSS for insertion
     */
    protected function load_template($title){
        global $ID;
        global $conf;
        $tpl = $this->tpl;

        // this is what we'll return
        $output = array(
            'html'  => '',
            'page'  => '',
            'first' => '',
            'cite'  => '',
        );

        // prepare header/footer elements
        $html = '';
        foreach(array('header','footer') as $t){
            foreach(array('','_odd','_even','_first') as $h){
                if(file_exists(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/'.$t.$h.'.html')){
                    $html .= '<htmlpage'.$t.' name="'.$t.$h.'">'.DOKU_LF;
                    $html .= file_get_contents(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/'.$t.$h.'.html').DOKU_LF;
                    $html .= '</htmlpage'.$t.'>'.DOKU_LF;

                    // register the needed pseudo CSS
                    if($h == '_first'){
                        $output['first'] .= $t.': html_'.$t.$h.';'.DOKU_LF;
                    }elseif($h == '_even'){
                        $output['page'] .= 'even-'.$t.'-name: html_'.$t.$h.';'.DOKU_LF;
                    }elseif($h == '_odd'){
                        $output['page'] .= 'odd-'.$t.'-name: html_'.$t.$h.';'.DOKU_LF;
                    }else{
                        $output['page'] .= $t.': html_'.$t.$h.';'.DOKU_LF;
                    }
                }
            }
        }

        // prepare replacements
        $replace = array(
                '@PAGE@'    => '{PAGENO}',
                '@PAGES@'   => '{nb}',
                '@TITLE@'   => hsc($title),
                '@WIKI@'    => $conf['title'],
                '@WIKIURL@' => DOKU_URL,
                '@DATE@'    => dformat(time()),
                '@BASE@'    => DOKU_BASE,
                '@TPLBASE@' => DOKU_BASE.'lib/plugins/dw2pdf/tpl/'.$tpl.'/'
        );

        // set HTML element
        $html = str_replace(array_keys($replace), array_values($replace), $html);
        //TODO For bookcreator $ID (= bookmanager page) makes no sense
        $output['html'] = $this->page_depend_replacements($html, $ID);

        // citation box
        if(file_exists(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/citation.html')){
            $output['cite'] = file_get_contents(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/citation.html');
            $output['cite'] = str_replace(array_keys($replace), array_values($replace), $output['cite']);
        }

        return $output;
    }

    /**
     * @param string $raw code with placeholders
     * @param string $id  pageid
     * @return string
     */
    protected function page_depend_replacements($raw, $id){
        global $REV;

        // generate qr code for this page using google infographics api
        $qr_code = '';
        if ($this->getConf('qrcodesize')) {
            $url = urlencode(wl($id,'','&',true));
            $qr_code = '<img src="https://chart.googleapis.com/chart?chs='.
                $this->getConf('qrcodesize').'&cht=qr&chl='.$url.'" />';
        }
        // prepare replacements
        $replace['@ID@']      = $id;
        $replace['@UPDATE@']  = dformat(filemtime(wikiFN($id, $REV)));
        $replace['@PAGEURL@'] = wl($id, ($REV) ? array('rev'=> $REV) : false, true, "&");
        $replace['@QRCODE@']  = $qr_code;

        // Relace @DATA:column@ placeholders, if the Data plugin is available.
        $data_replacements = $this->get_data_replacements($raw, $id);
        $replace = array_merge($replace, $data_replacements);

        return str_replace(array_keys($replace), array_values($replace), $raw);
    }

    /**
     * Replace all @DATA:column@ patterns with values retrieved from the
     * data plugin's metadata database.
     * 
     * @param string $html Input HTML, in which to find replacement strings.
     * @param string $id The page ID
     * @return string HTML string with replacements made.
     */
    public function get_data_replacements($html, $id) {
        $replacements = array();

        // Load helper (or give up)
        $helper = plugin_load('helper', 'data');
        if ($helper == NULL) return $replacements;

        // Find replacements (or give up)
        $count = preg_match_all('/@DATA:(.*?)@/', $html, $matches);
        if ($count < 1) return $replacements;
        $replaceable = array();
        for ($m=0; $m<count($matches[0]); $m++) {
            $replaceable[strtolower($matches[1][$m])] = $matches[0][$m];
        }

        // Set up SQLite, and retrieve this page's metadata
        $sqlite = $helper->_getDB();
        $sql = "SELECT key, value
            FROM pages JOIN data ON data.pid=pages.pid
            WHERE pages.page = '".$id."'";
        $rows = $sqlite->res2arr($sqlite->query($sql));

        // Get replacement values and build the replacement array
        foreach ($rows as $row) {
            if (isset($replaceable[$row['key']])) {
                $replacements[$replaceable[$row['key']]] = $row['value'];
            }
        }

        // Return required replacements. Replacing is done in
        // $this->page_depend_replacements() along with other replacements.
        return $replacements;
    }

    /**
     * Load all the style sheets and apply the needed replacements
     */
    protected function load_css(){
        global $conf;
        //reusue the CSS dispatcher functions without triggering the main function
        define('SIMPLE_TEST',1);
        require_once(DOKU_INC.'lib/exe/css.php');

        // prepare CSS files
        $files = array_merge(
                    array(
                        DOKU_INC.'lib/styles/screen.css'
                            => DOKU_BASE.'lib/styles/',
                        DOKU_INC.'lib/styles/print.css'
                            => DOKU_BASE.'lib/styles/',
                    ),
                    css_pluginstyles('all'),
                    $this->css_pluginPDFstyles(),
                    array(
                        DOKU_PLUGIN.'dw2pdf/conf/style.css'
                            => DOKU_BASE.'lib/plugins/dw2pdf/conf/',
                        DOKU_PLUGIN.'dw2pdf/tpl/'.$this->tpl.'/style.css'
                            => DOKU_BASE.'lib/plugins/dw2pdf/tpl/'.$this->tpl.'/',
                        DOKU_PLUGIN.'dw2pdf/conf/style.local.css'
                            => DOKU_BASE.'lib/plugins/dw2pdf/conf/',
                    )
                 );
        $css = '';
        foreach($files as $file => $location){
            $css .= css_loadfile($file, $location);
        }

        // apply pattern replacements
        $css = css_applystyle($css,DOKU_INC.'lib/tpl/'.$conf['template'].'/');

        return $css;
    }


    /**
     * Returns a list of possible Plugin PDF Styles
     *
     * Checks for a pdf.css, falls back to print.css
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function css_pluginPDFstyles(){
        $list = array();
        $plugins = plugin_list();

        $usestyle = explode(',',$this->getConf('usestyles'));
        foreach ($plugins as $p){
            if(in_array($p,$usestyle)){
                $list[DOKU_PLUGIN."$p/screen.css"] = DOKU_BASE."lib/plugins/$p/";
                $list[DOKU_PLUGIN."$p/style.css"] = DOKU_BASE."lib/plugins/$p/";
            }

            if(file_exists(DOKU_PLUGIN."$p/pdf.css")){
                $list[DOKU_PLUGIN."$p/pdf.css"] = DOKU_BASE."lib/plugins/$p/";
            }else{
                $list[DOKU_PLUGIN."$p/print.css"] = DOKU_BASE."lib/plugins/$p/";
            }
        }
        return $list;
    }

}
