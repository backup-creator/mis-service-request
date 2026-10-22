<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_checklists', function (Blueprint $table): void {
            $table->text('other')->nullable();
            $table->string('technician_signature')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_checklists', function (Blueprint $table): void {
            $table->dropColumn(['other', 'technician_signature']);
        });
    }
};
