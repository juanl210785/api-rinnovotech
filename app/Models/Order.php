<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_name',
        'phone',
        'shipping_address',
        'city',
        'envio_type',
        'shipping_cost',
        'total',
        'status',
        'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:2',
            'total'         => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
