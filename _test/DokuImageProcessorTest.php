<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\DokuImageProcessorDecorator;
use DokuWikiTest;

/**
 * General tests for the imagemap plugin
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class DokuImageProcessorTest extends DokuWikiTest
{

    /**
     * @return array the Testdata
     */
    public function provideGetImageTestdata() {
        global $conf;

        return [
            [
                DOKU_URL . 'lib/exe/fetch.php?tok=b0b7a3&media=http%3A%2F%2Fphp.net%2Fimages%2Fphp.gif',
                DOKU_REL . 'lib/exe/fetch.php?tok=b0b7a3&media=http%3A%2F%2Fphp.net%2Fimages%2Fphp.gif',
                'http://php.net/images/php.gif',
                'http://php.net/images/php.gif',
                'external image',
            ],
            [
                DOKU_URL . 'lib/images/throbber.gif',
                DOKU_REL . 'lib/images/throbber.gif',
                DOKU_INC . 'lib/images/throbber.gif',
                DOKU_INC . 'lib/images/throbber.gif',
                'fixed standard image',
            ],
            [
                DOKU_URL . 'lib/exe/fetch.php?media=wiki:dokuwiki-128.png',
                DOKU_REL . 'lib/exe/fetch.php?media=wiki:dokuwiki-128.png',
                $conf['mediadir'] . '/wiki/dokuwiki-128.png',
                $conf['mediadir'] . '/wiki/dokuwiki-128.png',
                'Internal image',
            ],
        ];
    }

    /**
     * @dataProvider provideGetImageTestdata
     *
     * @param $input_file
     * @param $input_orig_srcpath
     * @param $expected_file
     * @param $expected_orig_srcpath
     * @param $msg
     */
    public function testGetImage($input_file, $input_orig_srcpath, $expected_file, $expected_orig_srcpath, $msg)
    {

        list($actual_file, $actual_orig_srcpath) = DokuImageProcessorDecorator::adjustGetImageLinks($input_file,
            $input_orig_srcpath);

        $this->assertEquals($expected_file, $actual_file,  '$file ' . $msg);
        $this->assertEquals($expected_orig_srcpath, $actual_orig_srcpath,  '$orig_srcpath ' . $msg);
    }

}
