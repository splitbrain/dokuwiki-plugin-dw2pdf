<?php
/**
 * DokuWiki Plugin dw2pdf (Syntax Component)
 *
 * For marking changes in page orientation.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sam Wilson <sam@samwilson.id.au>
 */
/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

/**
 * Syntax for page specific directions for mpdf library
 */
class syntax_plugin_dw2pdf_exportlink extends DokuWiki_Syntax_Plugin {

    /**
     * Syntax Type
     *
     * Needs to return one of the mode types defined in $PARSER_MODES in parser.php
     *
     * @return string
     */
    public function getType() {
        return 'substition';
    }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    public function getSort() {
        return 41;
    }

    /**
     * @param string $mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~PDFNS>(.*?)\|(.*?)~~', $mode, 'plugin_dw2pdf_pagesetting');
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $ns = substr($match,8,strpos($match,'|')-8);
        $title = substr($match,strpos($match,'|')-8);
        return array('ns' => $ns, 'title' => $title, $state, $pos);
    }

    /**
     * Handles the actual output creation.
     *
     * @param string        $mode     output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array         $data     data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode == 'xhtml') {
            $link = '[[?do=export_pdfns&pdfns_ns=' . $data['ns'] . '&pdfns_title=' . $data['title'] . '|PDF-Export]]';
            $renderer->cdata($link);
            return true;
        }
        return false;
    }

}