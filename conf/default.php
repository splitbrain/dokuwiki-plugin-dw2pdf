<?php

//check if we are running within the DokuWiki environment
if (!defined("DOKU_INC")){
    die();
}

$conf['output']         = 'file';

$conf['norender']         = 'span,acronym';

$conf['footer_odd']     = '@WIKI@ - @WIKIURL@||';
$conf['footer_even']    = '@WIKIURL@||Printed on @DATE@';
$conf['header_odd']     = '@DATE@|@PAGE@/@PAGES@|@TITLE@';
$conf['header_even']    = 'Last update: @UPDATE@|@ID@|@PAGEURL@';

$conf["maxbookmarks"]           = 5; 

$conf["addcitation"]            = true; 
$conf["loadusercss"]            = false; //TRUE: dw2pdf/user/user.css will be loaded

