<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\NamespaceCollector;
use DokuWikiTest;
use ReflectionClass;

/**
 * @group plugin_dw2pdf
 * @group plugins
 */
class NamespaceCollectorSortTest extends DokuWikiTest
{
    /** @var string[] */
    protected $pluginsEnabled = ['dw2pdf'];

    /**
     * Provide a list of page orderings that should remain stable after sorting.
     *
     * @see testPagenameSort
     * @return array
     */
    public function providerPageNameSort(): array
    {
        return [
            'start pages sorted' => [[
                'bar',
                'bar:start',
                'bar:alpha',
                'bar:bar',
            ]],

            'pages and subspaces mixed' => [[
                'alpha',
                'beta:foo',
                'gamma',
            ]],

            'full test' => [[
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
            ]],
        ];
    }

    /**
     * @dataProvider providerPageNameSort
     *
     * @param array $expected
     * @return void
     */
    public function testPagenameSort(array $expected): void
    {
        // Build a namespace collector instance without running the heavy constructor logic.
        $reflection = new ReflectionClass(NamespaceCollector::class);
        /** @var NamespaceCollector $collector */
        $collector = $reflection->newInstanceWithoutConstructor();

        $prepared = [];
        foreach ($expected as $line) {
            $prepared[] = ['id' => $line];
        }

        $input = $prepared;
        shuffle($input);

        usort($input, [$collector, 'cbPagenameSort']);

        $this->assertSame($prepared, $input);
    }
}

