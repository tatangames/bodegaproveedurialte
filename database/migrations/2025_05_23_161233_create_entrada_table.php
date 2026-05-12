<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ENTRADAS
     */
    public function up(): void
    {
        Schema::create('entrada', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tipoproyecto_id')->unsigned();
            $table->date('fecha');
            $table->string('descripcion', 800)->nullable();

            $table->foreign('tipoproyecto_id')->references('id')->on('tipoproyecto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrada');
    }
};
