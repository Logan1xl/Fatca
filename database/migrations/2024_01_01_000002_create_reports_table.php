<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('original_filename');
            $table->string('excel_path');
            $table->string('xml_path')->nullable();
            $table->string('xml_corrected_path')->nullable();
            $table->string('encrypted_xml_path')->nullable();
            $table->string('pdf_report_path')->nullable();
            $table->date('reporting_period')->nullable();
            $table->enum('status', ['pending', 'analyzing', 'errors_found', 'valid', 'corrected'])->default('pending');
            $table->integer('total_errors')->default(0);
            $table->integer('total_warnings')->default(0);
            $table->integer('total_records')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
