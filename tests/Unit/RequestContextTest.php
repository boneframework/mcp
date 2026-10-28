<?php

declare(strict_types=1);

namespace Tests\Bone\MCP;

use Bone\MCP\RequestContext;
use Codeception\Test\Unit;
use Laminas\Diactoros\ServerRequest;

class RequestContextTest extends Unit
{
    public function testWithoutRequest(): void
    {
        $context = new RequestContext();

        self::assertNull($context->getRequest());
        self::assertSame('fallback', $context->getAttribute('x', 'fallback'));
        self::assertNull($context->getClientId());
    }

    public function testWithRequest(): void
    {
        $context = new RequestContext();
        $request = (new ServerRequest())->withAttribute('oauth_client_id', 'client-1')->withAttribute('x', 'y');
        $context->setRequest($request);

        self::assertSame($request, $context->getRequest());
        self::assertSame('y', $context->getAttribute('x'));
        self::assertSame('client-1', $context->getClientId());
    }

    public function testEmptyOrNonStringClientIdIsNull(): void
    {
        $context = new RequestContext();

        $context->setRequest((new ServerRequest())->withAttribute('oauth_client_id', ''));
        self::assertNull($context->getClientId());

        $context->setRequest((new ServerRequest())->withAttribute('oauth_client_id', 42));
        self::assertNull($context->getClientId());
    }
}
