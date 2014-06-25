<?php
$meta['output']           = array('multichoice', '_choices' => array('browser', 'file'));
$meta['pagesize']         = array('string');
$meta['orientation']      = array('multichoice', '_choices' => array('portrait', 'landscape'));
$meta['usecache']         = array('onoff');
$meta['template']         = array('dirchoice', '_dir' => DOKU_PLUGIN . 'dw2pdf/tpl/');
$meta['maxbookmarks']     = array('numeric');
$meta['usestyles']        = array('string');
$meta['qrcodesize']       = array('string', '_pattern' => '/^(|\d+x\d+)$/');
$meta['showexportbutton'] = array('onoff');
