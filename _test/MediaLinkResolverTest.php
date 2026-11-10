<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\MediaLinkResolver;
use DokuWikiTest;

/**
 * Tests for translating Dokuwiki media references into local cached files.
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class MediaLinkResolverTest extends DokuWikiTest
{
    private $resolver;

    public function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MediaLinkResolver(new Config());
    }

    /**
     * @return array<string, array{0:string,1:string,2:string}>
     */
    public static function resolveProvider(): array
    {
        global $conf;

        return [
            'internal fetch url' => [
                DOKU_URL . 'lib/exe/fetch.php?media=wiki:dokuwiki-128.png',
                $conf['mediadir'] . '/wiki/dokuwiki-128.png',
                'image/png',
            ],
            'static local file' => [
                DOKU_URL . 'lib/images/throbber.gif',
                DOKU_INC . 'lib/images/throbber.gif',
                'image/gif',
            ],
        ];
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolveReturnsLocalPathAndMime(string $input, string $expectedPath, string $expectedMime): void
    {
        $resolved = $this->resolver->resolve($input);

        $this->assertNotNull($resolved);
        $this->assertSame($expectedPath, $resolved['path']);
        $this->assertSame($expectedMime, $resolved['mime']);
        $this->assertFileExists($resolved['path']);
    }

    /**
     * The resolver must support our dw2pdf:// pseudo scheme for temporary files.
     */
    public function testResolveDw2pdfScheme(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'dw2pdf');
        if ($temp === false) {
            $this->fail('Unable to create temp file for dw2pdf:// test');
        }

        $image = $temp . '.png';
        if (!rename($temp, $image)) {
            $this->fail('Unable to rename temp file for dw2pdf:// test');
        }

        $resolved = $this->resolver->resolve('dw2pdf://' . $image);

        $this->assertNotNull($resolved);
        $this->assertSame($image, $resolved['path']);
        $this->assertSame('image/png', $resolved['mime']);

        @unlink($image);
    }

    /**
     * External media is downloaded even when the wiki does not allow fetch.php to do so.
     *
     * @group internet
     */
    public function testResolveFetchesExternalMedia(): void
    {
        global $conf;
        $conf['fetchsize'] = 0;
        $resolver = new MediaLinkResolver(new Config(['fetchsize' => 512 * 1024]));

        $external = 'https://php.net/images/php.gif';
        $input = DOKU_URL . 'lib/exe/fetch.php?media=' . rawurlencode($external);
        $resolved = $resolver->resolve($input);

        if ($resolved === null) {
            $this->markTestSkipped('External media fetching is not available in this environment.');
        }

        $this->assertNotNull($resolved);
        $this->assertFileExists($resolved['path']);
        $this->assertSame('image/gif', $resolved['mime']);
        $this->assertSame(2523, filesize($resolved['path']));
    }

    /**
     * Downloading external media can be switched off in the plugin configuration.
     */
    public function testResolveSkipsExternalMediaWhenDownloadingIsDisabled(): void
    {
        $resolver = new MediaLinkResolver(new Config(['fetchsize' => 0]));

        $external = 'https://php.net/images/php.gif';
        $input = DOKU_URL . 'lib/exe/fetch.php?media=' . rawurlencode($external);

        $this->assertNull($resolver->resolve($input));
    }

    /**
     * Media marked nocache is still downloaded.
     *
     * @group internet
     */
    public function testResolveFetchesNocacheExternalMedia(): void
    {
        $resolver = new MediaLinkResolver(new Config(['fetchsize' => 512 * 1024]));

        $external = 'https://php.net/images/php.gif';
        $input = DOKU_URL . 'lib/exe/fetch.php?media=' . rawurlencode($external) . '&cache=nocache';
        $resolved = $resolver->resolve($input);

        if ($resolved === null) {
            $this->markTestSkipped('External media fetching is not available in this environment.');
        }

        $this->assertSame(2523, filesize($resolved['path']));
    }

    /**
     * Without a cache parameter an existing copy is used no matter how old it is.
     */
    public function testResolveKeepsCachedExternalMediaByDefault(): void
    {
        $external = 'http://127.0.0.1:1/unreachable.png';
        $cacheFile = $this->seedOutdatedCache($external);

        $resolver = new MediaLinkResolver(new Config(['fetchsize' => 1024 * 1024]));
        $input = DOKU_URL . 'lib/exe/fetch.php?media=' . rawurlencode($external);

        $resolved = $resolver->resolve($input);

        $this->assertNotNull($resolved);
        $this->assertSame($cacheFile, $resolved['path']);
    }

    /**
     * Media marked recache expires, so an outdated copy is fetched again.
     */
    public function testResolveExpiresCachedExternalMediaOnRecache(): void
    {
        $external = 'http://127.0.0.1:1/unreachable.png';
        $this->seedOutdatedCache($external);

        $resolver = new MediaLinkResolver(new Config(['fetchsize' => 1024 * 1024]));
        $input = DOKU_URL . 'lib/exe/fetch.php?media=' . rawurlencode($external) . '&cache=recache';

        $this->assertNull($resolver->resolve($input));
    }

    /**
     * Write a week old cache file for the given external media URL.
     *
     * @param string $url The external media URL.
     * @return string Absolute path to the cache file.
     */
    protected function seedOutdatedCache(string $url): string
    {
        $cacheFile = getCacheName(strtolower($url), '.media.png');
        io_saveFile($cacheFile, 'outdated bytes');
        touch($cacheFile, time() - 7 * 86400);

        return $cacheFile;
    }

    /**
     * Non-image payloads should never be returned to the PDF generator.
     */
    public function testResolveRejectsNonImages(): void
    {
        $resolved = $this->resolver->resolve(DOKU_URL . 'README');

        $this->assertNull($resolved);
    }

    /**
     * Resizing parameters from fetch.php should trigger scaled copies in cache.
     */
    public function testResolveAppliesResizeParameter(): void
    {
        global $conf;

        $input = DOKU_URL . 'lib/exe/fetch.php?w=32&media=wiki:dokuwiki-128.png';
        $original = $conf['mediadir'] . '/wiki/dokuwiki-128.png';
        $resolved = $this->resolver->resolve($input);

        $this->assertNotNull($resolved);
        $this->assertNotSame($original, $resolved['path']);
        $this->assertSame('image/png', $resolved['mime']);
        $this->assertFileExists($resolved['path']);
    }
}
