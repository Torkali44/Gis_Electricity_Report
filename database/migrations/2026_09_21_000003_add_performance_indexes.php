<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electricity_reports', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index('title');
            $table->index('client_name');
        });

        Schema::table('report_sections', function (Blueprint $table) {
            $table->index(['electricity_report_id', 'sort_order', 'id']);
            $table->index('table_type');
        });
    }

    public function down(): void
    {
        Schema::table('electricity_reports', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['title']);
            $table->dropIndex(['client_name']);
        });

        Schema::table('report_sections', function (Blueprint $table) {
            $table->dropIndex(['electricity_report_id', 'sort_order', 'id']);
            $table->dropIndex(['table_type']);
        });
    }
};
