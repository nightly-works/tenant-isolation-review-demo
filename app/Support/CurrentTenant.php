<?php

namespace App\Support;

/**
 * The tenant whose data the code may touch. In a request it is the signed-in user's;
 * queued jobs, commands and the scheduler must set it explicitly with run().
 */
class CurrentTenant
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id ?? auth()->user()?->tenant_id;
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
