<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['product_id', 'change', 'reason', 'sale_id', 'user_id'];

    protected function casts(): array
    {
        return ['change' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Itala ang galaw ng stock (walang gagawin kapag 0 ang change). */
    public static function log(int $productId, int $change, string $reason, ?int $saleId = null): void
    {
        if ($change === 0) {
            return;
        }

        static::create([
            'product_id' => $productId,
            'change'     => $change,
            'reason'     => $reason,
            'sale_id'    => $saleId,
            'user_id'    => auth()->id(),
        ]);
    }
}
