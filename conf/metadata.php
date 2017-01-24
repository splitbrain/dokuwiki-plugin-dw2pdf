<?php
/*
additional option for meta:
  '_ignore_for_settingstree' =>true --> mark the config settings to not be used by settingstree plugin (at all).
  '_ignore_for_export' => true		--> mark the config settings to not be used by settingstree for export-popup
		
e.g.: 
$meta['admin_only_settingstree']       = array('onoff', '_ignore_for_settingstree'=>true);
This means: you can never change the value of 'admin_only_settingstree' by namespace, it can only be set in config plugin, and it's global.
Note: protected configurations are remain protected in settingstree as well, but that is the wiki maintainer's choice not the plugin authors.

$meta['showexportbutton']       = array('onoff', '_ignore_for_export'=>true);
This means: you won't see the export button settings on an export-popup

*/

$meta['pagesize']         = array('string');
$meta['orientation']      = array('multichoice', '_choices' => array('portrait', 'landscape'));
$meta['doublesided']      = array('onoff');
$meta['toc']              = array('onoff');
$meta['toclevels']        = array('string', '_pattern' => '/^(|[1-5]-[1-5])$/');
$meta['maxbookmarks']     = array('numeric');
$meta['template']         = array('dirchoice', '_dir' => DOKU_PLUGIN . 'dw2pdf/tpl/');
$meta['output']           = array('multichoice', '_choices' => array('browser', 'file'));
$meta['usecache']         = array('onoff');
$meta['usestyles']        = array('string');
$meta['qrcodesize']       = array('string', '_pattern' => '/^(|\d+x\d+)$/');
$meta['showexportbutton'] = array('onoff', '_ignore_for_export'=>true);	// added ignore_for_export, as it obviously does not affect the exported pdf

$meta['enable_settingstree'] = array('onoff','_ignore_for_settingstree'=>true);
$meta['admin_only_settingstree'] = array('onoff','_ignore_for_settingstree'=>true);
$meta['enable_export_config_popup'] = array('onoff', '_ignore_for_export'=>true);	// removed ignore_for_settingstree to allow different export button settings by hierarchy but added ignore_for_export
//$meta['enable_extended_templates'] = array('onoff','_ignore_for_settingstree'=>true);
//$meta['admin_for_templates_variables'] = array('onoff','_ignore_for_settingstree'=>true);


