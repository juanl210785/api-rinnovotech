<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usuario Admin por defecto si no existe
        User::firstOrCreate(
            ['email' => 'admin@rinnovotech.com'],
            [
                'name'     => 'Administrador RinnovoTech',
                'password' => 'admin1234',
                'role'     => 'admin',
            ]
        );

        User::factory(10)->create();

        // Estructura de Catálogo (Familias -> Categorías -> Subcategorías)
        $this->call([
            CatalogSeeder::class,
        ]);
    }
}
