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
        Schema::table('growing_cycles', function (Blueprint $table) {
            $table->string('mushroom_variety')->default('Pleurotus ostreatus')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('growing_cycles', function (Blueprint $table) {
            $table->string('mushroom_variety')->default('Pleurotus sajor-caju')->change();
        });
    }
};
