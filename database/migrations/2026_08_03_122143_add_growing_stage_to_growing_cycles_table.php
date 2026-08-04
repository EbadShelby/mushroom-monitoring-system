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
            $table->enum('growing_stage', ['colonization', 'fruiting'])->default('colonization')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('growing_cycles', function (Blueprint $table) {
            $table->dropColumn('growing_stage');
        });
    }
};
