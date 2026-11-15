<?php

use Illuminate\Database\Migrations\Migration;

/**
 * This migration is a no-op. The `role` and `contact_number` columns were
 * already included in the base create_users_table migration.
 * Kept here to avoid breaking the migration history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op: columns already exist in create_users_table migration.
    }

    public function down(): void
    {
        // No-op.
    }
};
