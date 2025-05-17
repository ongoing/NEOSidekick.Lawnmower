<?php

namespace NEOSidekick\ContentRepositoryWebhooks;

use GuzzleHttp\Psr7\Request;

final class WebhookRequest extends Request
{
    protected $url;

    protected $payload;

    public function __construct(string $url, array $payload)
    {
        parent::__construct('POST', $url, [
            'Content-Type' => 'application/json'
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
