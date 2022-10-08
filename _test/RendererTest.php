<?php

namespace dokuwiki\plugin\dw2pdf\test;

use DokuWikiTest;

/**
 * Class dw2pdf_renderer_dw2pdf_test
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class RendererTest extends DokuWikiTest {

    public function test() {
        $Renderer = new \renderer_plugin_dw2pdf();

        $levels = [
            1,2,2,2,3,4,5,6,5,4,3,2,1, // index:0-12
            3,4,3,1,                   // 13-16
            2,3,4,2,3,4,1,             // 17-23
            3,4,3,2,1,                 // 24-28
            3,4,2,1,                   // 29-32
            3,5,6,5,6,4,6,3,1,         // 33-41
            3,6,4,5,6,4,3,6,2,1,       // 42-51
            2,3,2,3,3                  // 52-56
        ];
        $expectedbookmarklevels = [
            0,1,1,1,2,3,4,5,4,3,2,1,0,
            1,2,1,0,
            1,2,3,1,2,3,0,
            1,2,1,1,0,
            1,2,1,0,
            1,2,3,2,3,2,3,2,0,
            1,2,2,3,4,2,2,3,1,0,
            1,2,1,2,2
        ];
        foreach ($levels as $i => $level) {
            $actualbookmarklevel = $this->callInaccessibleMethod($Renderer, 'calculateBookmarklevel', [$level]);
            $this->assertEquals($expectedbookmarklevels[$i], $actualbookmarklevel, "index:$i, lvl:$level");
        }
    }
}
