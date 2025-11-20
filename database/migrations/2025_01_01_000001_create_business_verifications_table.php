<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_verifications', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED
            $table->unsignedBigInteger('user_id');

            $table->string('business_name', 255);
            $table->text('business_description')->nullable();
            $table->string('business_type', 80)->nullable();
            $table->string('business_country', 80)->default('India');

            $table->string('registration_number', 64)->nullable();
            $table->date('registration_date')->nullable();
            $table->boolean('has_registration')->default(0);
            $table->boolean('gst_verified')->default(0);

            $table->string('owner_name', 150)->nullable();
            $table->string('owner_email', 150)->nullable();
            $table->string('phone_number', 32)->nullable();
            $table->string('alternative_phone', 32)->nullable();
            $table->string('website', 512)->nullable();

            // Address
            $table->text('business_address')->nullable();
            $table->string('location_address_line', 512)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('state', 128)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('country', 80)->default('India');

            // Financial
            $table->decimal('annual_revenue', 18, 2)->nullable();
            $table->integer('number_of_employees')->nullable();
            $table->integer('years_in_business')->nullable();

            // Bank Details
            $table->string('account_holder_name', 255)->nullable();
            $table->string('account_number', 128)->nullable();
            $table->string('ifsc_routing', 64)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('branch_name', 255)->nullable();
            $table->string('upi_id', 128)->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'verified',
                'rejected'
            ])->default('draft');

            $table->boolean('terms_accepted')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_verifications');
    }
};
