<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->foreignId('growing_cycle_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        // Backfill existing alerts to the growing cycle active on their sent_at timestamp.
        $cycles = DB::table('growing_cycles')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get(['id', 'start_date', 'end_date', 'status']);

        foreach ($cycles as $cycle) {
            $start = Carbon::parse($cycle->start_date)->startOfDay();
            $end = $cycle->end_date !== null
                ? Carbon::parse($cycle->end_date)->endOfDay()
                : ($cycle->status === 'active' ? null : $start->copy()->endOfDay());

            $query = DB::table('alert_logs')
                ->whereNull('growing_cycle_id')
                ->where('sent_at', '>=', $start);

            if ($end !== null) {
                $query->where('sent_at', '<=', $end);
            }

            $query->update(['growing_cycle_id' => $cycle->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('growing_cycle_id');
        });
    }
};
