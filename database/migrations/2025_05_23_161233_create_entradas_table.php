<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ENTRADAS POR DIFERENTES AREAS
     */
    public function up(): void
    {
        Schema::create('entradas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_tipocompra')->unsigned()->nullable();
            $table->bigInteger('id_proveedor')->unsigned()->nullable();

            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->string('lote', 100)->nullable();

            $table->foreign('id_tipocompra')->references('id')->on('tipo_compra');
            $table->foreign('id_proveedor')->references('id')->on('proveedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entradas');
    }
};
