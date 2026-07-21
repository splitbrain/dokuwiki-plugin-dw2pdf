<?php

namespace dokuwiki\plugin\dw2pdf\src;

use dokuwiki\HTTP\DokuHTTPClient;
use Mpdf\Http\ClientInterface;
use Mpdf\PsrHttpMessageShim\Response;
use Mpdf\PsrHttpMessageShim\Stream;
use Mpdf\PsrLogAwareTrait\PsrLogAwareTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * mPDF HTTP client adapter that routes requests through Dokuwiki's HTTP stack.
 *
 * Basically wraps a simple, naive PSR-7 implementation around DokuHTTPClient.
 */
class HttpClient implements ClientInterface, LoggerAwareInterface
{
    use PsrLogAwareTrait;

    /**
     * Send the HTTP request using Dokuwiki's HTTP client, falling back to media resolution when possible.
     *
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request)
    {
        $uri = $request->getUri();

        $url = (string)$uri;

        // standard Dokuwiki HTTP client for any remote content
        $client = new DokuHTTPClient();
        $client->headers = $this->buildHeaders($request);
        $client->referer = $request->getHeaderLine('Referer');
        if ($agent = $request->getHeaderLine('User-Agent')) {
            $client->agent = $agent;
        }

        $body = (string)$request->getBody();
        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }

        $method = strtoupper($request->getMethod());
        $client->sendRequest($url, $body, $method);

        $response = (new Response())->withStatus($client->status ?: 500);

        foreach ((array)$client->resp_headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $single) {
                    $response = $response->withHeader($name, $single);
                }
            } else {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response->withBody(Stream::create($client->resp_body));
    }

    /**
     * Convert PSR-7 headers to the associative format expected by DokuHTTPClient.
     *
     * @param RequestInterface $request Original request from mPDF.
     * @return array<string,string>
     */
    private function buildHeaders(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}
