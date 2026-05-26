<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('informacion_general', function (Blueprint $table) {
            $table->id();

            // REPORTE PIXELES DISTANCIAS
            $table->integer('px_firmas');
            $table->integer('px_observaciones');

            // NOMBRES PARA EL REPORTE: INFORME DE INVENTARIO FÍSICO DE MATERIALES SOBRANTES
            $table->string('c_nombre1', 200)->nullable(); // ELABORADO POR
            $table->string('c_nombre2', 200)->nullable(); // REVISADO POR
            $table->string('c_nombre3', 200)->nullable(); // ES CONFORME


            // NOMBRES PARA EL REPORTE: REPORTE DE SALDOS DE MATERIALES SOBRANTES
            $table->string('s_nombre1', 200)->nullable(); // [ENCARGADO DE BODEGA DE PROYECTO O RESPONSABLE ASIGNADO]
            $table->string('s_nombre2', 200)->nullable(); // JEFE INMEDIATO

            // NOMBRE PARA EL REPORTE: REPORTE DE MATERIALES SOBRANTES TRANSFERIDOS A PROYECTO DE INVERSIÓN PÚBLICA
            // TAMBIEN PARA SALIDA GENERAL
            $table->string('d_nombre1', 200)->nullable(); // [ENCARGADO DE BODEGA DE PROYECTO O RESPONSABLE ASIGNADO]
            $table->string('d_nombre2', 200)->nullable(); // JEFE INMEDIATO

            // NOMBRES PARA REPORTE POR PERIODOS

            $table->string('p_nombre1', 200)->nullable(); // [ENCARGADO DE BODEGA DE PROYECTO O RESPONSABLE ASIGNADO]
            $table->string('p_nombre2', 200)->nullable(); // JEFE INMEDIATO

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informacion_general');
    }
};
