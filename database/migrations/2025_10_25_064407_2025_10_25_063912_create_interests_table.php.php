<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
    {
        Schema::create('interests', function (Blueprint $table) {
            $table->id('interest_id');                // primary key -> id (bigint unsigned)
            $table->string('name');     // 'Food', 'Fashion', ...
            $table->string('icon')->nullable();    // emoji or short string
            $table->string('color')->nullable();   // hex color or token name
            $table->timestamps();       // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interests');
    }
};
