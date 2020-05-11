<?php

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class dw2pdf_action_pagenamesort_test extends DokuWikiTest
{

    protected $pluginsEnabled = array('dw2pdf');


    public function testDirectPagenameSort() {
        $action = new action_plugin_dw2pdf();

        $this->assertLessThan(0, $action->_pagenamesort(['id'=>'bar'], ['id'=>'bar:start']));
        $this->assertGreaterThan(0, $action->_pagenamesort(['id'=>'bar:bar'], ['id'=>'bar:start']));
    }

    /**
     * @see testPageNameSort
     * @return array
     */
    public function providerPageNameSort()
    {
        return [
            [
                'start pages sorted',
                [
                    'bar',
                    'bar:start',
                    'bar:alpha',
                    'bar:bar',
                ]
            ],
            [
                'pages and subspaces mixed',
                [
                    'alpha',
                    'beta:foo',
                    'gamma'
                ]
            ],
            [
                'full test',
                [
                    'start',
                    '01_page',
                    '10_page',
                    'bar',
                    'bar:start',
                    'bar:1_page',
                    'bar:2_page',
                    'bar:10_page',
                    'bar:22_page',
                    'bar:aa_page',
                    'bar:aa_page:detail1',
                    'bar:zz_page',
                    'foo',
                    'foo:start',
                    'foo:01_page',
                    'foo:10_page',
                    'foo:foo',
                    'foo:zz_page',
                    'ns',
                    'ns:01_page',
                    'ns:10_page',
                    'ns:ns',
                    'ns:zz_page',
                    'zz_page',
                ],
            ],
        ];
    }

    /**
     * @dataProvider providerPageNameSort
     * @param string $comment
     * @param array $expected
     */
    public function testPagenameSort($comment, $expected)
    {
        // prepare the array as expected in the sort function
        $prepared = [];
        foreach($expected as $line) {
            $prepared[] = ['id' => $line];
        }

        // the input is random
        $input = $prepared;
        shuffle($input);

        // run sort
        $action = new action_plugin_dw2pdf();
        usort($input, [$action, '_pagenamesort']);

        $this->assertSame($prepared, $input);
    }
}

