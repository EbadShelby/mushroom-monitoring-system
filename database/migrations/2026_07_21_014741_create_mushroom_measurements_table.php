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
        Schema::create('mushroom_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growing_cycle_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('observed_date');
            $table->integer('flush_number')->default(1);
            $table->float('height_cm')->nullable();
            $table->float('cap_diameter_cm')->nullable();
            $table->integer('fruiting_body_count')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mushroom_measurements');
    }
};
