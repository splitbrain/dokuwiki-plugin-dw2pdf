<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\LocalContentLoader;
use DokuWikiTest;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class LocalContentLoaderTest extends DokuWikiTest
{
    /**
     * dw2pdf:// URLs should be dereferenced directly from disk.
     */
    public function testLoadsDw2pdfSchemeFiles(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'dw2pdf_loader');
        if ($temp === false) {
            $this->markTestSkipped('Unable to create a temporary file');
        }

        $file = $temp . '.png';
        if (!@rename($temp, $file)) {
            $this->fail('Unable to prepare a temporary dw2pdf:// file');
        }
        file_put_contents($file, 'image-bytes');

        $loader = new LocalContentLoader();
        $this->assertSame('image-bytes', $loader->load('dw2pdf://' . $file));

        @unlink($file);
    }

    /**
     * Missing files should return null so mPDF can fall back to HTTP.
     */
    public function testReturnsNullForMissingFiles(): void
    {
        $file = sys_get_temp_dir() . '/dw2pdf_loader_missing.png';
        @unlink($file);

        $loader = new LocalContentLoader();
        $this->assertNull($loader->load($file));
    }
}
