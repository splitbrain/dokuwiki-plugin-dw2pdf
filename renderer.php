<?php
/**
 * DokuWiki Plugin dw2pdf (Renderer Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Render xhtml suitable as input for mpdf library
 */
class renderer_plugin_dw2pdf extends Doku_Renderer_xhtml {

    private $lastheadlevel = -1;
    private $current_bookmark_level = 0;
    /**
     * Stores action instance
     *
     * @var action_plugin_dw2pdf
     */
    private $actioninstance = null;

    /**
     * load action plugin instance
     */
    public function __construct() {
        $this->actioninstance = plugin_load('action', 'dw2pdf');
    }

    public function document_start() {
        global $ID;

        parent::document_start();

        //ancher for rewritten links to included pages
        $check = false;
        $pid = sectionID($ID, $check);

        $this->doc .= "<a name=\"{$pid}__\">";
        $this->doc .= "</a>";
    }

    /**
     * Make available as XHTML replacement renderer
     */
    public function canRender($format){
        if($format == 'xhtml') return true;
        return false;
    }

    /**
     * Simplified header printing with PDF bookmarks
     */
    function header($text, $level, $pos) {
        if(!$text) return; //skip empty headlines
        global $ID;

        $hid = $this->_headerToLink($text, true);
        $check = false;
        $pid = sectionID($ID, $check);
        $hid = $pid . '__' . $hid;

            // add PDF bookmark
        $bookmark = '';
        $bmlevel = $this->actioninstance->getExportConfig('maxbookmarks');
        if($bmlevel && $bmlevel >= $level){
            // PDF readers choke on invalid nested levels

            if ($this->lastheadlevel == -1)
            	$this->lastheadlevel = $level;

            $step = $level - $this->lastheadlevel;

            if ($step > 0) 
            	$this->current_bookmark_level += 1;
            else if ($step <0)  {
            	$this->current_bookmark_level -= 1;
                if ($this->current_bookmark_level < 0) 
                    $this->current_bookmark_level = 0;
            }

            $this->lastheadlevel = $level;

            $bookmark = '<bookmark content="'.$this->_xmlEntities($text).'" level="'.($this->current_bookmark_level).'" />';
        }

        // print header
        $this->doc .= DOKU_LF."<h$level>$bookmark";
        $this->doc .= "<a name=\"$hid\">";
        $this->doc .= $this->_xmlEntities($text);
        $this->doc .= "</a>";
        $this->doc .= "</h$level>".DOKU_LF;
    }

    /**
     * Render a page local link
     *
     * @param string $hash hash link identifier
     * @param string $name name for the link
     *
     * // modified copy of parent function
     * @see Doku_Renderer_xhtml::locallink
     */
    function locallink($hash, $name = null, $returnonly = false) {
        global $ID;
        $name  = $this->_getLinkTitle($name, $hash, $isImage);
        $hash  = $this->_headerToLink($hash);
        $title = $ID.' ↵';

        $check = false;
        $pid = sectionID($ID, $check);

        $this->doc .= '<a href="#'. $pid . '__' . $hash.'" title="'.$title.'" class="wikilink1">';
        $this->doc .= $name;
        $this->doc .= '</a>';
    }

    /**
     * Wrap centered media in a div to center it
     */
    function _media ($src, $title=NULL, $align=NULL, $width=NULL,
                      $height=NULL, $cache=NULL, $render = true) {

        $out = '';
        if($align == 'center'){
            $out .= '<div align="center" style="text-align: center">';
        }

        $out .= parent::_media ($src, $title, $align, $width, $height, $cache, $render);

        if($align == 'center'){
            $out .= '</div>';
        }

        return $out;
    }

    /**
     * hover info makes no sense in PDFs, so drop acronyms
     */
    function acronym($acronym) {
        $this->doc .= $this->_xmlEntities($acronym);
    }


    /**
     * reformat links if needed
     */

    function _formatLink($link){

        // for internal links contains the title the pageid
        if(in_array($link['title'], $this->actioninstance->getExportedPages())) {
            list(/* $url */, $hash) = explode('#', $link['url'], 2);

            $check = false;
            $pid = sectionID($link['title'], $check);
            $link['url'] = "#" . $pid . '__' . $hash;
        }


        // prefix interwiki links with interwiki icon
        if($link['name'][0] != '<' && preg_match('/\binterwiki iw_(.\w+)\b/',$link['class'],$m)){
            if(file_exists(DOKU_INC.'lib/images/interwiki/'.$m[1].'.png')){
                $img = DOKU_BASE.'lib/images/interwiki/'.$m[1].'.png';
            }elseif(file_exists(DOKU_INC.'lib/images/interwiki/'.$m[1].'.gif')){
                $img = DOKU_BASE.'lib/images/interwiki/'.$m[1].'.gif';
            }else{
                $img = DOKU_BASE.'lib/images/interwiki.png';
            }

            $link['name'] = '<img src="'.$img.'" width="16" height="16" style="vertical-align: center" class="'.$link['class'].'" />'.$link['name'];
        }
        return parent::_formatLink($link);
    }

    /**
     * no obfuscation for email addresses
     */
    function emaillink($address, $name = NULL, $returnonly = false) {
        global $conf;
        $old = $conf['mailguard'];
        $conf['mailguard'] = 'none';
        parent::emaillink($address, $name, $returnonly);
        $conf['mailguard'] = $old;
    }

}

