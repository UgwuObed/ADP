<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_application_id')->constrained()->onDelete('cascade');
            $table->string('document_type'); 
            $table->string('file_name');
            $table->string('file_url');
            $table->string('file_size');
            $table->string('mime_type');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('kyc_documents');
    }
};