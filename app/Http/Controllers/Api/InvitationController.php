<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\CircleInvitation;

class InvitationController extends Controller
{
    /**
     * Accept Circle Invitation
     */
    public function accept($circleId)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->user_id;

        $inv = CircleInvitation::where('circle_id', $circleId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if (!$inv) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found or already responded.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Update invitation
            $inv->update([
                'status' => 'accepted',
                'accepted_at' => now()
            ]);

            // Add member
            CircleMember::create([
                'circle_id' => $circleId,
                'user_id' => $userId,
                'role' => 'member',
                'joined_at' => now()
            ]);

            // Update circle counts
            $circle = Circle::find($circleId);
            $circle->invitations_accepted += 1;
            $circle->save();

            // If accepted count >= 10 -> activate the circle
            if ($circle->invitations_accepted >= 10 && $circle->status === 'pending') {
                $circle->update(['status' => 'active']);

                // Make creator admin (if not existing)
                CircleMember::firstOrCreate(
                    ['circle_id' => $circleId, 'user_id' => $circle->created_by],
                    ['role' => 'admin', 'joined_at' => now()]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted successfully',
                'circle_status' => $circle->status
            ]);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Decline Invitation
     */
    public function decline($circleId)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->user_id;

        $inv = CircleInvitation::where('circle_id', $circleId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if (!$inv) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found or already responded.'
            ], 404);
        }

        $inv->update([
            'status' => 'declined',
            'declined_at' => now()
        ]);

        // Update circle decline count
        $circle = Circle::find($circleId);
        $circle->invitations_declined += 1;
        $circle->save();

        return response()->json([
            'success' => true,
            'message' => 'Invitation declined'
        ]);
    }

    public function adminRequests()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $circleIds = Circle::where('created_by', $user->user_id)->pluck('circle_id');

        $requests = CircleInvitation::whereIn('circle_id', $circleIds)
            ->where('status', 'pending')
            ->with('user')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function approve($requestId)
    {
        $inv = CircleInvitation::find($requestId);

        if (!$inv || $inv->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Invalid request'], 404);
        }

        $inv->update(['status' => 'accepted', 'accepted_at' => now()]);

        $circle = Circle::find($inv->circle_id);
        $circle->increment('invitations_accepted');

        // Activate circle
        if ($circle->invitations_accepted >= 10 && $circle->status === 'pending') {
            $circle->update(['status' => 'active']);
        }

        return response()->json(['success' => true, 'message' => 'Request approved']);
    }

    public function reject($requestId)
    {
        $inv = CircleInvitation::find($requestId);

        if (!$inv || $inv->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Invalid request'], 404);
        }

        $inv->update(['status' => 'declined', 'declined_at' => now()]);

        $circle = Circle::find($inv->circle_id);
        $circle->increment('invitations_declined');

        return response()->json(['success' => true, 'message' => 'Request rejected']);
    }
}
