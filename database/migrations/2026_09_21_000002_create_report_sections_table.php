<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electricity_report_id')->constrained('electricity_reports')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            /** @var list<array{text: string, color: string}> */
            $table->json('comments_json')->nullable();
            /** @var list<string> relative storage paths */
            $table->json('images_json')->nullable();
            $table->boolean('has_table')->default(false);
            $table->string('table_type', 40)->nullable(); // grounding_main | mcb_panel
            $table->string('table_title')->nullable();
            $table->json('table_data')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['electricity_report_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sections');
    }
};
