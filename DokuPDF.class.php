<?php
/**
 * Wrapper around the mpdf library class
 *
 * This class overrides some functions to make mpdf make use of DokuWiki'
 * standard tools instead of its own.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\plugin\dw2pdf\DokuImageProcessorDecorator;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Class DokuPDF
 * Some DokuWiki specific extentions
 */
class DokuPDF extends \Mpdf\Mpdf
{

    /**
     * DokuPDF constructor.
     *
     * @param string $pagesize
     * @param string $orientation
     * @param int $fontsize
     */
    function __construct($pagesize = 'A4', $orientation = 'portrait', $fontsize = 11)
    {
        global $conf, $lang;

        if (!defined('_MPDF_TEMP_PATH')) define('_MPDF_TEMP_PATH', $conf['tmpdir'] . '/dwpdf/' . rand(1, 1000) . '/');
        io_mkdir_p(_MPDF_TEMP_PATH);

        $format = $pagesize;
        if ($orientation == 'landscape') {
            $format .= '-L';
        }

        switch ($conf['lang']) {
            case 'zh':
            case 'zh-tw':
            case 'ja':
            case 'ko':
                $mode = '+aCJK';
                break;
            default:
                $mode = 'UTF-8-s';

        }

        $proxy = $proxyAuth = null;
        if (isset($conf['proxy']['host'])) {
            $proxy = "http";
            if ($conf['proxy']['ssl'] ?? false) {
                $proxy .= "s";
            }
            $proxy .= "://" . $conf['proxy']['host'] . ":" . ($conf['proxy']['port'] ?? 3128);
            if (isset($conf['proxy']['user']) && isset($conf['proxy']['pass'])) {
                $proxyAuth = urlencode($conf['proxy']['user']) . "@" . urlencode($conf['proxy']['pass']);
            }
        }

        // we're always UTF-8
        parent::__construct(
            array(
                'mode' => $mode,
                'format' => $format,
                'default_font_size' => $fontsize,
                'curlProxy' => $proxy,
                'curlProxyAuth' => $proxyAuth,
                'ImageProcessorClass' => DokuImageProcessorDecorator::class,
                'tempDir' => _MPDF_TEMP_PATH //$conf['tmpdir'] . '/tmp/dwpdf'
            )
        );

        $this->autoScriptToLang = true;
        $this->baseScript = 1;
        $this->autoVietnamese = true;
        $this->autoArabic = true;
        $this->autoLangToFont = true;

        $this->ignore_invalid_utf8 = true;
        $this->tabSpaces = 4;

        // assumed that global language can be used, maybe Bookcreator needs more nuances?
        $this->SetDirectionality($lang['direction']);
    }

    /**
     * Cleanup temp dir
     */
    function __destruct()
    {
        io_rmdir(_MPDF_TEMP_PATH, true);
    }

    /**
     * Decode all paths, since DokuWiki uses XHTML compliant URLs
     *
     * @param string $path
     * @param string $basepath
     */
    function GetFullPath(&$path, $basepath = '')
    {
        $path = htmlspecialchars_decode($path);
        parent::GetFullPath($path, $basepath);
    }
}
