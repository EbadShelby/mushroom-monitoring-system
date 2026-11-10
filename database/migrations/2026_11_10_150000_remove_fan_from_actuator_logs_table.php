<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('actuator_logs')->where('actuator', 'fan')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE actuator_logs MODIFY COLUMN actuator ENUM('humidifier', 'led') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE actuator_logs MODIFY COLUMN actuator ENUM('humidifier', 'led', 'fan') NOT NULL");
        }
    }
};
