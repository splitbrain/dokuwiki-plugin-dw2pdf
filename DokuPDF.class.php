<?php
/**
 * Wrapper around the mpdf library class
 *
 * This class overrides some functions to make mpdf make use of DokuWiki'
 * standard tools instead of its own.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */

if(!defined('_MPDF_TEMP_PATH')) define('_MPDF_TEMP_PATH', $conf['tmpdir']);
if(!defined('_MPDF_TTFONTDATAPATH')) define('_MPDF_TTFONTDATAPATH',$conf['cachedir'].'/mpdf_ttf/');
require_once(dirname(__FILE__)."/mpdf/mpdf.php");

class DokuPDF extends mpdf {

    function __construct(){
        io_mkdir_p(_MPDF_TTFONTDATAPATH);

        // we're always UTF-8
        parent::__construct('UTF-8-s');
        $this->SetAutoFont(AUTOFONT_ALL);
        $this->ignore_invalid_utf8 = true;

        // allimage sources are local (see _getImage)
        $this->basepathIsLocal = 1;
    }


    /**
     * Override the mpdf _getImage function
     *
     * This function takes care of gathering the image data from HTTP or
     * local files before passing the data back to mpdf's original function
     * making sure that only cached file paths are passed to mpdf. It also
     * takes care of checking image ACls.
     */
    function _getImage(&$file, $firsttime=true, $allowvector=true, $orig_srcpath=false){
        list($ext,$mime) = mimetype($file);
        if(substr($mime,0,6) == 'image/'){
            // build regex to parse URL back to media info
            $re = preg_quote(ml('xxx123yyy'),'/');
            $re = str_replace('xxx123yyy','([^&\?]*)',$re);


            if(preg_match("/$re/",$file,$m) ||
               preg_match('/[&\?]media=([^&\?]*)/',$file,$m)){
                $media = rawurldecode($m[1]);

                if(preg_match('/[\?&]w=(\d+)/',$file, $m)) $w = $m[1];
                if(preg_match('/[\?&]h=(\d+)/',$file, $m)) $h = $m[1];

                if(preg_match('/^https?:\/\//',$media)){
                    $local = media_get_from_URL($media,$ext,-1);
                    if(!$local) $local = $media; // let mpdf try again
                }else{
                    $media = cleanID($media);
                    //check permissions (namespace only)
                    if(auth_quickaclcheck(getNS($media).':X') < AUTH_READ){
                        $file = '';
                    }
                    $local  = mediaFN($media);
                }

                //handle image resizing/cropping
                if($w){
                    if($h){
                        $local = media_crop_image($local,$ext,$w,$h);
                    }else{
                        $local = media_resize_image($local,$ext,$w,$h);
                    }
                }
            }elseif(preg_match('/^https?:\/\//',$file)){ // fixed external URLs
                $local = media_get_from_URL($file,$ext,$conf['cachetime']);
            }

            if($local){
                $orig_srcpath = $local;
            }
        }

        return parent::_getImage($file, $firsttime, $allowvector, $orig_srcpath);
    }

}
