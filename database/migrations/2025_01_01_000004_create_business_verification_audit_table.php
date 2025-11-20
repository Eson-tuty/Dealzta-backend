<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_verification_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verification_id');
            $table->unsignedBigInteger('changed_by')->nullable();

            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64)->nullable();
            $table->text('comment')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('verification_id')
                  ->references('id')
                  ->on('business_verifications')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_verification_audit');
    }
};
