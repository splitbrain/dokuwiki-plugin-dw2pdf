<?php
/**
 * DokuWiki Plugin dw2pdf (Syntax Component) 
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps    
 */
 
// must be run within DokuWiki
if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_dw2pdf extends DokuWiki_Syntax_Plugin {
 
 
	function getType() { return 'substition'; }
	function getSort() { return 32; }
 
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern('<pdfvars[^>]*>[^<]*</pdfvars>',$mode,'plugin_dw2pdf');
	}
 
	function handle($match, $state, $pos, Doku_Handler &$handler) {
		return array($match, $state, $pos);
	}
 
	function render($mode, Doku_Renderer &$renderer, $data) {
	// $data is what the function handle return'ed.
		if($mode == 'xhtml'){
			$renderer->doc .="";
			return true;
		}
		return false;
	}
}

