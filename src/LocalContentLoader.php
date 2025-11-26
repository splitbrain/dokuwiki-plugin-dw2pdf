<?php

namespace dokuwiki\plugin\dw2pdf\src;

use Mpdf\File\LocalContentLoaderInterface;

/**
 * Local content loader that understands Dokuwiki media URLs.
 */
class LocalContentLoader implements LocalContentLoaderInterface
{
    /**
     * Load a media asset from disk, translating Dokuwiki-specific paths first.
     *
     * @param string $path Original path provided by mPDF.
     * @return string|null File contents or null when the file is unreadable.
     */
    public function load($path)
    {
        // try to translate URLs and fetch.php calls into local cache files
        $resolved = (new MediaLinkResolver())->resolve($path);
        ;
        if ($resolved) {
            $path = $resolved['path'];
        }

        if (!is_readable($path)) {
            return null;
        }

        return file_get_contents($path);
    }
}
