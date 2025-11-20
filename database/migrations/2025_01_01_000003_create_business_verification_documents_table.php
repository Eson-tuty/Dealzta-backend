<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verification_id');
            $table->unsignedBigInteger('user_id');

            $table->enum('doc_type', [
                'business_license',
                'shop_image',
                'additional_certificate',
                'other'
            ]);

            $table->string('file_path', 1024);
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamp('uploaded_at')->useCurrent();
            $table->enum('status', ['pending', 'processed', 'rejected'])->default('pending');
            $table->text('notes')->nullable();

            $table->foreign('verification_id')
                  ->references('id')
                  ->on('business_verifications')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->index(['verification_id', 'doc_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_verification_documents');
    }
};
