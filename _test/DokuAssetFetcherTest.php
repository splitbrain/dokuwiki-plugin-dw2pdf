<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\DokuMpdf;
use DokuWikiTest;
use Mpdf\AssetFetcherInterface;
use Mpdf\Mpdf;
use ReflectionProperty;

/**
 * Tests for handing resolved media files over to mpdf.
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class DokuAssetFetcherTest extends DokuWikiTest
{
    /**
     * Get the asset fetcher of a fresh DokuMpdf instance with the given basepath setting.
     *
     * Depending on basepathIsLocal mpdf uses either the first or the second argument of
     * fetchDataFromPath(), so both cases need to be tested. The flag is false on any install
     * where the baseurl host differs from the request host, a non-default port being enough.
     *
     * @param bool $basepathIsLocal Value to force for the mpdf flag
     * @return AssetFetcherInterface
     */
    protected function getFetcher(bool $basepathIsLocal): AssetFetcherInterface
    {
        $mpdf = new DokuMpdf(new Config(), 'en');
        $mpdf->basepathIsLocal = $basepathIsLocal;

        return (new ReflectionProperty(Mpdf::class, 'assetFetcher'))->getValue($mpdf);
    }

    /**
     * @return array<string, array{0:string,1:string,2:bool}>
     */
    public static function fetchProvider(): array
    {
        global $conf;

        $media = DOKU_URL . 'lib/exe/fetch.php?media=wiki:dokuwiki-128.png';
        $mediaFile = $conf['mediadir'] . '/wiki/dokuwiki-128.png';
        $static = DOKU_URL . 'lib/images/throbber.gif';
        $staticFile = DOKU_INC . 'lib/images/throbber.gif';

        return [
            'fetch url, local basepath' => [$media, $mediaFile, true],
            'fetch url, remote basepath' => [$media, $mediaFile, false],
            'static file, local basepath' => [$static, $staticFile, true],
            'static file, remote basepath' => [$static, $staticFile, false],
        ];
    }

    /**
     * The local file has to be read regardless of the basepath setting.
     *
     * The second argument is the unmodified source as mpdf passes it for every image.
     *
     * @dataProvider fetchProvider
     */
    public function testFetchDataFromPathReadsLocalFile(string $source, string $expected, bool $basepathIsLocal): void
    {
        $data = $this->getFetcher($basepathIsLocal)->fetchDataFromPath($source, $source);

        $this->assertSame(file_get_contents($expected), $data);
    }

    /**
     * The resolved file has to win over the source mpdf was originally given.
     *
     * With a local basepath mpdf prefers the second argument whenever it can open it. For a
     * media URL that means an anonymous HTTP request back into the wiki instead of our local
     * copy. A readable file stands in for such a loadable source here.
     */
    public function testFetchDataFromPathIgnoresOriginalSource(): void
    {
        global $conf;
        $file = $conf['mediadir'] . '/wiki/dokuwiki-128.png';
        $source = DOKU_URL . 'lib/exe/fetch.php?media=wiki:dokuwiki-128.png';
        $loadable = DOKU_INC . 'lib/images/throbber.gif';

        $data = $this->getFetcher(true)->fetchDataFromPath($source, $loadable);

        $this->assertSame(file_get_contents($file), $data);
    }

    /**
     * Our pseudo scheme has no stream wrapper, so it can only ever be read locally.
     *
     * @testWith [true]
     *           [false]
     */
    public function testFetchDataFromPathReadsDw2pdfScheme(bool $basepathIsLocal): void
    {
        global $conf;
        $file = $conf['mediadir'] . '/wiki/dokuwiki-128.png';
        $source = 'dw2pdf://' . $file;

        $data = $this->getFetcher($basepathIsLocal)->fetchDataFromPath($source, $source);

        $this->assertSame(file_get_contents($file), $data);
    }
}
