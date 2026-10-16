<?php

namespace dokuwiki\plugin\dw2pdf\src;

use Mpdf\AssetFetcher;

/**
 * Wrapper for AssetFetcher which resolves DokuWiki media paths
 */
class DokuAssetFetcher extends AssetFetcher
{
    /**
     * Load the given asset, preferring a local copy of Dokuwiki media over an HTTP request
     *
     * Both arguments are overwritten with the resolved file, because mpdf picks either one
     * depending on its basepathIsLocal flag. Leaving a URL in one of them would make mpdf
     * fetch the asset over HTTP again.
     *
     * @param string $path Media reference or URL to load
     * @param string|null $originalSrc The unmodified source as given in the HTML
     * @return string The asset's binary data, empty when it could not be loaded
     */
    public function fetchDataFromPath($path, $originalSrc = null)
    {
        $resolved = (new MediaLinkResolver())->resolve($path);
        if ($resolved) $path = $originalSrc = $resolved['path'];
        return parent::fetchDataFromPath($path, $originalSrc);
    }
}
