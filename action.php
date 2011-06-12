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

    /**
     * Register the events
     */
    function register(&$controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'convert',array());
    }

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

        // initialize PDF library
        require_once(dirname(__FILE__)."/DokuPDF.class.php");
        $mpdf = new DokuPDF();

        // let mpdf fix local links
        $self = parse_url(DOKU_URL);
        $url  = $self['scheme'].'://'.$self['host'];
        if($self['port']) $url .= ':'.$port;
        $mpdf->setBasePath($url);

        // some default settings
        $mpdf->mirrorMargins          = 1;  // Use different Odd/Even headers and footers and mirror margins
        $mpdf->defaultheaderfontsize  = 8;  // in pts
        $mpdf->defaultheaderfontstyle = ''; // blank, B, I, or BI
        $mpdf->defaultheaderline      = 1;  // 1 to include line below header/above footer
        $mpdf->defaultfooterfontsize  = 8;  // in pts
        $mpdf->defaultfooterfontstyle = ''; // blank, B, I, or BI
        $mpdf->defaultfooterline      = 1;  // 1 to include line below header/above footer

        // prepare HTML header styles
        $html  = '<html><head>';
        $html .= '<style>';
        $html .= file_get_contents(DOKU_PLUGIN.'dw2pdf/conf/style.css');
        $html .= @file_get_contents(DOKU_PLUGIN.'dw2pdf/conf/style.local.css');
        $html .= '</style>';
        $html .= '</head><body>';

        // set headers/footers
        $this->prepare_headers($mpdf);

        // one or multiple pages?
        $list = array();
        if ( $ACT == 'export_pdf' ) {
            $list[0] = $ID;
        } elseif (isset($_COOKIE['list-pagelist'])) {
            $list = explode("|", $_COOKIE['list-pagelist']);
        }

        // loop over all pages
        $cnt = count($list);
        for($n=0; $n<$cnt; $n++){
            $page = $list[$n];

            $html .= p_wiki_xhtml($page,$REV,false);
            if($this->getConf('addcitation')){
                $html .= $this->citation($page);
            }
            if ($n < ($cnt - 1)){
                $html .= '<pagebreak />';
            }
        }

        $this->arrangeHtml($html, $this->getConf("norender"));

        $mpdf->WriteHTML($html);
        $title = $_GET['pdfbook_title'];
        if(!$title) $title = noNS($ID);
        $output = 'I';
        if($this->getConf('output') == 'file') $output = 'D';
        $mpdf->Output(urlencode($title).'.pdf', $output);

        exit();
    }

    /**
     * Setup the page headers and footers
     */
    protected function prepare_headers(&$mpdf){
        global $ID;
        global $REV;
        global $conf;

        if($_GET['pdfbook_title']){
            $title = $_GET['pdfbook_title'];
        }else{
            $title = p_get_first_heading($ID);
        }
        if(!$title) $title = noNS($ID);

        // prepare replacements
        $replace = array(
                '@ID@'      => $ID,
                '@PAGE@'    => '{PAGENO}',
                '@PAGES@'   => '{nb}',
                '@TITLE@'   => $title,
                '@WIKI@'    => $conf['title'],
                '@WIKIURL@' => DOKU_URL,
                '@UPDATE@'  => dformat(filemtime(wikiFN($ID,$REV))),
                '@PAGEURL@' => wl($ID,($REV)?array('rev'=>$REV):false, true, "&"),
                '@DATE@'    => dformat(time()),
        );

        // do the replacements
        $fo = str_replace(array_keys($replace), array_values($replace), $this->getConf("footer_odd"));
        $fe = str_replace(array_keys($replace), array_values($replace), $this->getConf("footer_even"));
        $ho = str_replace(array_keys($replace), array_values($replace), $this->getConf("header_odd"));
        $he = str_replace(array_keys($replace), array_values($replace), $this->getConf("header_even"));

        // set the headers/footers
        $mpdf->SetHeader($ho);
        $mpdf->SetHeader($he, 'E');
        $mpdf->SetFooter($fo);
        $mpdf->SetFooter($fe, 'E');

        // title
        $mpdf->SetTitle($title);
    }

    /**
     * Fix up the HTML a bit
     *
     * FIXME This is far from perfect and will modify things within code and
     * nowiki blocks. It would probably be a good idea to use a real HTML
     * parser or our own renderer instead of modifying the HTML at all.
     */
    protected function arrangeHtml(&$html, $norendertags = '' ) {
        // add bookmark links
        $bmlevel = $this->getConf('maxbookmarks');
        if($bmlevel > 0) {
            $html = preg_replace("/\<a name=(.+?)\>(.+?)\<\/a\>/s",'$2',$html);
            for ($j = 1; $j<=$bmlevel; $j++) {
                $html = preg_replace("/\<h".$j."\>(.+?)\<\/h".$j."\>/s",'<h'.$j.'>$1<bookmark content="$1" level="'.($j-1).'"/></h'.$j.'>',$html);
            }
        }

        // insert a pagebreak for support of WRAP and PAGEBREAK plugins
        $html = str_replace('<br style="page-break-after:always;">','<pagebreak />',$html);
        $html = str_replace('<div class="wrap_pagebreak"></div>','<pagebreak />',$html);
        $html = str_replace('<span class="wrap_pagebreak"></span>','<pagebreak />',$html);

        // Customized to strip all span tags so that the wiki <code> SQL would display properly
        $norender = explode(',',$this->getConf('norender'));
        $this->strip_only($html, $norender);
        $this->strip_htmlencodedchars($html);
    }

    /**
     * Create the citation box
     *
     * @todo can we drop the inline style here?
     */
    protected function citation($page) {
        global $conf;

        $date = filemtime(wikiFN($page));
        $html  = '';
        $html .= "<br><br><div style='font-size: 80%; border: solid 0.5mm #DDDDDD;background-color: #EEEEEE; padding: 2mm; border-radius: 2mm 2mm; width: 100%;'>";
        $html .= "From:<br>";
        $html .= "<a href='".DOKU_URL."'>".DOKU_URL."</a>&nbsp;-&nbsp;"."<b>".$conf['title']."</b>";
        $html .= "<br><br>Permanent link:<br>";
        $html .= "<b><a href='".wl($page, false, true, "&")."'>".wl($page, false, true, "&")."</a></b>";
        $html .= "<br><br>Last update: <b>".dformat($date)."</b><br>";
        $html .= "</div>";
        return $html;
    }

    /**
     * Strip unwanted tags
     *
     * @fixme could this be done by strip_tags?
     * @author Jared Ong
     */
    protected function strip_only(&$str, $tags) {
        if(!is_array($tags)) {
            $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
            if(end($tags) == '') array_pop($tags);
        }
        foreach($tags as $tag) $str = preg_replace('#</?'.$tag.'[^>]*>#is', '', $str);
    }

    /**
     * Replace &#039; &quot; &gt; &lt; &amp;
     *
     * @fixme do we really need this? wouldn't this break things?
     * @fixme and if we really need it, do correct numeric decoding
     */
    protected function strip_htmlencodedchars(&$str) {
        $str = htmlspecialchars_decode($str);
        $str = str_replace('&#039;', '\'', $str);
    }
}
