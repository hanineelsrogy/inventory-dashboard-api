<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get dashboard statistics / KPI summary.
     */
    public function stats(): JsonResponse
    {
        $data = Cache::remember('dashboard:stats', now()->addHour(), function () {
            $threshold = (int) config('inventory.low_stock_threshold', 5);
            $includeInactive = (bool) config('inventory.valuation_includes_inactive', false);

            // Commercial valuation policy: inactive products are excluded by default
            $valueSql = $includeInactive
                ? 'COALESCE(SUM(price * stock), 0)'
                : "COALESCE(SUM(CASE WHEN status = 'active' THEN price * stock ELSE 0 END), 0)";

            $stats = DB::table('products')
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total_products,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_products,
                    SUM(CASE WHEN stock < COALESCE(reorder_level, ?) THEN 1 ELSE 0 END) as low_stock_count,
                    {$valueSql} as total_inventory_value
                ", ['active', $threshold])->first();

            $latestProducts = Product::with('category')
                ->latest()
                ->take(5)
                ->get();

            return [
                'total_categories' => Category::count(),
                'total_products' => (int) ($stats->total_products ?? 0),
                'active_products' => (int) ($stats->active_products ?? 0),
                'low_stock_count' => (int) ($stats->low_stock_count ?? 0),
                'total_inventory_value' => round((float) ($stats->total_inventory_value ?? 0), 2),
                'latest_products' => ProductResource::collection($latestProducts)->resolve(),
            ];
        });

        return $this->success($data, 'Dashboard statistics retrieved successfully.');
    }
}
