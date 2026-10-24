<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $sku
 * @property string $price
 * @property int $stock
 * @property int|null $reorder_level
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $category
 */
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'price',
        'stock',
        'reorder_level',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'reorder_level' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     * Invalidates dashboard cache when products change.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('dashboard:stats');
        });

        static::deleted(function () {
            Cache::forget('dashboard:stats');
        });

        static::restored(function () {
            Cache::forget('dashboard:stats');
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Scope a query to search products by name or SKU, safely escaping SQL wildcards.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $escaped = addcslashes($term, '%_\\');

        return $query->where(function (Builder $sub) use ($escaped) {
            $sub->where('name', 'LIKE', "%{$escaped}%")
                ->orWhere('sku', 'LIKE', "%{$escaped}%");
        });
    }

    /**
     * Scope a query to include only low-stock products based on individual reorder levels,
     * falling back to the global configured threshold when reorder_level is null.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        $defaultThreshold = (int) config('inventory.low_stock_threshold', 5);

        return $query->whereRaw('stock < COALESCE(reorder_level, ?)', [$defaultThreshold]);
    }
}
