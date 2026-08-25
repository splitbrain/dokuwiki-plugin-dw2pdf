<?php

namespace dokuwiki\plugin\dw2pdf\test;

use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\HttpClient;
use DokuWikiTest;
use Mpdf\PsrHttpMessageShim\Request;

/**
 * Tests for the HTTP client mpdf loads remote assets through.
 *
 * @group plugin_dw2pdf
 * @group plugins
 */
class HttpClientTest extends DokuWikiTest
{
    /**
     * No remote host may be contacted when downloading is switched off.
     */
    public function testSendRequestRefusesWhenDownloadingIsDisabled(): void
    {
        $this->expectLogMessage('Remote asset not downloaded for PDF export');

        $request = new Request('GET', 'https://example.org/does-not-matter.png');
        $response = (new HttpClient(new Config(['fetchsize' => 0])))->sendRequest($request);

        $this->assertSame(403, $response->getStatusCode());
    }
}
