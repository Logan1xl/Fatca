<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validation_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->onDelete('cascade');
            $table->enum('severity', ['error', 'warning', 'info'])->default('error');
            $table->string('category'); // structure, format, characters, size, business_rule
            $table->string('element')->nullable();
            $table->string('row_reference')->nullable();
            $table->text('message');
            $table->text('expected_value')->nullable();
            $table->text('actual_value')->nullable();
            $table->text('suggestion')->nullable();
            $table->string('fatca_section')->nullable();
            $table->boolean('auto_correctable')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_errors');
    }
};
