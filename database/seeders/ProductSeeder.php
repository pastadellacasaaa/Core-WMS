<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'client_code' => 'SCS',
                'sku' => 'CHILLED-0014',
                'uom' => 'KG',
                'category' => 'CHILLED',
                'unit_per_pack' => 1,
                'weight_kg' => 1,
            ],
            [
                'client_code' => 'SCS',
                'sku' => 'CHILLED-0044',
                'uom' => 'KG',
                'category' => 'CHILLED',
                'unit_per_pack' => 1,
                'weight_kg' => 1,
            ],
            [
                'client_code' => 'SCS',
                'sku' => 'DRY-0211',
                'uom' => 'CTN',
                'category' => 'DRY',
                'unit_per_pack' => 5,
                'weight_kg' => 5,
            ],
            [
                'client_code' => 'SCS',
                'sku' => 'DRY-0211',
                'uom' => 'PKT',
                'category' => 'DRY',
                'unit_per_pack' => 1,
                'weight_kg' => 1,
            ],
            [
                'client_code' => 'SCS',
                'sku' => 'FROZEN-0027',
                'uom' => 'CTN',
                'category' => 'FROZEN',
                'unit_per_pack' => 20,
                'weight_kg' => 10,
            ],
            [
                'client_code' => 'SCS',
                'sku' => 'FROZEN-0027',
                'uom' => 'PKT',
                'category' => 'FROZEN',
                'unit_per_pack' => 1,
                'weight_kg' => 0.5,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                [
                    'client_code' => $product['client_code'],
                    'sku' => $product['sku'],
                    'uom' => $product['uom'],
                ],
                $product
            );
        }
    }
}