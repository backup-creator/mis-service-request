<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('assigned_staff_id')
                ->nullable()
                ->after('requester_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('resolution_summary')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
        });

        DB::table('service_requests')
            ->whereNull('submitted_at')
            ->update(['submitted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['assigned_staff_id']);
            $table->dropIndex(['submitted_at']);
            $table->dropColumn(['assigned_staff_id', 'resolution_summary', 'submitted_at']);
        });
    }
};
