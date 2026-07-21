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
        Schema::create('actuator_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('actuator', ['humidifier', 'led', 'fan']);
            $table->enum('action', ['on', 'off']);
            $table->enum('trigger', ['automatic', 'manual']);
            $table->string('triggered_by')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actuator_logs');
    }
};
