<?php

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class dw2pdf_action_pagenamesort_test extends DokuWikiTest
{

    protected $pluginsEnabled = array('dw2pdf');

    function test_sort()
    {

        $expected = [
            ['id' => 'start'],
            ['id' => '01_page'],
            ['id' => '10_page'],
            ['id' => 'zz_page'],
            ['id' => 'bar:start'],
            ['id' => 'bar:01_page'],
            ['id' => 'bar:10_page'],
            ['id' => 'bar:zz_page'],
            ['id' => 'foo:start'],
            ['id' => 'foo:01_page'],
            ['id' => 'foo:10_page'],
            ['id' => 'foo:zz_page'],
        ];

        // the input is random
        $input = $expected;
        shuffle($input);

        // run sort
        $action = new action_plugin_dw2pdf();
        usort($input, [$action, '_pagenamesort']);

        $this->assertSame($expected, $input);
    }
}

