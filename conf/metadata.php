<?php
/*
additional option for meta:
  '_ignore_for_settingstree' =>true
mark the config settings to not be used by settingstree plugin.
e.g.: 
$meta['showexportbutton']       = array('onoff', '_ignore_for_settingstree'=>true);
This means: you can never change the value of 'showexportbutton' by namespace, it can only be set in config plugin, and it's global.
Note: protected configurations are remain protected in settingstree as well, but that is the wiki maintainer's choice not the plugin authors.
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
$meta['showexportbutton'] = array('onoff');

$meta['enable_settingstree'] = array('onoff','_ignore_for_settingstree'=>true);
$meta['admin_only_settingstree'] = array('onoff','_ignore_for_settingstree'=>true);
//$meta['enable_export_config_popup'] = array('onoff','_ignore_for_settingstree'=>true);
//$meta['enable_extended_templates'] = array('onoff','_ignore_for_settingstree'=>true);
//$meta['admin_for_templates_variables'] = array('onoff','_ignore_for_settingstree'=>true);


