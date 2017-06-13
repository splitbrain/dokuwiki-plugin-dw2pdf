<?php
/**
 * Wrapper around the mpdf library class
 *
 * This class overrides some functions to make mpdf make use of DokuWiki'
 * standard tools instead of its own.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
global $conf;
if(!defined('_MPDF_TEMP_PATH')) define('_MPDF_TEMP_PATH', $conf['tmpdir'] . '/dwpdf/' . rand(1, 1000) . '/');
if(!defined('_MPDF_TTFONTDATAPATH')) define('_MPDF_TTFONTDATAPATH', $conf['cachedir'] . '/mpdf_ttf/');

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Class DokuPDF
 * Some DokuWiki specific extentions
 */
class DokuPDF extends \Mpdf\Mpdf {

    function __construct($pagesize = 'A4', $orientation = 'portrait', $fontsize = 11) {
        global $conf;

        io_mkdir_p(_MPDF_TTFONTDATAPATH);
        io_mkdir_p(_MPDF_TEMP_PATH);

        $format = $pagesize;
        if($orientation == 'landscape') {
            $format .= '-L';
        }

        switch($conf['lang']) {
            case 'zh':
            case 'zh-tw':
            case 'ja':
            case 'ko':
                $mode = '+aCJK';
                break;
            default:
                $mode = 'UTF-8-s';

        }

        // we're always UTF-8
        parent::__construct(
            array(
                'mode' => $mode,
                'format' => $format,
                'fontsize' => $fontsize
            )
        );
        $this->autoScriptToLang = true;
        $this->baseScript = 1;
        $this->autoVietnamese = true;
        $this->autoArabic = true;
        $this->autoLangToFont = true;

        $this->ignore_invalid_utf8 = true;
        $this->tabSpaces = 4;
    }

    /**
     * Cleanup temp dir
     */
    function __destruct() {
        io_rmdir(_MPDF_TEMP_PATH, true);
    }

    /**
     * Decode all paths, since DokuWiki uses XHTML compliant URLs
     */
    function GetFullPath(&$path, $basepath = '') {
        $path = htmlspecialchars_decode($path);
        parent::GetFullPath($path, $basepath);
    }

    /**
     * Override the mpdf _getImage function
     *
     * This function takes care of gathering the image data from HTTP or
     * local files before passing the data back to mpdf's original function
     * making sure that only cached file paths are passed to mpdf. It also
     * takes care of checking image ACls.
     */
    function _getImage(&$file, $firsttime = true, $allowvector = true, $orig_srcpath = false, $interpolation = false) {
        global $conf;

        // build regex to parse URL back to media info
        $re = preg_quote(ml('xxx123yyy', '', true, '&', true), '/');
        $re = str_replace('xxx123yyy', '([^&\?]*)', $re);

        // extract the real media from a fetch.php uri and determine mime
        if(preg_match("/^$re/", $file, $m) ||
            preg_match('/[&\?]media=([^&\?]*)/', $file, $m)
        ) {
            $media = rawurldecode($m[1]);
            list($ext, $mime) = mimetype($media);
        } else {
            list($ext, $mime) = mimetype($file);
        }

        // local files
        $local = '';
        if(substr($file, 0, 9) == 'dw2pdf://') {
            // support local files passed from plugins
            $local = substr($file, 9);
        } elseif(!preg_match('/(\.php|\?)/', $file)) {
            $re = preg_quote(DOKU_URL, '/');
            // directly access local files instead of using HTTP, skip dynamic content
            $local = preg_replace("/^$re/i", DOKU_INC, $file);
        }

        if(substr($mime, 0, 6) == 'image/') {
            if(!empty($media)) {
                // any size restrictions?
                $w = $h = 0;
                if(preg_match('/[\?&]w=(\d+)/', $file, $m)) $w = $m[1];
                if(preg_match('/[\?&]h=(\d+)/', $file, $m)) $h = $m[1];

                if(media_isexternal($media)) {
                    $local = media_get_from_URL($media, $ext, -1);
                    if(!$local) $local = $media; // let mpdf try again
                } else {
                    $media = cleanID($media);
                    //check permissions (namespace only)
                    if(auth_quickaclcheck(getNS($media) . ':X') < AUTH_READ) {
                        $file = '';
                    }
                    $local = mediaFN($media);
                }

                //handle image resizing/cropping
                if($w && file_exists($local)) {
                    if($h) {
                        $local = media_crop_image($local, $ext, $w, $h);
                    } else {
                        $local = media_resize_image($local, $ext, $w, $h);
                    }
                }
            } elseif(media_isexternal($file)) { // fixed external URLs
                $local = media_get_from_URL($file, $ext, $conf['cachetime']);
            }

            if($local) {
                $file = $local;
                $orig_srcpath = $local;
            }
        }

        return parent::_getImage($file, $firsttime, $allowvector, $orig_srcpath, $interpolation);
    }

}
