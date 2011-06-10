<?php

//check if we are running within the DokuWiki environment
if (!defined("DOKU_INC")){
    die();
}

$meta['output'] = array('multichoice', '_choices' => array('browser', 'file'));

$meta['norender']    = array('string');

$meta['footer_odd']     = array('string');
$meta['footer_even']    = array('string');
$meta['header_odd']     = array('string');
$meta['header_even']    = array('string');

$meta['maxbookmarks']   = array('numeric'); 

$meta['addcitation']    = array("onoff");
$meta['loadusercss']    = array("onoff");


