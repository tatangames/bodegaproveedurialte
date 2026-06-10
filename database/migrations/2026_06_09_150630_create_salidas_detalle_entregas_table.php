<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ESPECIFICAR QUE SALIO DE ESTE ITEM
     */
    public function up(): void
    {
        Schema::create('salidas_detalle_entregas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_salida_detalle')->unsigned();
            // PUEDE SER UNA SALIDA GENERAL
            $table->bigInteger('id_departamento')->unsigned()->nullable();
            $table->string('numero_solicitud', 100)->nullable();
            $table->integer('cantidad');
            $table->date('fecha_entrega');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->foreign('id_salida_detalle')->references('id')->on('salidas_detalle');
            $table->foreign('id_departamento')->references('id')->on('departamentos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas_detalle_entregas');
    }
};
