<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\CircleInvitation;

class CircleController extends Controller
{
    /**
     * Create a new Circle (PENDING STATUS)
     */
    public function create(Request $request)
    {

        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->user_id;

        $validator = Validator::make($request->all(), [
            'circle_name' => 'required|string|max:100|unique:circles,circle_name',
            'description' => 'required|string|min:5',
            'profile_photo' => 'nullable|url',
            'categories' => 'required|array|min:1',
            'members' => 'required|array|min:10',

            'circle_type' => 'nullable|in:public,private',
            'allow_join_request' => 'nullable|boolean',
            'only_admin_can_post' => 'nullable|boolean',

            'join_payment' => 'nullable|boolean',
            'payment' => 'nullable|numeric|min:0',

            'post_payment' => 'nullable|boolean',
            'post_cost' => 'nullable|numeric|min:0',

            'enable_sponsor_price' => 'nullable|boolean',
            'sponsor_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $circle = Circle::create([
                'circle_name'           => $request->circle_name,
                'description'           => $request->description,
                'profile_photo'         => $request->profile_photo ?? null,
                'categories'            => json_encode($request->categories),
                'circle_type'           => $request->circle_type ?? 'public',
                'allow_join_request'    => $request->allow_join_request ?? 0,
                'only_admin_can_post'   => $request->only_admin_can_post ?? 0,
                'join_payment'          => $request->join_payment ?? 0,
                'payment'               => $request->payment ?? 0,
                'post_payment'          => $request->post_payment ?? 0,
                'post_cost'             => $request->post_cost ?? 0,
                'enable_sponsor_price'  => $request->enable_sponsor_price ?? 0,
                'sponsor_price'         => $request->sponsor_price ?? 0,
                'created_by'            => $userId,
                'status'                => 'pending',
                'invitations_sent'      => count($request->members),
                'invitations_accepted'  => 0,
                'invitations_declined'  => 0,
            ]);

            // Insert invitations
            foreach ($request->members as $memberId) {
                CircleInvitation::create([
                    'circle_id' => $circle->circle_id,
                    'user_id' => $memberId,
                    'status'    => 'pending'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Circle created successfully and invitations sent.',
                'data' => $circle
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create circle',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * View Circle details
     */
    public function show($circleId)
    {
        $circle = Circle::with(['creator', 'members.user', 'invitations.user'])
            ->find($circleId);

        if (!$circle) {
            return response()->json([
                'success' => false,
                'message' => 'Circle not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $circle
        ]);
    }

    public function checkStatus($circleId)
    {
        $circle = Circle::find($circleId);

        return response()->json([
            'success' => true,
            'status' => $circle->status,
            'accepted' => $circle->invitations_accepted,
        ]);
    }
}
