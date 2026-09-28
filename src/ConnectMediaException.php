<?php

declare(strict_types=1);

namespace ConnectMedia\Sms;

/** Thrown when the API returns a non-success code or the request fails. */
final class ConnectMediaException extends \RuntimeException
{
    public string $apiCode;
    public string $apiMessage;
    /** @var array<string, mixed> */
    public array $response;

    /** @param array<string, mixed> $response */
    public function __construct(string $apiCode, string $apiMessage, array $response = [])
    {
        parent::__construct("[$apiCode] $apiMessage");
        $this->apiCode    = $apiCode;
        $this->apiMessage = $apiMessage;
        $this->response   = $response;
    }
}
