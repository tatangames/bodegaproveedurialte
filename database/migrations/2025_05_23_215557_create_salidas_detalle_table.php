<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALIDAS DETALLE
     */
    public function up(): void
    {
        Schema::create('salidas_detalle', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_salida')->unsigned();
            $table->bigInteger('id_entrada_detalle')->unsigned();

            $table->string('fecha');
            $table->text('descripcion')->nullable();
            $table->string('numero_solicitud', 100)->nullable();

            $table->integer('cantidad_salida');

            // PARA PODER SEGUIR AGREGANDOLE MAS SALIDAS
            $table->enum('estado', ['pendiente', 'finalizado'])->default('pendiente');

            $table->foreign('id_salida')->references('id')->on('salidas');
            $table->foreign('id_entrada_detalle')->references('id')->on('entradas_detalle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas_detalle');
    }
};
