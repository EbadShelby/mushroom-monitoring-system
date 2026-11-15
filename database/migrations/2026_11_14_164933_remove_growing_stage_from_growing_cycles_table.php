<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('growing_cycles', 'growing_stage')) {
            Schema::table('growing_cycles', function (Blueprint $table) {
                $table->dropColumn('growing_stage');
            });
        }

        // Migrate any existing stage-prefixed threshold settings to unified keys
        $keys = [
            'temp_min',
            'temp_max',
            'humidity_low',
            'humidity_high',
            'co2_max',
            'light_min',
            'light_max',
            'soil_warning',
            'soil_critical',
        ];

        foreach ($keys as $key) {
            $value = DB::table('settings')->where('key', "threshold_fruit_{$key}")->value('value')
                ?? DB::table('settings')->where('key', "threshold_col_{$key}")->value('value');

            if ($value !== null) {
                DB::table('settings')->updateOrInsert(
                    ['key' => "threshold_{$key}"],
                    ['value' => $value, 'updated_at' => now()],
                );
            }
        }

        DB::table('settings')
            ->where('key', 'like', 'threshold_col_%')
            ->orWhere('key', 'like', 'threshold_fruit_%')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('growing_cycles', 'growing_stage')) {
            Schema::table('growing_cycles', function (Blueprint $table) {
                $table->enum('growing_stage', ['colonization', 'fruiting'])->default('colonization')->after('status');
            });
        }
    }
};
