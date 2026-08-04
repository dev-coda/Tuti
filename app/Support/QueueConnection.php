<?php

namespace App\Support;

/**
 * Resolves the queue connection used for order/email jobs that must not run
 * inline in a web request.
 *
 * Centralised because the "sync means dispatch somewhere else" rule was copied
 * into every dispatch site, and a copy that disagreed with the running worker
 * silently stranded jobs in a queue nobody consumed.
 */
class QueueConnection
{
    /**
     * The connection background jobs should be dispatched on.
     *
     * `sync` would execute the job inline, defeating the point of queueing, so
     * it is replaced by the configured async fallback.
     */
    public static function forBackgroundWork(): string
    {
        $connection = (string) config('queue.default');

        if ($connection !== 'sync') {
            return $connection;
        }

        return (string) config('queue.async_fallback', 'database');
    }
}
