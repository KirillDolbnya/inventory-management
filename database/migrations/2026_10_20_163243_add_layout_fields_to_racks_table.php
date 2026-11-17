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
        Schema::table('racks', function (Blueprint $table) {
            $table->decimal('position_x', total: 8, places: 3)->nullable();
            $table->decimal('position_y', total: 8, places: 3)->nullable();
            $table->decimal('position_z', total: 8, places: 3)->nullable();
            $table->decimal('rotation_y_deg', total: 8, places: 3)->nullable();
            $table->decimal('width', total: 8, places: 3)->nullable();
            $table->decimal('depth', total: 8, places: 3)->nullable();
            $table->decimal('height', total: 8, places: 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropColumn([
                'position_x',
                'position_y',
                'position_z',
                'rotation_y_deg',
                'width',
                'depth',
                'height',
            ]);
        });
    }
};
