<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportController extends Controller
{
    /**
     * Stream an inventory report as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Product::class);

        $defaultThreshold = (int) config('inventory.low_stock_threshold', 5);
        $filename = 'inventory-report-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($defaultThreshold, $request) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // UTF-8 Byte Order Mark (BOM) for broad spreadsheet compatibility (e.g., Microsoft Excel)
            fwrite($handle, "\xEF\xBB\xBF");

            // CSV Column Headers
            fputcsv($handle, [
                'ID',
                'Name',
                'SKU',
                'Category',
                'Price',
                'Stock',
                'Reorder Level',
                'Inventory Value',
                'Status',
                'Low Stock Alert',
                'Created At',
            ]);

            // Query products with eager loaded categories
            $query = Product::with('category');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', (string) $request->input('status'));
            }

            if ($request->boolean('low_stock')) {
                $query->lowStock();
            }

            $query->orderBy('id', 'asc');

            // Memory-efficient cursor iteration
            foreach ($query->cursor() as $product) {
                $reorderLevel = $product->reorder_level;
                $threshold = $reorderLevel ?? $defaultThreshold;
                $isLowStock = $product->stock < $threshold ? 'Yes' : 'No';
                $unitPrice = (float) $product->price;
                $inventoryValue = number_format($unitPrice * $product->stock, 2, '.', '');

                $categoryName = $product->category !== null ? $product->category->name : 'Uncategorized';

                fputcsv($handle, [
                    $product->id,
                    $product->name,
                    $product->sku,
                    $categoryName,
                    number_format((float) $product->price, 2, '.', ''),
                    $product->stock,
                    $reorderLevel !== null ? (string) $reorderLevel : '',
                    $inventoryValue,
                    $product->status,
                    $isLowStock,
                    $product->created_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, $headers);
    }
}
