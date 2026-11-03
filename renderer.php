<?php

// phpcs:disable: PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable: PSR2.Methods.MethodDeclaration.Underscore
use dokuwiki\plugin\dw2pdf\src\HeadingResolver;

/**
 * DokuWiki Plugin dw2pdf (Renderer Component)
 * Render xhtml suitable as input for mpdf library
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class renderer_plugin_dw2pdf extends Doku_Renderer_xhtml
{
    /**
     * Cached state of the optional XBR renderer.
     *
     * @var bool|null
     */
    private $xbrEnabled;

    /**
     * Check whether the XBR renderer is installed and enabled.
     *
     * @return bool
     */
    private function isXbrEnabled()
    {
        if ($this->xbrEnabled === null) {
            $this->xbrEnabled = plugin_load('renderer', 'xbr') !== null;
        }

        return $this->xbrEnabled;
    }

    /**
     * Render ordinary text.
     *
     * Preserve source line breaks when the XBR renderer is enabled,
     * matching DokuWiki's XHTML output.
     *
     * @param string $text
     * @return void
     */
    public function cdata($text)
    {
        if (!$this->isXbrEnabled()) {
            parent::cdata($text);
            return;
        }

        $parts = preg_split('/\R/u', $text);

        if ($parts === false) {
            parent::cdata($text);
            return;
        }

        $last = count($parts) - 1;

        foreach ($parts as $index => $part) {
            parent::cdata($part);

            if ($index !== $last) {
                parent::linebreak();
            }
        }
    }

    /**
     * Emit an anchor the writer can rewrite intra-PDF links to
     *
     * @inheritdoc
     */
    public function document_start()
    {
        global $ID;

        parent::document_start();

        //anchor for rewritten links to included pages
        $check = false;
        $pid = sectionID($ID, $check);

        $this->doc .= "<a name=\"{$pid}__\">";
        $this->doc .= "</a>";
    }

    /**
     * Make available as XHTML replacement renderer
     *
     * @param $format
     * @return bool
     */
    public function canRender($format)
    {
        if ($format == 'xhtml') {
            return true;
        }
        return false;
    }

    /**
     * Simplified header printing
     *
     * Numbering and PDF bookmark are not known while a single page is rendered, so a marker
     * is emitted instead. It is resolved by the writer once the page's position within the
     * export is known.
     *
     * @param string $text
     * @param int $level from 1 (highest) to 6 (lowest)
     * @param int $pos
     * @see HeadingResolver
     */
    public function header($text, $level, $pos, $returnonly = false)
    {
        //skip empty headlines
        if (!$text) {
            return;
        }
        global $ID;

        $hid = $this->_headerToLink($text, true);

        //only add items within global configured levels (doesn't check the pdf toc settings)
        $this->toc_additem($hid, $text, $level);

        $check = false;
        $pid = sectionID($ID, $check);
        $hid = $pid . '__' . $hid;

        // print header
        $this->doc .= DOKU_LF . "<h$level>";
        $this->doc .= HeadingResolver::marker($text, $level);
        $this->doc .= "<a name=\"$hid\">";
        $this->doc .= $this->_xmlEntities($text);
        $this->doc .= "</a>";
        $this->doc .= "</h$level>" . DOKU_LF;
    }

    /**
     * Render a page local link
     *
     * // modified copy of parent function
     *
     * @param string $hash hash link identifier
     * @param string $name name for the link
     * @param bool $returnonly
     * @return string|void
     *
     * @see Doku_Renderer_xhtml::locallink
     */
    public function locallink($hash, $name = null, $returnonly = false)
    {
        global $ID;
        $name = $this->_getLinkTitle($name, $hash, $isImage);
        $hash = $this->_headerToLink($hash);
        $title = $ID . ' ↵';

        $check = false;
        $pid = sectionID($ID, $check);

        $this->doc .= '<a href="#' . $pid . '__' . $hash . '" title="' . $title . '" class="wikilink1">';
        $this->doc .= $name;
        $this->doc .= '</a>';
    }

    /**
     * Wrap centered media in a div to center it
     *
     * @param string $src media ID
     * @param string $title descriptive text
     * @param string $align left|center|right
     * @param int $width width of media in pixel
     * @param int $height height of media in pixel
     * @param string $cache cache|recache|nocache
     * @param bool $render should the media be embedded inline or just linked
     * @return string
     */
    public function _media(
        $src,
        $title = null,
        $align = null,
        $width = null,
        $height = null,
        $cache = null,
        $render = true
    ) {

        $out = '';
        if ($align == 'center') {
            $out .= '<div align="center" style="text-align: center">';
        }

        $out .= parent::_media($src, $title, $align, $width, $height, $cache, $render);

        if ($align == 'center') {
            $out .= '</div>';
        }

        return $out;
    }

    /**
     * hover info makes no sense in PDFs, so drop acronyms
     *
     * @param string $acronym
     */
    public function acronym($acronym)
    {
        $this->doc .= $this->_xmlEntities($acronym);
    }

    /**
     * reformat links if needed
     *
     * Because the output of this renderer will be cached, but might be part of a larger PDF
     * including multiple pages, the links are not rewritten here.
     * Instead they will be rewritten in the created HTML after rendering but before feeding to mPDF.
     *
     * @param array $link
     * @return string
     */
    public function _formatLink($link)
    {
        // mark internal wiki links for later processing by the writer
        if (
            !empty($link['more']) &&
            str_contains($link['more'], 'data-wiki-id=')
        ) {
            [, $hash] = sexplode('#', $link['url'], 2, '');
            $target = $link['title'] ?? ''; // for internal links, 'title' holds the target page id
            if ($target !== '') {
                $attrs = ['data-dw2pdf-target="' . hsc($target) . '"'];
                if ($hash !== '') {
                    $attrs[] = 'data-dw2pdf-hash="' . hsc($hash) . '"';
                }
                $link['more'] = trim($link['more'] . ' ' . implode(' ', $attrs));
            }
        }

        // prefix interwiki links with interwiki icon
        if ($link['name'][0] != '<' && preg_match('/\binterwiki iw_(.\w+)\b/', $link['class'], $m)) {
            if (file_exists(DOKU_INC . 'lib/images/interwiki/' . $m[1] . '.png')) {
                $img = DOKU_BASE . 'lib/images/interwiki/' . $m[1] . '.png';
            } elseif (file_exists(DOKU_INC . 'lib/images/interwiki/' . $m[1] . '.gif')) {
                $img = DOKU_BASE . 'lib/images/interwiki/' . $m[1] . '.gif';
            } else {
                $img = DOKU_BASE . 'lib/images/interwiki.png';
            }

            $link['name'] = sprintf(
                '<img src="%s" width="16" height="16" style="vertical-align: middle" class="%s" />%s',
                $img,
                $link['class'],
                $link['name']
            );
        }
        return parent::_formatLink($link);
    }

    /**
     * no obfuscation for email addresses
     *
     * @param string $address
     * @param bool $returnonly
     * @return string|void
     */
    public function emaillink($address, $name = null, $returnonly = false)
    {
        global $conf;
        $old = $conf['mailguard'];
        $conf['mailguard'] = 'none';
        parent::emaillink($address, $name, $returnonly);
        $conf['mailguard'] = $old;
    }
}
