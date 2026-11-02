<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tests\Support;

use PdfGate\Http\HttpRequest;
use PdfGate\Http\HttpResponse;
use PdfGate\Http\HttpTransportInterface;

/**
 * Records requests and replies with queued responses.
 */
final class FakeTransport implements HttpTransportInterface
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse> */
    private array $queue = [];

    public function queue(HttpResponse $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    /**
     * @param array<string,mixed> $body
     */
    public function queueJson(array $body, int $status = 200): self
    {
        return $this->queue(new HttpResponse(
            $status,
            (string) json_encode($body),
            ['content-type' => 'application/json']
        ));
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;

        $response = array_shift($this->queue);
        if ($response === null) {
            throw new \RuntimeException('No queued response for ' . $request->getMethod() . ' ' . $request->getUrl());
        }

        return $response;
    }
}
