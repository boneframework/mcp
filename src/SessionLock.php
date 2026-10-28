<?php

declare(strict_types=1);

namespace Bone\MCP;

use Symfony\Component\Uid\Uuid;

/**
 * Serialises requests on one MCP session across PHP processes.
 *
 * The SDK loads the session file, handles the message, queues the reply in the session and
 * saves it. Two overlapping requests on one session would each save their own copy, and the
 * last save wins: the other reply is lost (an empty 202) or returned to the wrong request.
 * Holding an exclusive lock per session around load → handle → save prevents that.
 */
class SessionLock
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Run $work while holding the lock for $sessionId. Requests without a valid session id
     * (initialize creates a new session) run unlocked.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function synchronized(?string $sessionId, callable $work): mixed
    {
        if ($sessionId === null || !Uuid::isValid($sessionId)) {
            return $work();
        }

        $path = $this->pathFor(Uuid::fromString($sessionId));
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not open MCP session lock "%s".', $path));
        }

        try {
            flock($handle, LOCK_EX);
            // the session store's gc removes files idle for longer than the TTL; keep this one fresh
            touch($path);

            return $work();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function pathFor(Uuid $sessionId): string
    {
        return rtrim($this->directory, '/') . '/' . $sessionId->toRfc4122() . '.lock';
    }
}
