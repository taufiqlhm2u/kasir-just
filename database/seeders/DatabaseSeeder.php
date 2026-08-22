<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Single User Account
        User::firstOrCreate(
            ['email' => 'admin@kasir.com'],
            [
                'name' => 'Admin Kasir',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Categories
        $categoriesData = [
            ['name' => 'Coffee Based', 'slug' => 'coffee-based'],
            ['name' => 'Milk Based', 'slug' => 'milk-based'],
            ['name' => 'Lainnya', 'slug' => 'lainnya'],
        ];

        foreach ($categoriesData as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        $coffee = Category::where('slug', 'coffee-based')->first();
        $milk = Category::where('slug', 'milk-based')->first();
        $other = Category::where('slug', 'lainnya')->first();

        // 3. Products (Using local images from storage/app/public/produk)
        $productsData = [
            // Coffee Based
            [
                'category_id' => $coffee->id,
                'name' => 'Americano',
                'price' => 9000,
                'stock' => 100,
                'image' => 'produk/Americano.png',
            ],
            [
                'category_id' => $coffee->id,
                'name' => 'Berry Cano',
                'price' => 10000,
                'stock' => 100,
                'image' => 'produk/Berry_cano.png',
            ],
            [
                'category_id' => $coffee->id,
                'name' => 'Black Lychee',
                'price' => 10000,
                'stock' => 100,
                'image' => 'produk/Black_lyche.png',
            ],
            [
                'category_id' => $coffee->id,
                'name' => 'Long Black',
                'price' => 12000,
                'stock' => 100,
                'image' => 'produk/long.jpg',
            ],

            // Milk Based
            [
                'category_id' => $milk->id,
                'name' => 'Latte',
                'price' => 13000,
                'stock' => 100,
                'image' => 'produk/Latte.png',
            ],
            [
                'category_id' => $milk->id,
                'name' => 'Palm Sugar',
                'price' => 13000,
                'stock' => 100,
                'image' => 'produk/Palm_sugar.png',
            ],
            [
                'category_id' => $milk->id,
                'name' => 'Butterscotch',
                'price' => 13000,
                'stock' => 100,
                'image' => 'produk/Butterscotch.png',
            ],
            [
                'category_id' => $milk->id,
                'name' => 'Hazelnut',
                'price' => 13000,
                'stock' => 100,
                'image' => 'produk/hazelnut.jpg',
            ],
            [
                'category_id' => $milk->id,
                'name' => 'Salted Caramel',
                'price' => 13000,
                'stock' => 100,
                'image' => 'produk/salted_caramel.jpg',
            ],

            // Lainnya
            [
                'category_id' => $other->id,
                'name' => 'Matcha Latte',
                'price' => 13000,
                'stock' => 100,
                'image' => 'produk/matcha.webp',
            ],
            [
                'category_id' => $other->id,
                'name' => 'Coklat',
                'price' => 13000,
                'stock' => 100,
                'image' => 'produk/chocolate.webp',
            ],
        ];

        foreach ($productsData as $product) {
            Product::updateOrCreate(['name' => $product['name']], $product);
        }
    }
}
