<?php

// phpcs:disable: PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use dokuwiki\plugin\dw2pdf\DokuImageProcessorDecorator;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * Wrapper around the mpdf library class
 *
 * This class overrides some functions to make mpdf make use of DokuWiki'
 * standard tools instead of its own.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class DokuPDF extends Mpdf
{
    /**
     * DokuPDF constructor.
     *
     * @param string $pagesize
     * @param string $orientation
     * @param int $fontsize
     *
     * @throws MpdfException
     * @throws Exception
     */
    public function __construct($pagesize = 'A4', $orientation = 'portrait', $fontsize = 11, $docLang = 'en')
    {
        global $conf;
        global $lang;

        if (!defined('_MPDF_TEMP_PATH')) {
            define('_MPDF_TEMP_PATH', $conf['tmpdir'] . '/dwpdf/' . random_int(1, 1000) . '/');
        }
        io_mkdir_p(_MPDF_TEMP_PATH);

        $format = $pagesize;
        if ($orientation == 'landscape') {
            $format .= '-L';
        }

        switch ($docLang) {
            case 'zh':
            case 'zh-tw':
            case 'ja':
            case 'ko':
                $mode = '+aCJK';
                break;
            default:
                $mode = 'UTF-8-s';
        }

        parent::__construct([
            'mode' => $mode,
            'format' => $format,
            'default_font_size' => $fontsize,
            'ImageProcessorClass' => DokuImageProcessorDecorator::class,
            'tempDir' => _MPDF_TEMP_PATH, //$conf['tmpdir'] . '/tmp/dwpdf'
            'SHYlang' => $docLang,
        ]);

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
    public function __destruct()
    {
        io_rmdir(_MPDF_TEMP_PATH, true);
    }

    /**
     * Decode all paths, since DokuWiki uses XHTML compliant URLs
     *
     * @param string $path
     * @param string $basepath
     */
    public function GetFullPath(&$path, $basepath = '')
    {
        $path = htmlspecialchars_decode($path);
        parent::GetFullPath($path, $basepath);
    }
}
