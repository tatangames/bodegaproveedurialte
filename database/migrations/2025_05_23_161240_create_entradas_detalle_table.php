<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ENTRADAS DETALLE
     */
    public function up(): void
    {
        Schema::create('entradas_detalle', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('entradas_id')->unsigned();
            $table->bigInteger('material_id')->unsigned();

            // La cantidad registrada que no cambia
            $table->integer('cantidad_inicial');

            // 4 DECIMALES PARA PRECIO UNITARIO
            $table->decimal('precio', 10,4)->default(0);

            $table->foreign('entradas_id')->references('id')->on('entrada');
            $table->foreign('material_id')->references('id')->on('materiales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entradas_detalle');
    }
};
