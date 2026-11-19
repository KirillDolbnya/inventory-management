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
        Schema::table('cells', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_marker')
                ->nullable()
                ->virtualAs(
                    'CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END'
                );

            $table->unique(
                ['rack_id', 'number', 'active_marker'],
                'cells_rack_number_active_unique'
            );

            $table->unique(
                ['rack_id', 'level', 'position', 'active_marker'],
                'cells_rack_position_active_unique'
            );
        });

        Schema::table('cells', function (Blueprint $table) {
            $table->dropUnique(['rack_id', 'number']);
            $table->dropUnique(['rack_id', 'level', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->unique(['rack_id', 'number']);
            $table->unique(['rack_id', 'level', 'position']);
        });

        Schema::table('cells', function (Blueprint $table) {
            $table->dropUnique('cells_rack_position_active_unique');
            $table->dropUnique('cells_rack_number_active_unique');
            $table->dropColumn('active_marker');
        });
    }
};
