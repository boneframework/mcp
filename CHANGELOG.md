# Changelog

## 1.0.2

> Although a patch release, this moves `mcp/sdk` across two minor versions with breaking changes. Apps requiring `^1.0` get it on their next `composer update`; check the upgrade notes below against your tool classes.

- Require `mcp/sdk` ^0.9.0 (from ^0.6.0), which fixes GHSA-7m52-jw36-44r3 / CVE-2026-53965 (fixed upstream in 0.7.1).
- Sessions are locked by the SDK's `FileSessionLock`, configured by the package. New option `session.lock_timeout` (default 30 seconds; a request that waits longer gets `503`).
- New option `allowed_origins`: the SDK now checks `Origin` separately from `Host`. Same-origin and server-side clients (no `Origin` header) are unaffected; cross-origin browser clients must be listed.
- `Bone\MCP\SessionLock` is deprecated and no longer used; it will be removed in 2.0.

Upgrade notes from the SDK (0.7–0.9) that can affect tool classes:
- A tool's `structuredContent` is validated against its `outputSchema` when it declares one; a mismatch becomes an `isError` result. Return `new \stdClass()` for an empty object, since `[]` is sent as an array.
- A list-shaped tool result is sent as `structuredContent` only on protocol `2026-07-28`; older protocol revisions get the JSON in `content`. Prefer returning objects (e.g. `['items' => [...]]`).
- Unknown tools, prompts and completion subjects are answered with JSON-RPC error `-32602` (was `-32002`).
- Element loading is lazy: discovery failures surface on the first request instead of at build time.

See the [mcp/sdk changelog](https://github.com/modelcontextprotocol/php-sdk/blob/main/CHANGELOG.md) for everything else.

## 1.0.1

- Lock each session around a request so concurrent requests on one session no longer lose or swap responses (#41).

## 1.0.0

- Production-ready package: configurable path, middleware, discovery, file sessions, allowed hosts, `RequestContext` (#26).
