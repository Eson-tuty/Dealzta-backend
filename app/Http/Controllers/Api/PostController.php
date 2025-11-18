<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
      // 🔹 Get all posts for logged-in user
    public function index(Request $request)
    {
        $posts = Post::where('user_id', $request->user()->id)
                     ->orderBy('created_at', 'desc')
                     ->get();

        return response()->json($posts);
    }

    // 🔹 Create new post
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_type' => 'required|string',
            'media'      => 'required|file',
            'title'      => 'nullable|string',
            'description'=> 'nullable|string',
        ]);

        if ($validator->fails())
            return response()->json(['error' => $validator->errors()], 422);

        // Save media
        $path = $request->file('media')->store('posts', 'public');

        // Save database entry
        $post = Post::create([
            'user_id'    => $request->user()->id,
            'media_type' => $request->media_type,
            'media_url'  => asset('storage/' . $path),
            'title'      => $request->title,
            'description'=> $request->description,
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json(['message' => 'Post created', 'post' => $post], 201);
    }

    // 🔹 Update post (boost or edit)
    public function update(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)->findOrFail($id);

        $post->update($request->all());

        return response()->json(['message' => 'Updated', 'post' => $post]);
    }

    // 🔹 Increment views
    public function incrementViews(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        $post->increment('views');

        return response()->json(['views' => $post->views]);
    }

    // 🔹 Delete post
    public function destroy(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)->findOrFail($id);

        // Delete media file
        $relativePath = str_replace(asset('storage/') . '/', '', $post->media_url);
        Storage::disk('public')->delete($relativePath);

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }
}
