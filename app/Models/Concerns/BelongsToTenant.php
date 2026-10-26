<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use LogicException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query of the model to the current tenant and fills tenant_id on create.
 * Without a current tenant the scope matches nothing and creating fails: code that must
 * see every tenant says so with withoutGlobalScope('tenant').
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = app(CurrentTenant::class)->id();
            $column = $query->getModel()->getTable().'.tenant_id';
            $tenantId ? $query->where($column, $tenantId) : $query->whereRaw('1 = 0');
        });

        static::creating(function ($model) {
            $current = app(CurrentTenant::class)->id();
            if ($current && $model->tenant_id && (int) $model->tenant_id !== $current) {
                throw new LogicException(static::class.' cannot be created for another tenant.');
            }
            $model->tenant_id ??= $current
                ?? throw new LogicException('No current tenant: '.static::class.' cannot be created.');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
