<?php

namespace Knivey\OpenAi\Exception;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
