<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_verifications', function (Blueprint $table) {
            $table->string('annual_revenue')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('business_verifications', function (Blueprint $table) {
            $table->decimal('annual_revenue', 18, 2)->nullable()->change();
        });
    }
};
