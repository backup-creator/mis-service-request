<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_request_categories', function (Blueprint $table) {
            $table->string('code', 100)->nullable();
        });

        Schema::table('service_request_priorities', function (Blueprint $table) {
            $table->string('code', 100)->nullable();
        });

        Schema::table('service_request_statuses', function (Blueprint $table) {
            $table->string('code', 100)->nullable();
        });

        $this->backfillCodes('service_request_categories', 'name');
        $this->backfillCodes('service_request_priorities', 'name');
        $this->backfillCodes('service_request_statuses', 'slug');

        Schema::table('service_request_categories', function (Blueprint $table) {
            $table->string('code', 100)->nullable(false)->change();
        });

        Schema::table('service_request_priorities', function (Blueprint $table) {
            $table->string('code', 100)->nullable(false)->change();
        });

        Schema::table('service_request_statuses', function (Blueprint $table) {
            $table->string('code', 100)->nullable(false)->change();
        });

        Schema::table('service_request_categories', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('service_request_priorities', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('service_request_statuses', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('service_request_categories', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::table('service_request_priorities', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::table('service_request_statuses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    private function backfillCodes(string $table, string $sourceColumn): void
    {
        $usedCodes = [];

        foreach (DB::table($table)->orderBy('id')->get(['id', $sourceColumn]) as $record) {
            $base = Str::upper(Str::slug($record->{$sourceColumn})) ?: "LOOKUP-{$record->id}";
            $code = $base;
            $suffix = 2;

            while (isset($usedCodes[$code])) {
                $code = "{$base}-{$suffix}";
                $suffix++;
            }

            $usedCodes[$code] = true;
            DB::table($table)->where('id', $record->id)->update(['code' => $code]);
        }
    }
};
