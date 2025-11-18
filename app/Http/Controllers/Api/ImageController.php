<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class ImageController extends Controller
{
    public function uploadProfileImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $image = $request->file('profile_photo');

        $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

        $path = $image->storeAs('profiles', $filename, 'public');
        $url = url('storage/' . $path);

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => [
                'url' => $url,
                'path' => $path,
            ]
        ]);
    }
}
