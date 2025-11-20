<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
    'business_id', 

        'title',
        'description',
        'caption',

        'custom_name',
        'display_name',

        'media_type',
        'post_base_type',

        'category',
        'categories',

        'delivery_option',
        'ad_action_type',

        'price',
        'product_quantity',
        'product_claim_type',
        'product_quantity_per_claim',

        'image',
        'images',
        'image_count',

        'filter',
        'overlays',
        'target_age_groups',

        'reach_distance',
        'post_duration',

        'location',
        'hashtags',

        'is_premium_post',
        'allow_comments',
        'allow_sharing',
        'is_active',
        'is_user_created',

        'good_feedback_count',
        'bad_feedback_count',
        'send',

        'user',       // user snapshot JSON
        'timestamp',
    ];

    protected $casts = [
        'categories' => 'array',
        'images' => 'array',
        'filter' => 'array',
        'overlays' => 'array',
        'target_age_groups' => 'array',
        'user' => 'array',

        'is_premium_post' => 'boolean',
        'allow_comments' => 'boolean',
        'allow_sharing' => 'boolean',
        'is_active' => 'boolean',
        'is_user_created' => 'boolean',
    ];
}
