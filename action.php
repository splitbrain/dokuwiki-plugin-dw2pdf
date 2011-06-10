<?php
 /**
 * dw2Pdf Plugin: Conversion from dokuwiki content to pdf.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once (DOKU_PLUGIN . 'action.php');
 
class action_plugin_dw2pdf extends DokuWiki_Action_Plugin
{
    /**
     * Constructor.
     */
    function action_plugin_dw2pdf(){
    }

    /**
     * return some info
     */
    function getInfo(){
      return array (
        'author' => 'Luigi Micco',
        'email' => 'l.micco@tiscali.it',
        'date' => '2010-02-04',
        'name' => 'Dw2Pdf plugin (action component)',
        'desc' => 'DokuWiki to Pdf converter',
        'url' => 'http://www.bitlibero.com/dokuwiki/dw2pdf-02.04.2010.zip',
      );
    }

    /**
     * Register the events
     */
    function register(&$controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'convert',array());
    }

    function convert(&$event, $param)
    {
      global $ACT;
      global $REV;
      global $ID;
      global $conf;

      if (( $ACT == 'export_pdfbook' ) || ( $ACT == 'export_pdf' )) {
        // check user's rights
        if ( auth_quickaclcheck($ID) < AUTH_READ ) {
          return false;
        }  

        $event->preventDefault();
        
        require_once(dirname(__FILE__)."/mpdf/mpdf.php");
        $mpdf=new mPDF('UTF-8-s'); 
        $mpdf->SetAutoFont(AUTOFONT_ALL);
      
        // Temp dir
        define("_MPDF_TEMP_PATH", $conf['savedir'].'/tmp/');

        $mpdf->ignore_invalid_utf8 = true;
        $mpdf->mirrorMargins = 1;	// Use different Odd/Even headers and footers and mirror margins

        $mpdf->defaultheaderfontsize = 8;	/* in pts */
        $mpdf->defaultheaderfontstyle = '';	/* blank, B, I, or BI */
        $mpdf->defaultheaderline = 1; 	/* 1 to include line below header/above footer */

        $mpdf->defaultfooterfontsize = 8;	/* in pts */
        $mpdf->defaultfooterfontstyle = '';	/* blank, B, I, or BI */
        $mpdf->defaultfooterline = 1; 	/* 1 to include line below header/above footer */
      
        $html = '<html><head>';
        $html = $html . "<style>
        table {
          border: 1px solid #808080;
          border-collapse: collapse;
        }
        td, th {
          border: 1px solid #808080;
        }";   
        
        //load userdefined CSS?
        if ($this->getConf("loadusercss") && @file_exists(DOKU_PLUGIN.'dw2pdf/user/user.css')) {
          $html = $html . io_readFile(DOKU_PLUGIN.'dw2pdf/user/user.css');
        }        
        $html = $html . "</style>";
        $html = $html . '</head><body>';

        
        if ( $ACT == 'export_pdf' ) {
          $list = array();
          $list[0] = $ID;
        } else {
          if (isset($_COOKIE['list-pagelist'])) {
            $list = explode("|", $_COOKIE['list-pagelist']);
          }  
          if ($_GET['pdfbook_title']) {
            $pdftitle = $_GET['pdfbook_title'];
          } else {  
            $pdftitle = $conf['title'];
          }  
        }

        for ($n = 0; $n < count($list); $n++) {            
          $page = $list[$n];
          
          $idparam = $page;
          if ($REV != 0) {  $idparam = $idparam."&rev=".$REV; };

          $pos = strrpos(utf8_decode($ID), ':');
          $pageName = p_get_first_heading($ID);
          if($pageName == NULL) {
            if($pos != FALSE) {
              $pageName = utf8_substr($page, $pos+1, utf8_strlen($page));
            } else {
              $pageName = $page;
            }
            $pageName = str_replace('_', ' ', $pageName);
          }
                    
          $iddata = p_get_metadata($page,'date');

          $html = $html . p_wiki_xhtml($page,$REV,false);

          if ($n == 0) {
            // standard replacements
            $replace = array(
                    '@ID@'   => $ID,
                    '@PAGE@' => '{PAGENO}',
                    '@PAGES@' => '{nb}',
                    '@TITLE@' => $pageName,
                    '@WIKI@' => $conf['title'],
                    '@WIKIURL@' => DOKU_URL,
                    '@UPDATE@' => dformat($iddata['modified']),
                    '@PAGEURL@' => wl($idparam, false, true, "&"),
                    '@DATE@' => strftime($conf['dformat']),
                    );
          
            // do the replace
            $footer_odd = str_replace(array_keys($replace), array_values($replace), $this->getConf("footer_odd"));
            $footer_even = str_replace(array_keys($replace), array_values($replace), $this->getConf("footer_even"));
            $header_odd = str_replace(array_keys($replace), array_values($replace), $this->getConf("header_odd"));
            $header_even = str_replace(array_keys($replace), array_values($replace), $this->getConf("header_even"));

            $mpdf->SetHeader($header_odd);
            $mpdf->SetHeader($header_even, 'E');
            
            $mpdf->SetFooter($footer_odd);
            $mpdf->SetFooter($footer_even, 'E');
          }  

          $html = $this->citation($html, $conf['title'], $idparam, $iddata, $this->getConf('addcitation'));
          
          if ($n < (count($list) - 1)) $html = $html . "<pagebreak />";
          
        }

        $html = $this->arrangeHtml($html, $this->getConf("maxbookmarks"), $this->getConf("norender"));

        $mpdf->SetTitle($pageName);
        $mpdf->WriteHTML($html);

        if (count($list) == 1) $pdftitle = $pageName;

        $output = 'I';
        if($this->getConf('output') == 'file') $output = 'D';
        $mpdf->Output(urlencode($pdftitle).'.pdf', $output);

        die();
      }
    }

    // thanks to Jared Ong
    // Custom function for help in stripping span tags
    function strip_only($str, $tags) {
      if(!is_array($tags)) {
          $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
          if(end($tags) == '') array_pop($tags);
      }
      foreach($tags as $tag) $str = preg_replace('#</?'.$tag.'[^>]*>#is', '', $str);
      return $str;
    }
    // Custom function for help in stripping span tags

    // Custom function for help in replacing &#039; &quot; &gt; &lt; &amp;
    function strip_htmlencodedchars($str) {
      $str = str_replace('&#039;', '\'', $str);
      $str = str_replace('&quot;', '"', $str);
      $str = str_replace('&gt;', '>', $str);
      $str = str_replace('&lt;', '<', $str);
      $str = str_replace('&amp;', '&', $str);
      return $str;
    }
    // Custom function for help in replacing &#039; &quot; &gt; &lt; &amp;


    function arrangeHtml($html, $bookmark = 0, $norendertags = '' ) {
    
      // add bookmark links
      if ($bookmark > 0) {
        $html = preg_replace("/\<a name=(.+?)\>(.+?)\<\/a\>/s",'$2',$html);
        for ($j = 1; $j<=$bookmark; $j++) { 
          $html = preg_replace("/\<h".$j."\>(.+?)\<\/h".$j."\>/s",'<h'.$j.'>$1<bookmark content="$1" level="'.($j-1).'"/></h'.$j.'>',$html);
        }
      }  
      // add bookmark links

      // insert a pagebreak for support of WRAP and PAGEBREAK plugins 
      $html = str_replace('<br style="page-break-after:always;">','<pagebreak />',$html);
      $html = str_replace('<div class="wrap_pagebreak"></div>','<pagebreak />',$html);

      // thanks to Jared Ong
      // Customized to strip all span tags so that the wiki <code> SQL would display properly
      $norender = explode(',',$norendertags);
      $html = $this->strip_only($html, $norender ); //array('span','acronym'));
      $html = $this->strip_htmlencodedchars($html);
      // Customized to strip all span tags so that the wiki <code> SQL would display properly

      $html = str_replace('href="/','href="http://'.$_SERVER['HTTP_HOST'].'/',$html);

      return $html;
    }
    
    function citation($html, $title, $idparam, $date, $flag = false) {

      if($flag) {
        $html = $html . "<br><br><div style='font-size: 80%; border: solid 0.5mm #DDDDDD;background-color: #EEEEEE; padding: 2mm; border-radius: 2mm 2mm; width: 100%;'>";
        $html = $html . "From:<br>";
        $html = $html . "<a href='".DOKU_URL."'>".DOKU_URL."</a>&nbsp;-&nbsp;"."<b>".$title."</b>";
        $html = $html . "<br><br>Permanent link:<br>";
        $html = $html . "<b><a href='".wl($idparam, false, true, "&")."'>".wl($idparam, false, true, "&")."</a></b>";
        $html = $html . "<br><br>Last update: <b>".dformat($date['modified'])."</b><br>";
        $html = $html . "</div>";
      }  
      return $html;
    }

}
?>
