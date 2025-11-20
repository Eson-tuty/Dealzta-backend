<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_verification_industries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verification_id');

            $table->string('industry_key', 100);
            $table->string('display_label', 150)->nullable();
            $table->tinyInteger('selection_order')->nullable(); // 1-5

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('verification_id')
                  ->references('id')
                  ->on('business_verifications')
                  ->onDelete('cascade');

            $table->unique(['verification_id', 'industry_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_verification_industries');
    }
};
