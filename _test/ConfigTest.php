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
     * Ensure plugin.conf overrides and transformations are honored before reading user input.
     */
    public function testPluginConfigurationOverridesDefaults(): void
    {
        $config = new Config([
            'pagesize' => 'Letter',
            'orientation' => 'landscape',
            'font-size' => 14,
            'doublesided' => 0,
            'toc' => 1,
            'toclevels' => '2-4',
            'maxbookmarks' => 3,
            'headernumber' => 1,
            'template' => 'default',
            'usestyles' => 'wrap,foo ',
            'watermark' => 'CONFIDENTIAL',
            'qrcodescale' => '2.5',
        ]);

        $this->assertSame('Letter-L', $config->getFormat());
        $this->assertTrue($config->hasToc());
        $this->assertSame(3, $config->getMaxBookmarks());
        $this->assertTrue($config->useNumberedHeaders());
        $this->assertSame(['wrap', 'foo'], $config->getStyledExtensions());
        $this->assertSame('CONFIDENTIAL', $config->getWatermarkText());
        $this->assertSame(2.5, $config->getQRScale());

        $mpdfConfig = $config->getMPdfConfig();
        $this->assertSame('Letter-L', $mpdfConfig['format']);
        $this->assertSame(14, $mpdfConfig['default_font_size']);
        $this->assertTrue($mpdfConfig['showWatermarkText']);
        $this->assertNotEmpty($config->getCacheKey());
    }

    /**
     * Ensure request parameters take precedence over plugin defaults for book export context.
     */
    public function testInputOverridesBookParameters(): void
    {
        global $INPUT;
        $INPUT->set('pagesize', 'Legal');
        $INPUT->set('orientation', 'landscape');
        $INPUT->set('font-size', '9');
        $INPUT->set('doublesided', '0');
        $INPUT->set('toclevels', '3-3');
        $INPUT->set('watermark', 'TOPSECRET');
        $INPUT->set('debug', '1');
        $INPUT->set('book_title', 'My Book');
        $INPUT->set('book_ns', 'playground:sub');
        $INPUT->set('book_order', 'date');
        $INPUT->set('book_nsdepth', 2);
        $INPUT->set('excludes', ['playground:sub:skip']);
        $INPUT->set('excludesns', ['playground:private']);
        $INPUT->set('selection', '["playground:start","playground:Sub:Child"]');
        $INPUT->set('savedselection', 'fav:123');
        $INPUT->set('id', 'Playground:Start ');

        $config = new Config();

        $this->assertSame('Legal-L', $config->getFormat());
        $this->assertTrue($config->isDebugEnabled());
        $this->assertSame('My Book', $config->getBookTitle());
        $this->assertSame('playground:sub', $config->getBookNamespace());
        $this->assertSame('date', $config->getBookSortOrder());
        $this->assertSame(2, $config->getBookNamespaceDepth());
        $this->assertSame(['playground:sub:skip'], $config->getBookExcludedPages());
        $this->assertSame(['playground:private'], $config->getBookExcludedNamespaces());
        $this->assertTrue($config->hasLiveSelection());
        $this->assertSame('["playground:start","playground:Sub:Child"]', $config->getLiveSelection());
        $this->assertTrue($config->hasSavedSelection());
        $this->assertSame('fav:123', $config->getSavedSelection());
        $this->assertSame('playground:start', $config->getExportId());
    }
}
