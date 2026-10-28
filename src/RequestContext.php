<?php

declare(strict_types=1);

namespace Bone\MCP;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The HTTP request currently being served by the MCP endpoint. Inject it into tool classes to
 * read request attributes, e.g. the `oauth_client_id` set by an OAuth resource server middleware.
 */
class RequestContext
{
    private ?ServerRequestInterface $request = null;

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->request?->getAttribute($name, $default) ?? $default;
    }

    /**
     * The authenticated OAuth client, when the endpoint is protected by an OAuth middleware.
     */
    public function getClientId(): ?string
    {
        $clientId = $this->getAttribute('oauth_client_id');

        return is_string($clientId) && $clientId !== '' ? $clientId : null;
    }
}
