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
        Schema::create('cells', function (Blueprint $table) {
            $table->id();
            $table->integer('number');
            $table->foreignId('rack_id')->constrained('racks');
            $table->integer('level');
            $table->integer('position');
            $table->timestamps();

            $table->unique(['rack_id', 'number']);
            $table->unique(['rack_id', 'level', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cells');
    }
};
