<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Category;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * List paginated products with query filters and sorting.
     */
    public function index(IndexProductRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::with('category');

        // 1. Search (delegated to model query scope)
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // 2. Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        // 3. Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 4. Filter low stock based on individual reorder_level or global config threshold
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        // 5. Whitelisted sorting
        $allowedSortColumns = ['id', 'name', 'sku', 'price', 'stock', 'reorder_level', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSortColumns, true)
            ? $request->input('sort_by')
            : 'created_at';

        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $direction);

        // 6. Configurable per_page (capped at 50)
        $perPage = min($request->integer('per_page', 10), 50);
        $perPage = $perPage > 0 ? $perPage : 10;

        $products = $query->paginate($perPage);

        return $this->paginated(
            ProductResource::collection($products),
            'Products retrieved successfully.'
        );
    }

    /**
     * Create a new product with an initial stock movement recorded atomically.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $product = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $productData = Arr::except($validated, ['reason']);
            $product = Product::create($productData);

            if ((int) $product->stock > 0) {
                $product->movements()->create([
                    'user_id' => $request->user()?->id,
                    'change' => (int) $product->stock,
                    'stock_before' => 0,
                    'stock_after' => (int) $product->stock,
                    'reason' => $validated['reason'] ?? 'Initial stock',
                    'created_at' => now(),
                ]);
            }

            return $product;
        });

        $product->load('category');

        return $this->success(
            new ProductResource($product),
            'Product created successfully.',
            201
        );
    }

    /**
     * Show a single product with its category.
     */
    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $product->load('category');

        return $this->success(
            new ProductResource($product),
            'Product retrieved successfully.'
        );
    }

    /**
     * Update a product, logging any stock movement inside a database transaction.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        DB::transaction(function () use ($request, $product) {
            $validated = $request->validated();

            if (array_key_exists('stock', $validated)) {
                $oldStock = (int) $product->stock;
                $newStock = (int) $validated['stock'];

                if ($newStock !== $oldStock) {
                    $delta = $newStock - $oldStock;
                    $product->movements()->create([
                        'user_id' => $request->user()?->id,
                        'change' => $delta,
                        'stock_before' => $oldStock,
                        'stock_after' => $newStock,
                        'reason' => $validated['reason'] ?? 'Stock adjustment',
                        'created_at' => now(),
                    ]);
                }
            }

            $product->update(Arr::except($validated, ['reason']));
        });

        $product->load('category');

        return $this->success(
            new ProductResource($product),
            'Product updated successfully.'
        );
    }

    /**
     * Delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return $this->success(null, 'Product deleted successfully.');
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restore(int|string $id): JsonResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $product);

        if (Category::withTrashed()->find($product->category_id)?->trashed()) {
            return $this->error(
                'Cannot restore product because its category has been deleted. Restore the category first.',
                409
            );
        }

        $product->restore();
        $product->load('category');

        return $this->success(
            new ProductResource($product),
            'Product restored successfully.'
        );
    }

    /**
     * Get stock movement audit trail for a product.
     */
    public function movements(int|string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        Gate::authorize('view', $product);

        $perPage = min(request()->integer('per_page', 15), 50);

        $movements = $product->movements()
            ->with('user')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginated(
            StockMovementResource::collection($movements),
            'Stock movements retrieved successfully.'
        );
    }
}
