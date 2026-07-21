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
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growing_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->float('temperature')->nullable();
            $table->float('humidity')->nullable();
            $table->integer('co2_raw')->nullable();
            $table->float('light_level')->nullable();
            $table->integer('soil_moisture')->nullable();
            $table->string('soil_status')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
