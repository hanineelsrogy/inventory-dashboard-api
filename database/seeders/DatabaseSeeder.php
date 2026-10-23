<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create default users (Admin + Staff)
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        User::create([
            'name'     => 'Staff Member',
            'email'    => 'staff@example.com',
            'password' => bcrypt('password'),
            'role'     => 'staff',
        ]);

        // 2. Create 4 Categories
        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Gadgets, devices, and electronic accessories.'],
            ['name' => 'Clothing', 'slug' => 'clothing', 'description' => 'Apparel, fashion, and wearable items.'],
            ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'description' => 'Furniture, appliances, and kitchen essentials.'],
            ['name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors', 'description' => 'Equipment and gear for sports and outdoor activities.'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // 3. Create 15 Products (distributed across categories with varied stock)
        $products = [
            // Electronics (cat 1)
            ['category_id' => 1, 'name' => 'Wireless Mouse', 'sku' => 'ELEC-001', 'price' => 29.99, 'stock' => 50, 'status' => 'active'],
            ['category_id' => 1, 'name' => 'Bluetooth Keyboard', 'sku' => 'ELEC-002', 'price' => 59.99, 'stock' => 3, 'status' => 'active'],
            ['category_id' => 1, 'name' => 'USB-C Hub', 'sku' => 'ELEC-003', 'price' => 45.00, 'stock' => 25, 'status' => 'active'],
            ['category_id' => 1, 'name' => 'Webcam HD', 'sku' => 'ELEC-004', 'price' => 89.99, 'stock' => 0, 'status' => 'inactive'],

            // Clothing (cat 2)
            ['category_id' => 2, 'name' => 'Cotton T-Shirt', 'sku' => 'CLTH-001', 'price' => 19.99, 'stock' => 100, 'status' => 'active'],
            ['category_id' => 2, 'name' => 'Denim Jeans', 'sku' => 'CLTH-002', 'price' => 49.99, 'stock' => 2, 'status' => 'active'],
            ['category_id' => 2, 'name' => 'Winter Jacket', 'sku' => 'CLTH-003', 'price' => 129.99, 'stock' => 15, 'status' => 'active'],
            ['category_id' => 2, 'name' => 'Running Shoes', 'sku' => 'CLTH-004', 'price' => 79.99, 'stock' => 4, 'status' => 'active'],

            // Home & Kitchen (cat 3)
            ['category_id' => 3, 'name' => 'Coffee Maker', 'sku' => 'HOME-001', 'price' => 149.99, 'stock' => 10, 'status' => 'active'],
            ['category_id' => 3, 'name' => 'Blender Pro', 'sku' => 'HOME-002', 'price' => 69.99, 'stock' => 1, 'status' => 'active'],
            ['category_id' => 3, 'name' => 'Desk Lamp LED', 'sku' => 'HOME-003', 'price' => 34.99, 'stock' => 30, 'status' => 'active'],

            // Sports & Outdoors (cat 4)
            ['category_id' => 4, 'name' => 'Yoga Mat', 'sku' => 'SPRT-001', 'price' => 24.99, 'stock' => 60, 'status' => 'active'],
            ['category_id' => 4, 'name' => 'Dumbbells Set', 'sku' => 'SPRT-002', 'price' => 99.99, 'stock' => 8, 'status' => 'active'],
            ['category_id' => 4, 'name' => 'Camping Tent', 'sku' => 'SPRT-003', 'price' => 199.99, 'stock' => 0, 'status' => 'inactive'],
            ['category_id' => 4, 'name' => 'Water Bottle', 'sku' => 'SPRT-004', 'price' => 14.99, 'stock' => 200, 'status' => 'active'],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
