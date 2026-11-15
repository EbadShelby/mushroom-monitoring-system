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
        Schema::create('camera_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growing_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('flush_number')->default(1);
            $table->date('captured_date');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera_snapshots');
    }
};
