<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank',
        'sender_name',
        'reference',
        'amount',
        'raw_message',
        'status',
        'order_id',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'payment_date' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
