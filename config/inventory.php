<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | Products with a stock quantity strictly less than this threshold will
    | be flagged as low stock in filters and dashboard KPI statistics.
    |
    */

    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Total Inventory Value Calculation Policy
    |--------------------------------------------------------------------------
    |
    | Determines whether inactive products should be counted in the
    | total_inventory_value KPI metric.
    |
    | - false (Default): Only active products count toward commercial value.
    |   Inactive products (discontinued, recalled, draft) are excluded.
    | - true: All warehouse items (active + inactive) count toward total asset value.
    |
    */

    'valuation_includes_inactive' => (bool) env('INVENTORY_VALUATION_INCLUDES_INACTIVE', false),

];
