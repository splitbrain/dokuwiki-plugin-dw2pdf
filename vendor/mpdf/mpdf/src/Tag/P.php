<?php
 
namespace Mpdf\Tag;
 
class P extends InlineTag
{	
 
	public function open($attr, &$ahtml, &$ihtml)
	{	

		parent::open($attr, $ahtml, $ihtml);
		
	}
 
	public function close(&$ahtml, &$ihtml)
	{

		parent::close($ahtml, $ihtml);

		$this->mpdf->_saveTextBuffer("\n");
		$this->mpdf->_saveTextBuffer("\n");
		
	}
		
}
