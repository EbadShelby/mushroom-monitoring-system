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
        Schema::table('mushroom_measurements', function (Blueprint $table) {
            $table->float('weight_g')->nullable()->after('flush_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mushroom_measurements', function (Blueprint $table) {
            $table->dropColumn('weight_g');
        });
    }
};
