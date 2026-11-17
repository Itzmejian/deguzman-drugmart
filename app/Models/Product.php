<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'category', 'type', 'subtitle', 'unit', 'price',
        'stock', 'expiration_date', 'photo_path', 'description',
    ];

    /** Galing sa logbook ng botika */
    public const TYPES = ['branded' => 'Branded', 'generic' => 'Generic', 'grocery' => 'Grocery'];

    /** Classification */
    public const CLASSIFICATIONS = ['otc' => 'OTC Medicine', 'rx' => 'Rx Prescription', 'grocery' => 'Grocery'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'expiration_date' => 'date',
        ];
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /* ---------- Accessors / helpers ---------- */

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo_path
            ? route('media', ['path' => $this->photo_path], false)
            : asset('images/no-image.svg');
    }

    public function isLowStock(): bool
    {
        return $this->stock < (int) Setting::get('low_stock_below');
    }

    public function isExpiringSoon(): bool
    {
        return $this->expiration_date !== null
            && ! $this->isExpired()
            && $this->expiration_date->lte(now()->addDays((int) Setting::get('expiry_warn_days')));
    }

    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->lt(today());
    }

    /** Walang expiration (grocery lang, hal. napkin). */
    public function hasNoExpiry(): bool
    {
        return $this->expiration_date === null;
    }

    /** "Dec 31, 2027" o "No expiry" */
    public function getExpiryLabelAttribute(): string
    {
        return $this->expiration_date?->format('M d, Y') ?? 'No expiry';
    }

    /** expired | out_of_stock | expiring | low_stock | in_stock */
    public function getStatusAttribute(): string
    {
        if ($this->isExpired())      return 'expired';
        if ($this->stock === 0)      return 'out_of_stock';
        if ($this->isExpiringSoon()) return 'expiring';
        if ($this->isLowStock())     return 'low_stock';
        return 'in_stock';
    }

    public function getCategoryLabelAttribute(): string
    {
        if ($this->type === 'grocery' || $this->category === 'grocery') {
            return 'Grocery';
        }

        return $this->type_label . ' • ' . ($this->category === 'rx' ? 'Rx' : 'OTC');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? 'Generic';
    }

    /* ---------- Scopes ---------- */

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $term
            ? $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('subtitle', 'like', "%{$term}%"))
            : $q;
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('stock', '<', (int) Setting::get('low_stock_below'));
    }

    /**
     * Mabebenta kung wala pang expiry ngayong araw (bukas pa o mas matagal pa).
     * Ang malapit nang mag-expire (pasok sa warning days) ay nabebenta pero may babala sa POS.
     * Bawal: expired na o mag-e-expire ngayong araw.
     */
    public function isSellable(): bool
    {
        return $this->expiration_date === null || $this->expiration_date->gt(today());
    }

    /** Ilang araw na lang bago mag-expire (null kung walang expiry) */
    public function daysToExpiry(): ?int
    {
        return $this->expiration_date ? (int) today()->diffInDays($this->expiration_date->copy()->startOfDay(), false) : null;
    }

    public function scopeSellable(Builder $q): Builder
    {
        return $q->where('stock', '>', 0)
            ->where(fn ($w) => $w->whereNull('expiration_date')
                ->orWhereDate('expiration_date', '>', today()->toDateString()));
    }

    /** Malapit nang mag-expire: hindi pa expired, pero pasok sa warning days. */
    public function scopeExpiringSoon(Builder $q): Builder
    {
        return $q->whereDate('expiration_date', '>=', today())
            ->whereDate('expiration_date', '<=', now()->addDays((int) Setting::get('expiry_warn_days')));
    }

    /** Expired na (lampas na ang petsa). */
    public function scopeExpired(Builder $q): Builder
    {
        return $q->whereDate('expiration_date', '<', today());
    }

    /** Expired o malapit nang mag-expire (para sa Urgent Alerts). */
    public function scopeExpiringOrExpired(Builder $q): Builder
    {
        return $q->whereDate('expiration_date', '<=', now()->addDays((int) Setting::get('expiry_warn_days')));
    }

    public static function generateSku(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)) ?: 'MED';
        do {
            $sku = $prefix . '-' . random_int(10000, 99999);
        } while (static::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
