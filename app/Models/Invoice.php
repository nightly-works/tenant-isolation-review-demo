<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'number', 'amount_cents', 'due_on', 'paid_at'])]
class Invoice extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['due_on' => 'date', 'paid_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('paid_at')->whereDate('due_on', '<', today());
    }
}
