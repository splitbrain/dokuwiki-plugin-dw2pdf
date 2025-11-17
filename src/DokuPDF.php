<?php

// phpcs:disable: PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace dokuwiki\plugin\dw2pdf\src;

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
class DokuPdf extends Mpdf
{
    /**
     * DokuPDF constructor.
     *
     * @param Config $config
     * @throws MpdfException
     * @throws \Exception
     */
    public function __construct(Config $config)
    {

        // FIXME this needs to be passed differently
        // 'ImageProcessorClass' => DokuImageProcessorDecorator::class,
        // either by monkeypatching the property to protected or via reflection

        parent::__construct($config->getMPdfConfig());
        $this->SetDirectionality($config->getDirectionality());

        // configure page numbering
        // https://mpdf.github.io/paging/page-numbering.html
        $this->PageNumSubstitutions[] = ['from' => 1, 'reset' => 0, 'type' => '1', 'suppress' => 'off'];
        // add watermark text if configured
        $this->setWatermarkText($config->getWatermarkText());
    }

    /**
     * Cleanup temp dir
     */
    public function __destruct()
    {
        // FIXME do we still need to clean up ourselves?
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
