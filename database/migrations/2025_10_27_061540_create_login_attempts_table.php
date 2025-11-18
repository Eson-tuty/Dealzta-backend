<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email_or_phone', 255);
            $table->string('ip_address', 45);
            $table->boolean('success')->default(false);
            $table->string('failure_reason', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for faster queries
            $table->index('email_or_phone');
            $table->index('ip_address');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('login_attempts');
    }
};