<?php

namespace dokuwiki\plugin\dw2pdf\src;

/**
 * Translates Dokuwiki-specific media URLs into local cached files.
 *
 * This consolidates the logic previously handled inside the custom ImageProcessor.
 */
class MediaLinkResolver
{

    /**
     * Resolve a Dokuwiki media URL or local path to a cached file path.
     *
     * The given file might be a Dokuwiki media reference (fetch.php call or /media/ URL) or an URL
     * pointing to a static resource. Instead of performing an HTTP request, this method is used to
     * resolve the file to a local cached copy whenever possible. It only handles images.
     *
     * When null is returned, the caller will fall back to a standard HTTP request.
     *
     * @param string $file Original media reference or URL.
     * @return array|null ['path' => string, 'mime' => string] when resolution succeeds, null otherwise.
     */
    public function resolve(string $file): ?array
    {
        $mediaID = $this->extractMediaID($file);
        if ($mediaID !== null) {
            [$w, $h, $rev] = $this->extractMediaParams($file);
            [$ext, $mime] = mimetype($mediaID);
            if(!$ext) return null;
            $localFile = $this->localMediaFile($mediaID, $ext, $rev);
            if (!$localFile) return null;
            if(strpos($mime, 'image/') === 0) {
                $localFile = $this->resizedMedia($localFile, $ext, $w, $h);
            }
        } else {
            [, $mime] = mimetype($file);
            if (strpos($mime, 'image/') !== 0) return null;
            $localFile = $this->extractLocalImage($file);
        }

        if (!$localFile) return null;
        return ['path' => $localFile, 'mime' => $mime];

    }

    /**
     * Check if the given file URL corresponds to a Dokuwiki media ID and extract it.
     *
     * Handles rewritten media URLs  (/media/*) and fetch.php calls by building a regex
     * from the result of calling ml() for a fake media ID.
     *
     * Note that the returned media ID could still be an external URL!
     *
     * @param string $file
     * @return string|null The extracted media ID, or null if not found.
     */
    protected function extractMediaID(string $file): ?string
    {
        // build regex to parse URL back to media info (matches fetch.php calls)
        $fetchRegex = preg_quote(ml('xxx123yyy', '', true, '&', true), '/');
        $fetchRegex = str_replace('xxx123yyy', '([^&\?]*)', $fetchRegex);

        // extract the real media from a fetch.php URI and determine mime
        if (
            preg_match("/^$fetchRegex/", $file, $matches) ||
            preg_match('/[&?]media=([^&?]*)/', $file, $matches)
        ) {
            return rawurldecode($matches[1]);
        }

        return null;
    }

    /**
     * Extract media parameters (width, height, revision) from the given file URL.
     *
     * When a parameter is not found, its value will be 0.
     *
     * @param string $file Source string (fetch call)
     * @return array{int,int,int} Array containing width, height, and revision.
     */
    protected function extractMediaParams(string $file): array
    {
        $width = $this->extractInt($file, 'w');
        $height = $this->extractInt($file, 'h');
        $rev = $this->extractInt($file, 'rev');
        return [$width, $height, $rev];
    }

    /**
     * Returns a local, absolute path for the given media ID and revision
     *
     * This method will download external media files to the local cache if needed. ACLs are
     * checked here as well.
     *
     * Returns null when the media file is not accessible.
     *
     * @param string $mediaID A media ID or external URL.
     * @param string $ext File extension (used for external media caching).
     * @param int $rev Revision number (0 for latest).
     * @return string|null Absolute path to the local media file, or null when not accessible.
     */
    protected function localMediaFile(string $mediaID, string $ext, int $rev): ?string
    {
        global $conf;

        if (media_isexternal($mediaID)) {
            $local = media_get_from_URL($mediaID, $ext, $conf['cachetime']);
            if (!$local) return null;
        } else {
            $mediaID = cleanID($mediaID);
            // check permissions (namespace only)
            if (auth_quickaclcheck(getNS($mediaID) . ':X') < AUTH_READ) {
                return null;
            }
            $local = mediaFN($mediaID, $rev ?: '');
        }
        if (!file_exists($local)) return null;

        return $local;
    }

    /**
     * Resize or crop the given media file as needed.
     *
     * @param string $mediaFile Absolute path to the local media file.
     * @param string $ext File extension.
     * @param int $width Desired width
     * @param int $height Desired height
     * @return string|null Absolute path to the resized/cropped media file, or null on failure.
     */
    protected function resizedMedia($mediaFile, $ext, $width, $height)
    {
        if ($width && $height) {
            $mediaFile = media_crop_image($mediaFile, $ext, $width, $height);
        } elseif ($width || $height) {
            $mediaFile = media_resize_image($mediaFile, $ext, $width, $height);
        }
        if (!file_exists($mediaFile)) return null;
        return $mediaFile;
    }

    /**
     * Extract an integer parameter from the given subject URL.
     *
     * @param string $subject Source string, usually the media URL.
     * @param string $param Name of the parameter to extract.
     * @return int
     */
    protected function extractInt(string $subject, string $param): int
    {
        $pattern = '/[?&]' . $param . '=(\d+)/';
        if (preg_match($pattern, $subject, $match)) {
            return (int)$match[1];
        }

        return 0;
    }

    /**
     * Attempt to extract a local file path from the given URL.
     *
     * This only works for static files that are directly accessible on disk. Or our
     * custom dw2pdf:// scheme for local files passed from plugins.
     *
     * @param string $file Source URL.
     * @return string|null Absolute path to the local file, or null when not accessible.
     */
    protected function extractLocalImage($file)
    {
        $local = null;
        if (substr($file, 0, 9) === 'dw2pdf://') {
            // support local files passed from plugins
            $local = substr($file, 9);
        } elseif (!preg_match('/(\.php|\?)/', $file)) {
            // directly access local files instead of using HTTP (skipping dynamic content)
            $base = preg_quote(DOKU_URL, '/');
            $local = preg_replace("/^$base/i", DOKU_INC, $file, 1);
        }

        if(!file_exists($local)) return null;
        if(!is_readable($local)) return null;

        return $local;
    }
}
