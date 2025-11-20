<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    /**
     * Fetch posts of logged-in user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $businessId = $request->query('business_id');

        $query = Post::query();

        if ($businessId) {
            // Business mode → load only business posts
            $query->where('business_id', $businessId);
        } else {
            // User mode → load only personal posts (business_id = null)
            $query->where('user_id', $user->id)
                ->whereNull('business_id');
        }

        $posts = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }




    /**
     * Create a new post
     */
    public function store(Request $request)
    {

        \Log::info("AUTH CHECK:", [
            "user" => $request->user(),
            "token" => $request->bearerToken()
        ]);

        $validator = Validator::make($request->all(), [
            'media_type' => 'required|string',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,mp4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // -------------------------------
        //  Upload Images
        // -------------------------------
        $uploadedFiles = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('posts', 'public');
                $uploadedFiles[] = asset('storage/' . $path);
            }
        } elseif ($request->hasFile('video')) {
            // Single video upload
            $path = $request->file('video')->store('posts', 'public');
            $uploadedFiles[] = asset('storage/' . $path);
        }

        $coverImage = $uploadedFiles[0] ?? null;


        // -------------------------------
        //  Create Post
        // -------------------------------
        $post = Post::create([
            'id' => $request->id ?? ('post_' . time()),
            'user_id' => $request->user()->user_id ?? $request->user()->id,
            'business_id' => $request->business_id,

            'title' => $request->title,
            'description' => $request->description,
            'caption' => $request->caption,

            'custom_name' => $request->custom_name,
            'display_name' => $request->display_name,

            'media_type' => $request->media_type,
            'post_base_type' => $request->post_base_type,

            'category' => $request->category,
            'categories' => $request->categories, // auto-cast

            'delivery_option' => $request->delivery_option,
            'ad_action_type' => $request->ad_action_type,

            'price' => $request->price,
            'product_quantity' => $request->product_quantity,
            'product_claim_type' => $request->product_claim_type,
            'product_quantity_per_claim' => $request->product_quantity_per_claim,

            'image' => $coverImage,
            'images' => $uploadedFiles,
            'image_count' => count($uploadedFiles),


            'filter' => $request->filter,
            'overlays' => $request->overlays,
            'target_age_groups' => $request->target_age_groups,

            'reach_distance' => $request->reach_distance,
            'post_duration' => $request->post_duration,
            'location' => $request->location,
            'hashtags' => $request->hashtags,

            'is_premium_post' => $request->boolean('is_premium_post'),
            'allow_comments' => $request->boolean('allow_comments'),
            'allow_sharing' => $request->boolean('allow_sharing'),

            'is_active' => 1,
            'timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),

            'is_user_created' => 1,
            'good_feedback_count' => 0,
            'bad_feedback_count' => 0,
            'send' => $request->send,

            'user' => $request->user,
        ]);
        \Log::info('🔍 Authenticated user:', [
            'user' => $request->user(),
            'token' => $request->bearerToken()
        ]);
        \Log::info("📥 Incoming POST data:", $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => $post
        ], 201);
    }




    /**
     * Update existing post (no image update)
     */
    public function update(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)->findOrFail($id);

        $updateData = [
            'title' => $request->title,
            'description' => $request->description,
            'caption' => $request->caption,

            'custom_name' => $request->custom_name,
            'display_name' => $request->display_name,

            'media_type' => $request->media_type,
            'post_base_type' => $request->post_base_type,

            'category' => $request->category,
            'categories' => $request->categories,

            'price' => $request->price,
            'product_quantity' => $request->product_quantity,
            'product_claim_type' => $request->product_claim_type,
            'product_quantity_per_claim' => $request->product_quantity_per_claim,

            'delivery_option' => $request->delivery_option,
            'ad_action_type' => $request->ad_action_type,

            'filter' => $request->filter,
            'overlays' => $request->overlays,
            'target_age_groups' => $request->target_age_groups,

            'reach_distance' => $request->reach_distance,
            'post_duration' => $request->post_duration,
            'location' => $request->location,
            'hashtags' => $request->hashtags,

            'is_premium_post' => $request->boolean('is_premium_post'),
            'allow_comments' => $request->boolean('allow_comments'),
            'allow_sharing' => $request->boolean('allow_sharing'),


            'send' => $request->send,
            'updated_at' => now(),
        ];

        $post->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully',
            'data' => $post
        ]);
    }




    /**
     * Delete post
     */
    public function destroy(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)->findOrFail($id);

        $images = json_decode($post->images, true);

        if (is_array($images)) {
            foreach ($images as $url) {
                $relative = str_replace(asset('storage/') . '/', '', $url);
                Storage::disk('public')->delete($relative);
            }
        }

        $post->delete();

        return response()->json(['success' => true, 'message' => 'Post deleted']);
    }
}
