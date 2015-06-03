<?php

/**
 * General tests for the imagemap plugin
 *
 * @group plugin_dw2pdf
 * @group plugins
*/
class dw2pdf_syntax_exportlink_test extends DokuWikiTest {

    public function setUp() {
        parent::setUp();
    }

    protected $pluginsEnabled = array('dw2pdf');

    function test_parser () {
        global $ID;
        $ID = 'foo:bar:start';
        $parser_response = p_get_instructions('~~PDFNS>.:|Foo~~');
        $expected_response = array(
            'plugin',
            array(
                'dw2pdf_exportlink',
                array(
                    'link' => '?do=export_pdfns&pdfns_ns=foo:bar&pdfns_title=Foo',
                    'title' => 'Export namespace "foo:bar:" to file Foo.pdf',
                    5,
                    1
                ),
                5,
                '~~PDFNS>.:|Foo~~'
            ),
            1
        );
        $this->assertEquals($expected_response,$parser_response[2]);
    }
}

