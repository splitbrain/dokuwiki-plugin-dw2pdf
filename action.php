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
        $tpl;
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
     */
    function convert(&$event, $param) {
        global $ACT;
        global $REV;
        global $ID;
        global $conf;

        // our event?
        if (( $ACT != 'export_pdfbook' ) && ( $ACT != 'export_pdf' )) return false;

        // check user's rights
        if ( auth_quickaclcheck($ID) < AUTH_READ ) return false;

        // it's ours, no one else's
        $event->preventDefault();

        // one or multiple pages?
        $list = array();
        if ( $ACT == 'export_pdf' ) {
            $list[0] = $ID;
        } elseif (isset($_COOKIE['list-pagelist'])) {
            $list = explode("|", $_COOKIE['list-pagelist']);
        }

        // prepare cache
        $cache = new cache(join(',',$list).$REV.$this->tpl,'.dw2.pdf');
        $depends['files']   = array_map('wikiFN',$list);
        $depends['files'][] = __FILE__;
        $depends['files'][] = dirname(__FILE__).'/renderer.php';
        $depends['files'][] = dirname(__FILE__).'/mpdf/mpdf.php';
        $depends['files']   = array_merge($depends['files'], getConfigFiles('main'));

        // hard work only when no cache available
        if(!$this->getConf('usecache') || !$cache->useCache($depends)){
            // initialize PDF library
            require_once(dirname(__FILE__)."/DokuPDF.class.php");
            $mpdf = new DokuPDF();

            // let mpdf fix local links
            $self = parse_url(DOKU_URL);
            $url  = $self['scheme'].'://'.$self['host'];
            if($self['port']) $url .= ':'.$port;
            $mpdf->setBasePath($url);

            // Set the title
            $title = $_GET['pdfbook_title'];
            if(!$title) $title = p_get_first_heading($ID);
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
            $html .= '<style>';
            $html .= $this->load_css();
            $html .= '@page { size:auto; '.$template['page'].'}';
            $html .= '@page :first {'.$template['first'].'}';
            $html .= $template['css'];
            $html .= '</style>';
            $html .= '</head><body>';
            $html .= $template['html'];
            $html .= '<div class="dokuwiki">';

            // loop over all pages
            $cnt = count($list);
            for($n=0; $n<$cnt; $n++){
                $page = $list[$n];

                $html .= p_cached_output(wikiFN($page,$REV),'dw2pdf',$page);
                $html .= $template['cite'];
                if ($n < ($cnt - 1)){
                    $html .= '<pagebreak />';
                }
            }

            $html .= '</div>';
            $mpdf->WriteHTML($html);

            // write to cache file
            $mpdf->Output($cache->cache, 'F');
        }

        // deliver the file
        header('Content-Type: application/pdf');
        header('Cache-Control: must-revalidate, no-transform, post-check=0, pre-check=0');
        header('Pragma: public');
        http_conditionalRequest(filemtime($cache->cache));

        $filename = rawurlencode(cleanID(strtr($title, ':/;"','    ')));
        if($this->getConf('output') == 'file'){
            header('Content-Disposition: attachment; filename="'.$filename.'.pdf";');
        }else{
            header('Content-Disposition: inline; filename="'.$filename.'.pdf";');
        }

        if (http_sendfile($cache->cache)) exit;

        $fp = @fopen($cache->cache,"rb");
        if($fp){
            http_rangeRequest($fp,filesize($cache->cache),'application/pdf');
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
        global $REV;
        global $conf;
        $tpl = $this->tpl;

        // this is what we'll return
        $output = array(
            'html'  => '',
            'css'   => '',
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

        // generate qr code for this page using google infographics api
        $qr_code = '';
        if ($this->getConf('qrcodesize')) {
            $url = urlencode(wl($ID,'','&',true));
            $qr_code = '<img src="https://chart.googleapis.com/chart?chs='.
                       $this->getConf('qrcodesize').'&cht=qr&chl='.$url.'" />';
        }

        // prepare replacements
        $replace = array(
                '@ID@'      => $ID,
                '@PAGE@'    => '{PAGENO}',
                '@PAGES@'   => '{nb}',
                '@TITLE@'   => hsc($title),
                '@WIKI@'    => $conf['title'],
                '@WIKIURL@' => DOKU_URL,
                '@UPDATE@'  => dformat(filemtime(wikiFN($ID,$REV))),
                '@PAGEURL@' => wl($ID,($REV)?array('rev'=>$REV):false, true, "&"),
                '@DATE@'    => dformat(time()),
                '@BASE@'    => DOKU_BASE,
                '@TPLBASE@' => DOKU_BASE.'lib/plugins/dw2pdf/tpl/'.$tpl.'/',
                '@TPLBASE@' => DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/',
                '@QRCODE@'  => $qr_code,
        );

        // set HTML element
        $output['html'] = str_replace(array_keys($replace), array_values($replace), $html);

        // citation box
        if(file_exists(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/citation.html')){
            $output['cite'] = file_get_contents(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/citation.html');
            $output['cite'] = str_replace(array_keys($replace), array_values($replace), $output['cite']);
        }

        // set custom styles
        if(file_exists(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/style.css')){
            $output['css'] = file_get_contents(DOKU_PLUGIN.'dw2pdf/tpl/'.$tpl.'/style.css');
        }

        return $output;
    }

    /**
     * Load all the style sheets and apply the needed replacements
     */
    protected function load_css(){
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
        global $lang;
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
