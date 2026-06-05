<?php

namespace Knivey\OpenAi\Tests\Exception;

use Knivey\OpenAi\Exception\ApiException;
use Knivey\OpenAi\Exception\AuthenticationException;
use Knivey\OpenAi\Exception\RateLimitException;
use PHPUnit\Framework\TestCase;

class ApiExceptionTest extends TestCase
{
    public function testApiExceptionHoldsStatusCodeAndBody(): void
    {
        $e = new ApiException('error', 400, '{"error":"bad"}');
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('{"error":"bad"}', $e->getResponseBody());
        $this->assertSame('error', $e->getMessage());
    }

    public function testRateLimitExceptionIsApiException(): void
    {
        $e = new RateLimitException('rate limited', 429, '{}');
        $this->assertInstanceOf(ApiException::class, $e);
        $this->assertSame(429, $e->getStatusCode());
    }

    public function testAuthenticationExceptionIsApiException(): void
    {
        $e = new AuthenticationException('unauthorized', 401, '{}');
        $this->assertInstanceOf(ApiException::class, $e);
        $this->assertSame(401, $e->getStatusCode());
    }
}
