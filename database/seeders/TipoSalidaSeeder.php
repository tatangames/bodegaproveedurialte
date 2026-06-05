<?php

namespace Database\Seeders;

use App\Models\TipoSalida;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoSalidaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TipoSalida::create([
            'nombre' => 'SALIDA CON SOLICITUD',
        ]);

        TipoSalida::create([
            'nombre' => 'SALIDA POR DESPERFECTO',
        ]);
    }
}
