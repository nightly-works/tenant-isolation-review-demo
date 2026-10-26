<?php

namespace App\Support;

/**
 * The tenant whose data the code may touch. In a request, SetCurrentTenant sets it from the
 * signed-in user; queued jobs, commands and the scheduler set it explicitly with run(). It is
 * never read from the auth guard, which a long-running worker may keep from an earlier job.
 */
class CurrentTenant
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function set(?int $tenantId): void
    {
        $this->id = $tenantId;
    }

    public function run(int $tenantId, callable $callback): mixed
    {
        $previous = $this->id;
        $this->id = $tenantId;

        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
