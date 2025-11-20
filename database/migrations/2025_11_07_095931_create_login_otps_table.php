<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('contact', 150); // mobile or email
            $table->string('otp_code', 10);
            $table->enum('otp_type', ['mobile', 'email']);

            $table->boolean('is_verified')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('verified_at')->nullable();

            $table->integer('attempt_count')->default(0);
            $table->integer('max_attempts')->default(3);

            $table->string('ip_address', 50)->nullable();

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_otps');
    }
};
