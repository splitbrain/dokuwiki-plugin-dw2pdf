<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\BookCreatorLiveSelectionCollector;
use dokuwiki\plugin\dw2pdf\src\Cache;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\PdfExportService;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use TestUtils;

/**
 * Tests how the structural parts of an export are laid out on pages
 *
 * Cover page, table of contents, content pages and back page each claim their own pages, and
 * every page carries the header and footer of the template variant that applies to it. Which
 * combination produces which page is decided by the interplay of the template files, the ToC
 * setting and double-sided output, so the combinations are checked as a matrix.
 *
 * What ends up on which page is read from the finished mPDF instance rather than from the PDF it
 * wrote. Text in a PDF is encoded against the embedded font subsets and no longer says which
 * template it came from, while mPDF still holds the composition of the document in a handful of
 * plain arrays. The helpers at the bottom read those arrays and explain them where they use them.
 *
 * One trait of mPDF matters throughout: a table of contents can only be built once every page it
 * refers to exists, so mPDF appends it to the otherwise finished document and then moves that
 * block of pages to where the writer had marked it. It renumbers the arrays below along with the
 * move, so they describe the document as it is delivered, not as it was written.
 *
 * The fixture templates in _test/tpl are named after what they provide: the layout part of the
 * name lists their header and footer files, the suffix their cover and back page.
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class PageLayoutTest extends \DokuWikiTest
{
    protected $pluginsEnabled = ['dw2pdf'];

    /** @var string[] The pages exported by every test case, each filling exactly one PDF page */
    protected const PAGES = ['one', 'two'];

    /** @var string Prefix of the links the fixtures place in their content, to recognize pages by */
    protected const MARKER = 'http://dw2pdf.test/';

    /** @var string[] Absolute paths of the fixture templates while they are installed */
    protected static array $templates = [];

    /**
     * Provide the fixture templates and pages
     *
     * Both are the same for every test case, and neither the template directory nor the wiki
     * data directory is reset between the tests of a class.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // templates are always resolved relative to the plugin, so the fixtures have to live there
        foreach (glob(__DIR__ . '/tpl/*', GLOB_ONLYDIR) as $source) {
            $target = DOKU_PLUGIN . 'dw2pdf/tpl/' . basename($source);
            if (is_dir($target)) {
                throw new \RuntimeException("fixture template $target would overwrite a real template");
            }

            TestUtils::rcopy(DOKU_PLUGIN . 'dw2pdf/tpl', $source);
            self::$templates[] = $target;
        }

        foreach (self::PAGES as $page) {
            saveWikiText($page, file_get_contents(__DIR__ . '/pages/' . $page . '.txt'), 'dw2pdf layout test');
        }
    }

    /**
     * Remove the fixture templates from the plugin again
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        foreach (self::$templates as $target) {
            TestUtils::rdelete($target);
        }
        self::$templates = [];

        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
    }

    /**
     * Templates and ToC settings, with the content each page of their export should hold
     *
     * Each case is the fixture template to export with, the configuration to export under, and
     * one entry per expected page. An empty entry is a page left blank.
     *
     * @return array
     */
    public function providePaging(): array
    {
        return [
            'nothing' => [
                'dw2pdftest_bare', [],
                ['one', 'two'],
            ],
            'cover' => [
                'dw2pdftest_bare_cover', [],
                ['cover', 'one', 'two'],
            ],
            'back' => [
                'dw2pdftest_bare_back', [],
                ['one', 'two', 'back'],
            ],
            'cover and back' => [
                'dw2pdftest_bare_coverback', [],
                ['cover', 'one', 'two', 'back'],
            ],
            'toc' => [
                'dw2pdftest_bare', ['toc' => 1],
                ['toc', 'one', 'two'],
            ],
            'toc and cover' => [
                'dw2pdftest_bare_cover', ['toc' => 1],
                ['cover', 'toc', 'one', 'two'],
            ],
            'toc and back' => [
                'dw2pdftest_bare_back', ['toc' => 1],
                ['toc', 'one', 'two', 'back'],
            ],
            'toc, cover and back' => [
                'dw2pdftest_bare_coverback', ['toc' => 1],
                ['cover', 'toc', 'one', 'two', 'back'],
            ],

            // the ToC starts on an odd page and is padded to an even number of pages, so that the
            // content behind it starts on an odd page again - both can leave a page blank
            'double-sided nothing' => [
                'dw2pdftest_bare', ['doublesided' => 1],
                ['one', 'two'],
            ],
            'double-sided cover' => [
                'dw2pdftest_bare_cover', ['doublesided' => 1],
                ['cover', 'one', 'two'],
            ],
            'double-sided back' => [
                'dw2pdftest_bare_back', ['doublesided' => 1],
                ['one', 'two', 'back'],
            ],
            'double-sided cover and back' => [
                'dw2pdftest_bare_coverback', ['doublesided' => 1],
                ['cover', 'one', 'two', 'back'],
            ],
            'double-sided toc' => [
                'dw2pdftest_bare', ['toc' => 1, 'doublesided' => 1],
                ['toc', 'toc', 'one', 'two'],
            ],
            'double-sided toc and cover' => [
                'dw2pdftest_bare_cover', ['toc' => 1, 'doublesided' => 1],
                ['cover', '', 'toc', 'toc', 'one', 'two'],
            ],
            'double-sided toc and back' => [
                'dw2pdftest_bare_back', ['toc' => 1, 'doublesided' => 1],
                ['toc', 'toc', 'one', 'two', 'back'],
            ],
            'double-sided toc, cover and back' => [
                'dw2pdftest_bare_coverback', ['toc' => 1, 'doublesided' => 1],
                ['cover', '', 'toc', 'toc', 'one', 'two', 'back'],
            ],
        ];
    }

    /**
     * Cover page, ToC, content pages and back page each occupy the pages they are supposed to
     *
     * No page may be inserted beyond those, in particular no blank one apart from the padding a
     * double-sided document needs to keep the ToC and the content on odd pages.
     *
     * @dataProvider providePaging
     * @param string $template The fixture template to export with
     * @param array $conf Plugin configuration overrides
     * @param string[] $pages The expected content of each page
     */
    public function testPaging(string $template, array $conf, array $pages): void
    {
        $mpdf = $this->export($template, $conf);

        $this->assertSame($pages, $this->pageContents($mpdf));
    }

    /**
     * Templates and ToC settings, with the header and footer each page of their export should get
     *
     * Each case is the fixture template to export with, the configuration to export under, one
     * entry per expected page, and one entry per expected header and footer.
     *
     * A template may provide a single header for all pages, dedicated ones for odd and even
     * pages, and a dedicated one for the first page. A missing variant falls back to the
     * unsuffixed file, and a template without any header file leaves its pages without one. The
     * fixture footers repeat their header, so both are expected to come out the same.
     *
     * Odd and even variants only alternate in double-sided documents, single-sided documents use
     * the odd variant throughout. Which page the first-page variant lands on depends on whether
     * the export has a cover page, which is why the cover page and ToC are varied here as well.
     *
     * The placeholders show the context a header was built from: cover page, ToC and back page
     * belong to the export as a whole and use the first exported page, while content pages use
     * their own.
     *
     * @return array
     */
    public function provideHeaderFooter(): array
    {
        // a cover page and a ToC shift everything behind them, so the pages are named once here
        $contentOnly = ['one', 'two'];
        $withStructure = ['cover', 'toc', 'one', 'two', 'back'];
        $withStructureDouble = ['cover', '', 'toc', 'toc', 'one', 'two', 'back'];

        return [
            'no header and footer' => [
                'dw2pdftest_bare', [],
                $contentOnly,
                ['', ''],
            ],
            'no header and footer, cover and toc' => [
                'dw2pdftest_bare_coverback', ['toc' => 1],
                $withStructure,
                ['', '', '', '', ''],
            ],
            'single header and footer' => [
                'dw2pdftest_plain', [],
                $contentOnly,
                ['O any one', 'O any two'],
            ],
            'single header and footer, cover and toc' => [
                'dw2pdftest_plain_coverback', ['toc' => 1],
                $withStructure,
                ['O any one', 'O any one', 'O any one', 'O any two', 'O any one'],
            ],
            'odd and even header and footer' => [
                'dw2pdftest_oddeven', [],
                $contentOnly,
                ['O odd one', 'O odd two'],
            ],
            'odd and even header and footer, cover and toc' => [
                'dw2pdftest_oddeven_coverback', ['toc' => 1],
                $withStructure,
                ['O odd one', 'O odd one', 'O odd one', 'O odd two', 'O odd one'],
            ],
            'first page header and footer' => [
                'dw2pdftest_first', [],
                $contentOnly,
                ['O first one', 'O any two'],
            ],
            'first page header and footer, cover and toc' => [
                'dw2pdftest_first_coverback', ['toc' => 1],
                $withStructure,
                ['O first one', 'O any one', 'O any one', 'O any two', 'O any one'],
            ],

            'double-sided no header and footer' => [
                'dw2pdftest_bare', ['doublesided' => 1],
                $contentOnly,
                ['', ''],
            ],
            'double-sided no header and footer, cover and toc' => [
                'dw2pdftest_bare_coverback', ['toc' => 1, 'doublesided' => 1],
                $withStructureDouble,
                ['', '', '', '', '', '', ''],
            ],
            'double-sided single header and footer' => [
                'dw2pdftest_plain', ['doublesided' => 1],
                $contentOnly,
                ['O any one', 'E any two'],
            ],
            'double-sided single header and footer, cover and toc' => [
                'dw2pdftest_plain_coverback', ['toc' => 1, 'doublesided' => 1],
                $withStructureDouble,
                ['O any one', 'E any one', 'O any one', 'E any one', 'O any one', 'E any two', 'O any one'],
            ],
            'double-sided odd and even header and footer' => [
                'dw2pdftest_oddeven', ['doublesided' => 1],
                $contentOnly,
                ['O odd one', 'E even two'],
            ],
            'double-sided odd and even header and footer, cover and toc' => [
                'dw2pdftest_oddeven_coverback', ['toc' => 1, 'doublesided' => 1],
                $withStructureDouble,
                ['O odd one', 'E even one', 'O odd one', 'E even one', 'O odd one', 'E even two', 'O odd one'],
            ],
            'double-sided first page header and footer' => [
                'dw2pdftest_first', ['doublesided' => 1],
                $contentOnly,
                ['O first one', 'E any two'],
            ],
            'double-sided first page header and footer, cover and toc' => [
                'dw2pdftest_first_coverback', ['toc' => 1, 'doublesided' => 1],
                $withStructureDouble,
                ['O first one', 'E any one', 'O any one', 'E any one', 'O any one', 'E any two', 'O any one'],
            ],
        ];
    }

    /**
     * Every page carries the header and footer of the template variant that applies to it
     *
     * @dataProvider provideHeaderFooter
     * @param string $template The fixture template to export with
     * @param array $conf Plugin configuration overrides
     * @param string[] $pages The expected content of each page
     * @param string[] $blocks The expected header and footer of each page
     */
    public function testHeaderFooter(string $template, array $conf, array $pages, array $blocks): void
    {
        $mpdf = $this->export($template, $conf);

        $this->assertSame($pages, $this->pageContents($mpdf), 'page order');
        $this->assertSame($blocks, $this->pageBlocks($mpdf, 'header'), 'headers');
        $this->assertSame($blocks, $this->pageBlocks($mpdf, 'footer'), 'footers');
    }

    /**
     * Export the fixture pages and return the mPDF instance of the finished document
     *
     * The export service composes the document and then has mPDF output it. Both steps are taken
     * separately here, so that the instance in between can be picked up.
     *
     * @param string $template The template to export with
     * @param array $conf Plugin configuration overrides
     * @return Mpdf The instance the document was written with, after it has been closed
     */
    protected function export(string $template, array $conf): Mpdf
    {
        $config = new Config(array_merge($conf, [
            'template' => $template,
            'usecache' => 0,
            'liveselection' => json_encode(self::PAGES),
        ]));
        $collector = new BookCreatorLiveSelectionCollector($config);
        $cache = new Cache($config, $collector);

        $service = new PdfExportService($config, $collector, $cache, 'Contents');
        $writer = $this->callInaccessibleMethod($service, 'renderDocument', []);
        $mpdf = $this->getInaccessibleProperty($writer, 'mpdf');

        // composing the document leaves the ToC unfinished, mPDF only assembles it and moves it
        // into place while outputting. Nothing here needs the resulting PDF, so it is asked for
        // as a string and dropped rather than written anywhere.
        $mpdf->Output('', Destination::STRING_RETURN);

        return $mpdf;
    }

    /**
     * Determine what each page of the finished document holds
     *
     * Two of mPDF's arrays are read for this.
     *
     * $pgsIns records the ToC pages that were moved into place, as the first page of the moved
     * block mapped to the number of pages it spans. A two page ToC now sitting on pages 3 and 4
     * is therefore [3 => 2]. This is the only way to tell the second page of a padded ToC from a
     * blank page, as an empty ToC page holds nothing that could be recognized otherwise.
     *
     * $PageLinks holds the links of every page, keyed by page number. A single link is a
     * positional array whose index 4 is the target: the URL for a link leaving the document, or
     * an '@' followed by a page number for one pointing inside it. Every fixture this test
     * exports - cover page, back page and both wiki pages - contains a link to a MARKER URL of
     * its own, so such a target names the fixture that was rendered onto that page.
     *
     * A page that is neither part of the ToC nor holds a marker link was left blank.
     *
     * @param Mpdf $mpdf The instance of a finished document
     * @return string[] One entry per page: the marker of its content, 'toc', or '' when blank
     */
    protected function pageContents(Mpdf $mpdf): array
    {
        // pages are numbered from 1, and $mpdf->page is the number the document ended up with
        $contents = array_fill(1, $mpdf->page, '');

        foreach ($mpdf->pgsIns as $start => $length) {
            for ($page = $start; $page < $start + $length; $page++) {
                $contents[$page] = 'toc';
            }
        }

        foreach ($mpdf->PageLinks as $page => $links) {
            foreach ($links as $link) {
                // links pointing inside the document are of no interest here
                if (!str_starts_with((string)$link[4], self::MARKER)) continue;
                $contents[$page] = substr($link[4], strlen(self::MARKER));
            }
        }

        return array_values($contents);
    }

    /**
     * Determine the header or footer each page of the finished document was rendered with
     *
     * A page's header and footer are not written when its content is. mPDF stamps them in once
     * the document is output and until then only notes, per page, which block is to be used.
     * $saveHTMLHeader and $saveHTMLFooter hold those notes, keyed by page number and below that
     * by the side the block was picked for: 'O' for odd pages, 'E' for even ones. Only one side
     * is ever noted for a page, and a single-sided document only ever uses 'O'.
     *
     * The 'html' of such a note is the block as it will be rendered, with its placeholders
     * already replaced. The writer redefines the named blocks before every page break, so the
     * note holds the state they were in when that page began. Since the fixture templates name
     * themselves and carry the @ID@ placeholder, the returned strings show all three things that
     * can go wrong: the side, the template variant that applied, and the page context its
     * placeholders were resolved against.
     *
     * @param Mpdf $mpdf The instance of a finished document
     * @param string $type Either 'header' or 'footer'
     * @return string[] One entry per page, '' for pages without one
     */
    protected function pageBlocks(Mpdf $mpdf, string $type): array
    {
        $applied = $type === 'header' ? $mpdf->saveHTMLHeader : $mpdf->saveHTMLFooter;
        $blocks = array_fill(1, $mpdf->page, '');

        foreach ($applied as $page => $sides) {
            // to lay out the ToC of a double-sided document, mPDF works on a scratch page that it
            // drops again afterwards - only its note stays behind, past the end of the document
            if ($page > $mpdf->page) continue;

            foreach ($sides as $side => $block) {
                $html = trim($block['html'] ?? '');
                if ($html !== '') $blocks[$page] = $side . ' ' . $html;
            }
        }

        return array_values($blocks);
    }
}
