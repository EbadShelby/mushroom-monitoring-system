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
        Schema::table('camera_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('camera_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by')->nullable(false)->change();
        });
    }
};
