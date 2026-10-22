<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_checklists', function (Blueprint $table): void {
            $table->id();
            $table->string('pc_name');
            $table->string('serial_no');
            $table->string('end_user');
            $table->string('office');
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_at');
            $table->json('checklist_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_checklists');
    }
};
