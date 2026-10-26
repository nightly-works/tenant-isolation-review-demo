<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'amount_cents', 'method', 'reference'])]
class Payment extends Model
{
    use BelongsToTenant;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
