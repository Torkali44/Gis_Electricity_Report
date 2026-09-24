<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electricity_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('client_name')->nullable();
            $table->string('address')->nullable();
            $table->string('reference_code', 120)->nullable();
            $table->date('inspection_date')->nullable();
            $table->string('villa_number')->nullable();
            $table->string('road')->nullable();
            $table->string('compound')->nullable();
            $table->string('area')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['reference_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electricity_reports');
    }
};
