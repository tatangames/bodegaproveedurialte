<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALIDAS
     */
    public function up(): void
    {
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_departamento')->unsigned()->nullable();
            $table->bigInteger('id_tiposalida')->unsigned()->nullable();

            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->string('numero_solicitud', 100)->nullable();

            $table->foreign('id_tiposalida')->references('id')->on('tipo_salida');
            $table->foreign('id_departamento')->references('id')->on('departamentos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};
