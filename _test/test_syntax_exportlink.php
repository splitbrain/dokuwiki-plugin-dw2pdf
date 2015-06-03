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
        $parser_response = p_get_instructions('~~PDFNS>.|Foo~~');
        print_r($parser_response);
        $this->markTestIncomplete('Test must yet be implemented.');
    }
}

