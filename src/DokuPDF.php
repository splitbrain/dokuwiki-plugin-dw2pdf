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
     * @param string $lang The language code to use for this document
     * @throws MpdfException
     */
    public function __construct(Config $config, string $lang)
    {

        // FIXME this needs to be passed differently
        // 'ImageProcessorClass' => DokuImageProcessorDecorator::class,
        // either by monkeypatching the property to protected or via reflection

        $initConfig = $config->getMPdfConfig();
        $initConfig['mode'] = $this->lang2mode($lang);
        parent::__construct($initConfig);
        $this->SetDirectionality($this->lang2direction($lang));

        // configure page numbering
        // https://mpdf.github.io/paging/page-numbering.html
        $this->PageNumSubstitutions[] = ['from' => 1, 'reset' => 0, 'type' => '1', 'suppress' => 'off'];
        // add watermark text if configured
        $this->setWatermarkText($config->getWatermarkText());

        // let mpdf fix local links
        $self = parse_url(DOKU_URL);
        $url = $self['scheme'] . '://' . $self['host'];
        if (!empty($self['port'])) {
            $url .= ':' . $self['port'];
        }
        $this->SetBasePath($url);
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
     * @inheritdoc
     */
    public function GetFullPath(&$path, $basepath = '')
    {
        $path = htmlspecialchars_decode($path);
        parent::GetFullPath($path, $basepath);
    }

    /**
     * Get the mode to use based on the given language
     *
     * @link https://mpdf.github.io/reference/mpdf-functions/construct.html
     * @link https://mpdf.github.io/reference/mpdf-variables/useadobecjk.html
     * @todo it might be more sensible to pass a language string instead
     * @param string $lang
     * @return string
     */
    protected function lang2mode(string $lang): string
    {
        switch ($lang) {
            case 'zh':
            case 'zh-tw':
            case 'ja':
            case 'ko':
                return '+aCJK';
            default:
                return 'UTF-8-s';
        }
    }

    /**
     * Return the writing direction based on the set language
     *
     * @param string $lang
     * @return string
     */
    protected function lang2direction(string $lang): string
    {
        switch ($lang) {
            case 'ar':
            case 'he':
                return 'rtl';
            default:
                return 'ltr';
        }
    }
}
