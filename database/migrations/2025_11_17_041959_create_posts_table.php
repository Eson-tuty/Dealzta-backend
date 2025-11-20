<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            // User reference
            $table->unsignedBigInteger('user_id');

            // Media
            $table->string('media_type')->nullable();        // image / video
            $table->json('media_urls')->nullable();          // array of image URLs
            $table->string('cover_image')->nullable();       // first image

            // Content
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('caption')->nullable();
            $table->string('custom_name')->nullable();
            $table->string('display_name')->nullable();

            // Post type & category
            $table->string('post_base_type')->default('product'); // product / service / ad
            $table->integer('category')->nullable();
            $table->json('categories')->nullable(); // array list

            // Product info
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('product_quantity')->default(0);
            $table->string('product_claim_type')->nullable();      // single/multiple
            $table->integer('product_quantity_per_claim')->nullable();

            // Ad / Post settings
            $table->string('delivery_option')->nullable();
            $table->string('ad_action_type')->nullable();          // claim, buy now, etc.
            $table->integer('reach_distance')->default(5);
            $table->integer('post_duration')->default(7);

            // Targeting
            $table->json('target_age_groups')->nullable();
            $table->string('location')->nullable();
            $table->text('hashtags')->nullable();

            // AI Filters
            $table->json('filter')->nullable();
            $table->json('overlays')->nullable();

            // Interactions
            $table->integer('likes')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('views')->default(0);  // For increment views
            $table->integer('good_feedback_count')->default(0);
            $table->integer('bad_feedback_count')->default(0);

            // Flags
            $table->boolean('is_premium_post')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_sharing')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_user_created')->default(true);

            // Extra
            $table->integer('send')->default(0);
            $table->timestamp('expires_at')->nullable();

            // Base
            $table->timestamps();

            // FK
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
