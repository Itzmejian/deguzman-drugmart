<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyStock extends Model
{
    protected $fillable = ['date', 'product_id', 'product_name', 'unit', 'type', 'rx', 'price', 'expiration_date',
        'opening', 'sold', 'added', 'removed', 'closing', 'sales_amount'];

    protected function casts(): array
    {
        return ['date' => 'date', 'expiration_date' => 'date', 'rx' => 'boolean', 'price' => 'decimal:2', 'sales_amount' => 'decimal:2'];
    }
}
