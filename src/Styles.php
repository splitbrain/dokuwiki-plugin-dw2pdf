<?php

namespace dokuwiki\plugin\dw2pdf\src;

use dokuwiki\StyleUtils;

class Styles
{
    protected Config $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get the full CSS to include in the PDF
     *
     * Gathers all relevant CSS files, applies style replacements and parses LESS.
     *
     * @return string
     */
    public function getCSS(): string
    {
        //reuse the CSS dispatcher functions without triggering the main function
        if (!defined('SIMPLE_TEST')) {
            define('SIMPLE_TEST', 1);
        }
        require_once(DOKU_INC . 'lib/exe/css.php');

        // prepare CSS files
        $files = $this->getStyleFiles();
        $css = '';
        foreach ($files as $file => $location) {
            $display = str_replace(fullpath(DOKU_INC), '', fullpath($file));
            $css .= "\n/* XXXXXXXXX $display XXXXXXXXX */\n";
            $css .= css_loadfile($file, $location);
        }

        // apply style replacements
        $styleUtils = new StyleUtils();
        $styleini = $styleUtils->cssStyleini();
        $css = css_applystyle($css, $styleini['replacements']);

        // parse less
        return css_parseless($css);
    }


    /**
     * Returns the list of style files to include in the PDF
     *
     * The array keys are the file paths on disk, the values are the
     * paths as used inside the Styles (for resolving relative links).
     *
     * @return array<string,string>
     */
    protected function getStyleFiles(): array
    {
        $tpl = $this->config->getTemplateName();

        return array_merge(
            [
                DOKU_INC . 'lib/styles/screen.css' => DOKU_BASE . 'lib/styles/',
                DOKU_INC . 'lib/styles/print.css' => DOKU_BASE . 'lib/styles/',
            ],
            $this->getExtensionStyles(),
            [
                DOKU_PLUGIN . 'dw2pdf/conf/style.css' => DOKU_BASE . 'lib/plugins/dw2pdf/conf/',
                DOKU_PLUGIN . 'dw2pdf/tpl/' . $tpl . '/style.css' => DOKU_BASE . 'lib/plugins/dw2pdf/tpl/' . $tpl . '/',
                DOKU_PLUGIN . 'dw2pdf/conf/style.local.css' => DOKU_BASE . 'lib/plugins/dw2pdf/conf/',
            ]
        );
    }

    /**
     * Returns a list of possible Plugin and Template PDF Styles
     *
     * Checks for a pdf.css, falls back to print.css. For configured usestyles plugins
     * the screen.css and style.css are also included.
     */
    protected function getExtensionStyles()
    {
        $list = [];
        $plugins = plugin_list();

        $usestyle = $this->config->getStyledExtensions();
        foreach ($plugins as $p) {
            if (in_array($p, $usestyle)) {
                $list[DOKU_PLUGIN . "$p/screen.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/screen.less"] = DOKU_BASE . "lib/plugins/$p/";

                $list[DOKU_PLUGIN . "$p/style.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/style.less"] = DOKU_BASE . "lib/plugins/$p/";
            }

            $list[DOKU_PLUGIN . "$p/all.css"] = DOKU_BASE . "lib/plugins/$p/";
            $list[DOKU_PLUGIN . "$p/all.less"] = DOKU_BASE . "lib/plugins/$p/";

            if (file_exists(DOKU_PLUGIN . "$p/pdf.css") || file_exists(DOKU_PLUGIN . "$p/pdf.less")) {
                $list[DOKU_PLUGIN . "$p/pdf.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/pdf.less"] = DOKU_BASE . "lib/plugins/$p/";
            } else {
                $list[DOKU_PLUGIN . "$p/print.css"] = DOKU_BASE . "lib/plugins/$p/";
                $list[DOKU_PLUGIN . "$p/print.less"] = DOKU_BASE . "lib/plugins/$p/";
            }
        }

        // template support
        foreach (
            [
                'pdf.css',
                'pdf.less',
                'css/pdf.css',
                'css/pdf.less',
                'styles/pdf.css',
                'styles/pdf.less'
            ] as $file
        ) {
            if (file_exists(tpl_incdir() . $file)) {
                $list[tpl_incdir() . $file] = tpl_basedir() . $file;
            }
        }

        return $list;
    }
}
