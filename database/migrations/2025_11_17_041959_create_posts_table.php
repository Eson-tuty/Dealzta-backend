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
    Schema::create('posts', function (Blueprint $table) {
        $table->id();

        // User owning the post
        $table->unsignedBigInteger('user_id');

        // Media
        $table->string('media_type'); // image or video
        $table->string('media_url');

        // Content
        $table->string('title')->nullable();
        $table->text('description')->nullable();

        // Views
        $table->unsignedBigInteger('views')->default(0);

        // Boost
        $table->boolean('is_boosted')->default(false);
        $table->timestamp('boost_expiry')->nullable();

        // Expiration (7 days default — optional)
        $table->timestamp('expires_at')->nullable();

        $table->timestamps();

        // Foreign key
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
