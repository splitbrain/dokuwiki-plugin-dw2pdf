<?php
/**
 * DokuWiki Plugin dw2pdf (Renderer Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
require_once DOKU_INC.'inc/parser/xhtml.php';

class renderer_plugin_dw2pdf extends Doku_Renderer_xhtml {

    /**
     * Make available as XHTML replacement renderer
     */
    public function canRender($format){
        if($format == 'xhtml') return true;
        return false;
    }

    // FIXME override any methods of Doku_Renderer_xhtml here


    /**
     * Simplified header printing with PDF bookmarks
     */
    function header($text, $level, $pos) {
        if(!$text) return; //skip empty headlines

        // add PDF bookmark
        $bmlevel = $this->getConf('maxbookmarks');
        if($bmlevel && $bmlevel >= $level){
            $this->doc .= '<bookmark content="'.$this->_xmlEntities($text).'" level="'.($level-1).'" />';
        }

        // print header
        $this->doc .= DOKU_LF."<h$level>";
        $this->doc .= $this->_xmlEntities($text);
        $this->doc .= "</h$level>".DOKU_LF;
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

}

