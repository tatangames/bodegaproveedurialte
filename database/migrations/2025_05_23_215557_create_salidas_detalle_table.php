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
            $table->bigInteger('salida_id')->unsigned();
            $table->bigInteger('entrada_detalle_id')->unsigned();

            $table->integer('cantidad_salida');

            $table->foreign('salida_id')->references('id')->on('salidas');
            $table->foreign('entrada_detalle_id')->references('id')->on('entradas_detalle');
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
