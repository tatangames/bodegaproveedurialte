<?php

namespace Database\Seeders;

use App\Models\Equipos;
use App\Models\TipoCompra;
use App\Models\TipoEntrada;
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
        // User::factory(10)->create();

        $this->call(RolesSeeder::class);
        $this->call(UsuariosSeeder::class);

        $this->call(TipoSalidaSeeder::class);
        $this->call(TipoCompraSeeder::class);
    }
}
