<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\Config;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class ConfigTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Check default values as set in the Config class
     */
    public function testDefaults(): void
    {
        global $conf, $ID;
        $ID = '';

        $config = new Config();
        $mpdfConfig = $config->getMPdfConfig();

        $this->assertSame('A4', $config->getFormat(), 'default pagesize/orientation');
        $this->assertFalse($config->hasToc(), 'default toc');
        $this->assertSame(5, $config->getMaxBookmarks(), 'default maxbookmarks');
        $this->assertFalse($config->useNumberedHeaders(), 'default headernumber');
        $this->assertSame('', $config->getWatermarkText(), 'default watermark');
        $this->assertSame('default', $config->getTemplateName(), 'default template');
        $this->assertSame('file', $config->getOutputTarget(), 'default output');
        $this->assertSame([], $config->getStyledExtensions(), 'default usestyles');
        $this->assertSame(0.0, $config->getQRScale(), 'default qrcodescale');
        $this->assertFalse($config->isDebugEnabled(), 'default debug');
        $this->assertNull($config->getBookTitle(), 'default book_title');
        $this->assertSame('', $config->getBookNamespace(), 'default book_ns');
        $this->assertSame('natural', $config->getBookSortOrder(), 'default book_order');
        $this->assertSame(0, $config->getBookNamespaceDepth(), 'default book_nsdepth');
        $this->assertSame([], $config->getBookExcludedPages(), 'default excludes');
        $this->assertSame([], $config->getBookExcludedNamespaces(), 'default excludesns');
        $this->assertFalse($config->hasLiveSelection(), 'default selection flag');
        $this->assertNull($config->getLiveSelection(), 'default selection');
        $this->assertFalse($config->hasSavedSelection(), 'default savedselection flag');
        $this->assertNull($config->getSavedSelection(), 'default savedselection');
        $this->assertSame('', $config->getExportId(), 'default exportid');

        $this->assertSame('A4', $mpdfConfig['format'], 'default pagesize/orientation');
        $this->assertSame(11, $mpdfConfig['default_font_size'], 'default font-size');
        $this->assertSame($conf['tmpdir'] . '/mpdf', $mpdfConfig['tempDir'], 'default tmpdir');
        $this->assertFalse($mpdfConfig['mirrorMargins'], 'default doublesided');
        $this->assertSame([], $mpdfConfig['h2toc'], 'default toc levels');
        $this->assertFalse($mpdfConfig['showWatermarkText'], 'default watermark');
        $this->assertSame('stretch', $mpdfConfig['setAutoTopMargin'], 'default mpdf auto top margin');
        $this->assertSame('stretch', $mpdfConfig['setAutoBottomMargin'], 'default mpdf auto bottom margin');
        $this->assertTrue($mpdfConfig['autoScriptToLang'], 'default mpdf autoScriptToLang');
        $this->assertSame(1, $mpdfConfig['baseScript'], 'default mpdf baseScript');
        $this->assertTrue($mpdfConfig['autoVietnamese'], 'default mpdf autoVietnamese');
        $this->assertTrue($mpdfConfig['autoArabic'], 'default mpdf autoArabic');
        $this->assertTrue($mpdfConfig['autoLangToFont'], 'default mpdf autoLangToFont');
        $this->assertTrue($mpdfConfig['ignore_invalid_utf8'], 'default mpdf ignore_invalid_utf8');
        $this->assertSame(4, $mpdfConfig['tabSpaces'], 'default mpdf tabSpaces');
    }

    /**
     * Ensure overrides from config work as expected
     */
    public function testloadPluginConfig(): void
    {
        $config = new Config([
            'exportid' => 'playground:start',
            'pagesize' => 'Legal',
            'orientation' => 'landscape',
            'font-size' => 14,
            'doublesided' => 0,
            'toc' => 1,
            'toclevels' => '2-4',
            'maxbookmarks' => 3,
            'headernumber' => 1,
            'template' => 'modern',
            'output' => 'inline',
            'usestyles' => 'wrap,foo ',
            'watermark' => 'CONFIDENTIAL',
            'qrcodescale' => '2.5',
            'debug' => 1,
            'booktitle' => 'My Book',
            'booknamespace' => 'playground:sub',
            'booksortorder' => 'date',
            'booknamespacedepth' => 2,
            'bookexcludepages' => ['playground:sub:skip'],
            'bookexcludenamespaces' => ['playground:private'],
            'liveselection' => '["playground:start","playground:Sub:Child"]',
            'savedselection' => 'fav:123',
        ]);
        $mpdfConfig = $config->getMPdfConfig();

        $this->assertSame('playground:start', $config->getExportId(), 'from exportid');
        $this->assertSame('Legal-L', $config->getFormat(), 'from pagesize + orientation');
        $this->assertSame(14, $mpdfConfig['default_font_size'], 'from font-size');
        $this->assertFalse($mpdfConfig['mirrorMargins'], 'from doublesided');
        $this->assertTrue($config->hasToc(), 'from toc');
        $this->assertSame(['H2' => 1, 'H3' => 2, 'H4' => 3], $mpdfConfig['h2toc'], 'from toclevels');
        $this->assertSame(3, $config->getMaxBookmarks(), 'from maxbookmarks');
        $this->assertTrue($config->useNumberedHeaders(), 'from headernumber');
        $this->assertSame('modern', $config->getTemplateName(), 'from template');
        $this->assertSame('inline', $config->getOutputTarget(), 'from output');
        $this->assertSame(['wrap', 'foo'], $config->getStyledExtensions(), 'from usestyles');
        $this->assertSame('CONFIDENTIAL', $config->getWatermarkText(), 'from watermark');
        $this->assertTrue($mpdfConfig['showWatermarkText'], 'from watermark');
        $this->assertSame(2.5, $config->getQRScale(), 'from qrcodescale');
        $this->assertTrue($config->isDebugEnabled(), 'from debug');
        $this->assertSame('My Book', $config->getBookTitle(), 'from booktitle');
        $this->assertSame('playground:sub', $config->getBookNamespace(), 'from booknamespace');
        $this->assertSame('date', $config->getBookSortOrder(), 'from booksortorder');
        $this->assertSame(2, $config->getBookNamespaceDepth(), 'from booknamespacedepth');
        $this->assertSame(['playground:sub:skip'], $config->getBookExcludedPages(), 'from bookexcludepages');
        $this->assertSame(['playground:private'], $config->getBookExcludedNamespaces(), 'from bookexcludenamespaces');
        $this->assertTrue($config->hasLiveSelection(), 'from liveselection');
        $this->assertSame('["playground:start","playground:Sub:Child"]', $config->getLiveSelection(), 'from liveselection');
        $this->assertTrue($config->hasSavedSelection(), 'from savedselection');
        $this->assertSame('fav:123', $config->getSavedSelection(), 'from savedselection');
        $this->assertNotEmpty($config->getCacheKey(), 'from combined plugin config values');
    }

    /**
     * Ensure toc levels are set to DokuWiki's default when toc is enabled but no levels are set
     */
    public function testDefaultTocLevels()
    {
        $config = new Config(['toc' => 1]);
        $mpdfConfig = $config->getMPdfConfig();
        $this->assertSame(['H1' => 0, 'H2' => 1, 'H3' => 2], $mpdfConfig['h2toc'], 'from toclevels');
    }

    /**
     * Ensure request parameters take precedence over defaults
     */
    public function testloadInputConfig(): void
    {
        global $INPUT, $ID;
        $ID = 'playground:start';
        $INPUT->set('pagesize', 'Legal');
        $INPUT->set('orientation', 'landscape');
        $INPUT->set('font-size', '14');
        $INPUT->set('doublesided', '0');
        $INPUT->set('toc', '1');
        $INPUT->set('toclevels', '2-4');
        $INPUT->set('watermark', 'CONFIDENTIAL');
        $INPUT->set('tpl', 'modern');
        $INPUT->set('debug', '1');
        $INPUT->set('outputTarget', 'inline');
        $INPUT->set('book_title', 'My Book');
        $INPUT->set('book_ns', 'playground:sub');
        $INPUT->set('book_order', 'date');
        $INPUT->set('book_nsdepth', 2);
        $INPUT->set('excludes', ['playground:sub:skip']);
        $INPUT->set('excludesns', ['playground:private']);
        $INPUT->set('selection', '["playground:start","playground:Sub:Child"]');
        $INPUT->set('savedselection', 'fav:123');


        $config = new Config();
        $mpdfConfig = $config->getMPdfConfig();

        $this->assertSame('playground:start', $config->getExportId(), 'from $ID');
        $this->assertSame('Legal-L', $config->getFormat(), 'from pagesize + orientation');
        $this->assertSame(14, $mpdfConfig['default_font_size'], 'from font-size');
        $this->assertFalse($mpdfConfig['mirrorMargins'], 'from doublesided');
        $this->assertSame(['H2' => 1, 'H3' => 2, 'H4' => 3], $mpdfConfig['h2toc'], 'from toclevels');
        $this->assertTrue($mpdfConfig['showWatermarkText'], 'from watermark');
        $this->assertSame('CONFIDENTIAL', $config->getWatermarkText(), 'from watermark');
        $this->assertSame('modern', $config->getTemplateName(), 'from tpl');
        $this->assertTrue($config->isDebugEnabled(), 'from debug');
        $this->assertSame('inline', $config->getOutputTarget(), 'from outputTarget');
        $this->assertSame('My Book', $config->getBookTitle(), 'from book_title');
        $this->assertSame('playground:sub', $config->getBookNamespace(), 'from book_ns');
        $this->assertSame('date', $config->getBookSortOrder(), 'from book_order');
        $this->assertSame(2, $config->getBookNamespaceDepth(), 'from book_nsdepth');
        $this->assertSame(['playground:sub:skip'], $config->getBookExcludedPages(), 'from excludes');
        $this->assertSame(['playground:private'], $config->getBookExcludedNamespaces(), 'from excludesns');
        $this->assertTrue($config->hasLiveSelection(), 'from selection');
        $this->assertSame('["playground:start","playground:Sub:Child"]', $config->getLiveSelection(), 'from selection');
        $this->assertTrue($config->hasSavedSelection(), 'from savedselection');
        $this->assertSame('fav:123', $config->getSavedSelection(), 'from savedselection');

    }


}
