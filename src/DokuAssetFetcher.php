<?php

namespace dokuwiki\plugin\dw2pdf\src;

use dokuwiki\Logger;
use Mpdf\AssetFetcher;
use Mpdf\File\LocalContentLoaderInterface;
use Mpdf\Http\ClientInterface;
use Mpdf\Mpdf;
use Psr\Log\LoggerInterface;

/**
 * Wrapper for AssetFetcher which resolves DokuWiki media paths
 */
class DokuAssetFetcher extends AssetFetcher
{
    /** @var MediaLinkResolver Translates media references into local files */
    protected MediaLinkResolver $resolver;

    /**
     * @param Mpdf $mpdf The document the assets are loaded for
     * @param LocalContentLoaderInterface $contentLoader Reads files from disk
     * @param ClientInterface $http Client for remote requests
     * @param LoggerInterface $logger Where mpdf reports asset problems
     * @param Config $config The configuration of the current export
     */
    public function __construct(
        Mpdf $mpdf,
        LocalContentLoaderInterface $contentLoader,
        ClientInterface $http,
        LoggerInterface $logger,
        Config $config
    ) {
        parent::__construct($mpdf, $contentLoader, $http, $logger);
        $this->resolver = new MediaLinkResolver($config);
    }

    /**
     * Load the given asset, preferring a local copy of Dokuwiki media over an HTTP request
     *
     * Both arguments are overwritten with the resolved file, because mpdf picks either one
     * depending on its basepathIsLocal flag. Leaving a URL in one of them would make mpdf
     * fetch the asset over HTTP again.
     *
     * Media this wiki serves itself is never requested back over HTTP. Such a request is
     * anonymous and cannot reach media the exporting user may read.
     *
     * @param string $path Media reference or URL to load
     * @param string|null $originalSrc The unmodified source as given in the HTML
     * @return string The asset's binary data, empty when it could not be loaded
     */
    public function fetchDataFromPath($path, $originalSrc = null)
    {
        $resolved = $this->resolver->resolve($path);

        if (!$resolved) {
            if ($this->resolver->isMediaUrl($path)) {
                Logger::error('Media not available for PDF export', $path);
                return '';
            }
            return parent::fetchDataFromPath($path, $originalSrc);
        }

        $path = $originalSrc = $resolved['path'];
        $data = parent::fetchDataFromPath($path, $originalSrc);
        if ($data === '') {
            Logger::error('Resolved media could not be read for PDF export', $path);
        }

        return $data;
    }
}
