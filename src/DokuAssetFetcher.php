<?php

namespace dokuwiki\plugin\dw2pdf\src;

use Mpdf\AssetFetcher;

/**
 * Wrapper for AssetFetcher which resolves DokuWiki media paths
 */
class DokuAssetFetcher extends AssetFetcher
{
    public function fetchDataFromPath($path, $originalSrc = null)
    {
        $resolved = (new MediaLinkResolver())->resolve($path);
        if ($resolved) $originalSrc = $resolved['path'];
        return parent::fetchDataFromPath($path, $originalSrc);
    }
}
