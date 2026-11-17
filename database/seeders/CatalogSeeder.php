<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Family;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tree = [
            'Computación' => [
                'Laptops' => [
                    'Laptops Refurbished'
                ],                
            ],
            
        ];

        foreach ($tree as $familyName => $categories) {
            $family = Family::firstOrCreate(['name' => $familyName]);

            foreach ($categories as $categoryName => $subcategories) {
                $category = Category::firstOrCreate([
                    'name'      => $categoryName,
                    'family_id' => $family->id,
                ]);

                foreach ($subcategories as $subName) {
                    Subcategory::firstOrCreate([
                        'name'        => $subName,
                        'category_id' => $category->id,
                    ]);
                }
            }
        }
    }
}
