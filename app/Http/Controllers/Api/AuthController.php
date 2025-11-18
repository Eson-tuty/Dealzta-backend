<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Session;
use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

use App\Services\OtpService;

class AuthController extends Controller
{

    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => [
                'nullable',
                'string',
                'max:15',
                $request->phone_number ? 'unique:users,phone_number' : ''
            ],
            'email_id' => [
                'nullable',
                'string',
                'email',
                'max:255',
                $request->email_id ? 'unique:users,email_id' : ''
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'username' => 'required|string|max:100|unique:users,username',
            'birthdate' => 'nullable|date',
            'gender' => 'required|string|max:100',
            'user_interest' => 'nullable|string',
            'password' => 'required|string|min:6|confirmed',
            'profile_photo' => 'nullable|string|url', // Just a URL string
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure at least one contact method is provided
        if (!$request->phone_number && !$request->email_id) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => [
                    'contact' => ['Either phone number or email is required']
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'phone_number' => $request->phone_number,
                'email_id' => $request->email_id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'username' => $request->username,
                'birthdate' => $request->birthdate,
                'gender' => $request->gender,
                'profile_photo' => $request->profile_photo, // Save the URL
                'user_interest' => $request->user_interest,
                'password' => Hash::make($request->password),
                'is_active' => true,
                'has_business_profile' => false,
            ]);

            // $user->phone_otp = $this->generateOTP();
            // $user->email_otp = $this->generateOTP();
            $user->save();

            $accessToken = JWTAuth::fromUser($user);
            $refreshToken = $this->generateRefreshToken($user);

            $session = $this->createSession($user, $accessToken, $refreshToken, $request);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => $this->formatUser($user),
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            // Log validation failure
            $this->logLoginAttempt(
                $request->identifier ?? 'unknown',
                $request,
                false,
                'Validation failed'
            );

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $identifier = $request->identifier;

            $user = User::where('email_id', $identifier)
                ->orWhere('phone_number', $identifier)
                ->orWhere('username', $identifier)
                ->first();

            if (!$user) {
                // Log failed login - user not found
                $this->logLoginAttempt(
                    $identifier,
                    $request,
                    false,
                    'User not found'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if (!$user->is_active) {
                // Log failed login - inactive account
                $this->logLoginAttempt(
                    $identifier,
                    $request,
                    false,
                    'Account is inactive'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive'
                ], 403);
            }

            if (!Hash::check($request->password, $user->password)) {
                // Log failed login - wrong password
                $this->logLoginAttempt(
                    $identifier,
                    $request,
                    false,
                    'Invalid password'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $accessToken = JWTAuth::fromUser($user);
            $refreshToken = $this->generateRefreshToken($user);

            $session = $this->createSession($user, $accessToken, $refreshToken, $request);

            // Log successful login
            $this->logLoginAttempt(
                $identifier,
                $request,
                true,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $this->formatUser($user),
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ]
            ], 200);
        } catch (\Exception $e) {
            // Log system error
            $this->logLoginAttempt(
                $request->identifier ?? 'unknown',
                $request,
                false,
                'System error: ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log login attempt
     */
    private function logLoginAttempt($identifier, Request $request, $success, $failureReason = null)
    {
        try {
            LoginAttempt::create([
                'email_or_phone' => $identifier,
                'ip_address' => $this->getClientIp($request),
                'success' => $success,
                'failure_reason' => $failureReason,
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log login attempt: ' . $e->getMessage());
        }
    }
    /**
     * ✅ CHECK ACCOUNT EXISTS FOR PASSWORD RESET
     */
    public function checkAccountForReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact' => 'required|string',
            'type' => 'required|in:email,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = $request->contact;
            $type = $request->type;
            $exists = false;

            if ($type === 'email') {
                $exists = User::where('email_id', $contact)->exists();
            } else {
                // Normalize phone number for search
                $normalizedPhone = $this->normalizePhone($contact);

                $exists = User::where(function ($query) use ($normalizedPhone, $contact) {
                    $query->where('phone_number', $contact)
                        ->orWhere('phone_number', $normalizedPhone)
                        ->orWhere('phone_number', 'like', '%' . substr($normalizedPhone, -10));
                })->exists();
            }

            return response()->json([
                'success' => true,
                'exists' => $exists
            ], 200);
        } catch (\Exception $e) {
            Log::error('Check Account Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check account'
            ], 500);
        }
    }

    /**
     * ✅ SEND PASSWORD RESET OTP
     */
    public function sendPasswordResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact' => 'required|string',
            'type' => 'required|in:email,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = $request->contact;
            $type = $request->type;
            $ipAddress = $this->getClientIp($request);

            // Find the user
            $user = null;
            if ($type === 'email') {
                $user = User::where('email_id', $contact)->first();
            } else {
                $normalizedPhone = $this->normalizePhone($contact);

                $user = User::where(function ($query) use ($normalizedPhone, $contact) {
                    $query->where('phone_number', $contact)
                        ->orWhere('phone_number', $normalizedPhone)
                        ->orWhere('phone_number', 'like', '%' . substr($normalizedPhone, -10));
                })->first();
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            // ✅ Use existing OtpService to send OTP
            $result = $this->otpService->sendOtp(
                $contact,
                $type,
                $user->user_id,
                $ipAddress,
                false // is_resend = false
            );

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Send Password Reset OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ VERIFY PASSWORD RESET OTP
     */
    public function verifyPasswordResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact' => 'required|string',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Log::info('🔐 Verify Password Reset OTP', [
                'contact' => $request->contact,
                'otp_code' => $request->otp_code,
            ]);

            // ✅ Use existing OtpService to verify
            $result = $this->otpService->verifyOtp(
                $request->contact,
                $request->otp_code
            );

            Log::info('📊 Password Reset OTP Verification Result', $result);

            // ✅ If verification successful, generate a temporary reset token
            if ($result['success']) {
                // Generate a temporary token for password reset (valid for 15 minutes)
                $resetToken = bin2hex(random_bytes(32));

                // Store reset token temporarily (you can use cache or a temporary column)
                Cache::put(
                    'password_reset_' . $request->contact,
                    $resetToken,
                    now()->addMinutes(15)
                );

                $result['reset_token'] = $resetToken;
            }

            $statusCode = $result['success'] ? 200 : 400;
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('❌ Verify Password Reset OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP'
            ], 500);
        }
    }

    /**
     * ✅ RESET PASSWORD
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact' => 'required|string',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // ✅ Verify reset token
            $storedToken = Cache::get('password_reset_' . $request->contact);

            if (!$storedToken || $storedToken !== $request->reset_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Find user
            $user = User::where('email_id', $request->contact)
                ->orWhere('phone_number', $request->contact)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // ✅ Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // ✅ Clear reset token from cache
            Cache::forget('password_reset_' . $request->contact);

            // ✅ Optionally: Invalidate all existing sessions for security
            Session::where('user_id', $user->user_id)->delete();

            Log::info('✅ Password reset successful for user: ' . $user->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully. Please login with your new password.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('❌ Reset Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }

    /**
     * ✅ HELPER: Normalize phone number
     */
    private function normalizePhone($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading country codes
        if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
            $phone = substr($phone, 2);
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    public function checkUsername(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:30|regex:/^[a-z0-9._]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $username = strtolower($request->username);
        $usernameExists = User::where('username', $username)->exists();

        if ($usernameExists) {
            return response()->json([
                'success' => false,
                'exists' => true,
                'message' => 'This username is already taken'
            ], 200);
        }

        return response()->json([
            'success' => true,
            'exists' => false,
            'message' => 'Username is available'
        ], 200);
    }

    public function checkContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string|max:15',
            'email_id' => 'nullable|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure at least one contact method is provided
        if (!$request->phone_number && !$request->email_id) {
            return response()->json([
                'success' => false,
                'message' => 'Either phone number or email is required'
            ], 422);
        }

        try {
            $exists = false;
            $message = '';
            $contactType = '';

            // Check phone number
            if ($request->phone_number) {
                $phoneExists = User::where('phone_number', $request->phone_number)->exists();
                if ($phoneExists) {
                    $exists = true;
                    $contactType = 'phone';
                    $message = 'This phone number is already registered';
                }
            }

            // Check email
            if ($request->email_id) {
                $emailExists = User::where('email_id', $request->email_id)->exists();
                if ($emailExists) {
                    $exists = true;
                    $contactType = 'email';
                    $message = 'This email address is already registered';
                }
            }

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'exists' => true,
                    'contact_type' => $contactType,
                    'message' => $message
                ], 409); // 409 Conflict
            }

            return response()->json([
                'success' => true,
                'exists' => false,
                'message' => 'Contact information is available'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check contact',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string|max:15',
            'email_id' => 'nullable|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure at least one contact method is provided
        if (!$request->phone_number && !$request->email_id) {
            return response()->json([
                'success' => false,
                'message' => 'Either phone number or email is required'
            ], 422);
        }

        try {
            $contact = $request->phone_number ?? $request->email_id;
            $type = $request->phone_number ? 'phone' : 'email';
            $ipAddress = $this->getClientIp($request);

            // Check if user exists (optional - for registration flow)
            $user = User::where('phone_number', $contact)
                ->orWhere('email_id', $contact)
                ->first();

            $result = $this->otpService->sendOtp(
                $contact,
                $type,
                $user ? $user->user_id : null,
                $ipAddress,
                false // is_resend = false for initial send
            );

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Send OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact' => 'required|string',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Log::info('🔐 Verify OTP Request', [
                'contact' => $request->contact,
                'otp_code' => $request->otp_code,
            ]);

            // ✅ Call the OTP service to verify
            $result = $this->otpService->verifyOtp(
                $request->contact,
                $request->otp_code
            );

            Log::info('📊 Verify OTP Result', $result);

            // ✅ Return appropriate HTTP status code
            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('❌ Verify OTP Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP. Please try again.'
            ], 500);
        }
    }


    /**
     * ✅ FIXED: Resend OTP with attempt validation
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string|max:15',
            'email_id' => 'nullable|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure at least one contact method is provided
        if (!$request->phone_number && !$request->email_id) {
            return response()->json([
                'success' => false,
                'message' => 'Either phone number or email is required'
            ], 422);
        }

        try {
            $contact = $request->phone_number ?? $request->email_id;
            $type = $request->phone_number ? 'phone' : 'email';
            $ipAddress = $this->getClientIp($request);

            // Check if user exists (optional - for registration flow)
            $user = User::where('phone_number', $contact)
                ->orWhere('email_id', $contact)
                ->first();

            // ✅ Call sendOtp with is_resend = true
            // This will check for max attempts before sending
            $result = $this->otpService->sendOtp(
                $contact,
                $type,
                $user ? $user->user_id : null,
                $ipAddress,
                true // is_resend = true
            );

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Resend OTP Error: ' . $e->getMessage());

            // ✅ Return proper error response
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 429); // 429 Too Many Requests for rate limiting
        }
    }

    /**
     * Get real client IP address
     */
    private function getClientIp($request)
    {
        // Check for proxy/load balancer headers
        if ($request->header('X-Forwarded-For')) {
            $ips = explode(',', $request->header('X-Forwarded-For'));
            return trim($ips[0]);
        }

        if ($request->header('X-Real-IP')) {
            return $request->header('X-Real-IP');
        }

        if ($request->header('CF-Connecting-IP')) {
            return $request->header('CF-Connecting-IP');
        }

        return $request->ip();
    }


    public function logout(Request $request)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 400);
            }

            // Delete from sessions table
            Session::where('session_token', $token)->delete();

            // Invalidate JWT
            JWTAuth::setToken($token)->invalidate();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function listUsers()
    {
        $users = User::select('user_id', 'first_name', 'last_name', 'username', 'profile_photo')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function me(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->formatUser($user)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function userProfile(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // ✅ Return ALL fields from database
            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'email_id' => $user->email_id,
                    'phone_number' => $user->phone_number,
                    'gender' => $user->gender,
                    'bio' => $user->bio,
                    'user_interest' => $user->user_interest,
                    'location' => $user->location,
                    'website' => $user->website,
                    'occupation' => $user->occupation,
                    'profile_photo' => $user->profile_photo,
                    'birthdate' => $user->birthdate ? $user->birthdate->format('Y-m-d') : null,
                    'profile_visibility' => $user->profile_visibility,
                    'has_business_profile' => $user->has_business_profile,
                    'is_active' => $user->is_active,

                    // ✅ Privacy settings
                    'show_email_publicly' => $user->show_email_publicly,
                    'show_phone_number' => $user->show_phone_number,
                    'show_location' => $user->show_location,
                    'show_website' => $user->show_website,
                    'show_occupation' => $user->show_occupation,
                    'show_birth_date' => $user->show_birth_date,
                    'allow_direct_messages' => $user->allow_direct_messages,
                    'show_online_status' => $user->show_online_status,
                    'share_location' => $user->share_location,
                    'auto_update_location' => $user->auto_update_location,

                    'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                    'updated_at' => $user->updated_at ? $user->updated_at->toISOString() : null,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to load profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function updateProfile(Request $request)
    {
        try {
            // ✅ USE JWT AUTHENTICATION
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Validate incoming data
            $validator = Validator::make($request->all(), [
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'username' => 'nullable|string|max:255|unique:users,username,' . $user->user_id . ',user_id',
                'email_id' => 'nullable|email|max:255|unique:users,email_id,' . $user->user_id . ',user_id',
                'phone_number' => 'nullable|string|max:20',
                'bio' => 'nullable|string|max:500',
                'location' => 'nullable|string|max:255',
                'website' => 'nullable|string|max:255',
                'occupation' => 'nullable|string|max:255',
                'birthdate' => 'nullable|date',
                'profile_photo' => 'nullable|string|url', // 5MB max
                'profile_visibility' => 'nullable|in:public,friends,private',
                'show_email_publicly' => 'nullable|boolean',
                'show_phone_number' => 'nullable|boolean',
                'show_location' => 'nullable|boolean',
                'show_website' => 'nullable|boolean',
                'show_occupation' => 'nullable|boolean',
                'show_birth_date' => 'nullable|boolean',
                'show_online_status' => 'nullable|boolean',
                'share_location' => 'nullable|boolean',
                'auto_update_location' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except(['profile_photo']);

            // ✅ Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                // Delete old photo if exists
                if ($user->profile_photo) {
                    $oldPhotoPath = str_replace(
                        url('storage/'),
                        'public/',
                        $user->profile_photo
                    );
                    if (Storage::exists($oldPhotoPath)) {
                        Storage::delete($oldPhotoPath);
                    }
                }

                $file = $request->file('profile_photo');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('public/profiles', $filename);

                // Store full URL
                $data['profile_photo'] = url('storage/profiles/' . $filename);
            }

            // Convert boolean strings to actual booleans
            $booleanFields = [
                'show_email_publicly',
                'show_phone_number',
                'show_location',
                'show_website',
                'show_occupation',
                'show_birth_date',
                'show_online_status',
                'share_location',
                'auto_update_location',
            ];

            foreach ($booleanFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            // Update user
            $user->fill($data);
            $user->save();

            // Refresh user data
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid or expired',
                'error' => $e->getMessage()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function sendPhoneOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('phone_number', $request->phone_number)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number not found'
                ], 404);
            }

            $otp = $this->generateOTP();
            $user->phone_otp = $otp;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                'debug_otp' => config('app.debug') ? $otp : null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyPhoneOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|max:15',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('phone_number', $request->phone_number)
                ->where('phone_otp', $request->otp)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            $user->phone_otp = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Phone verified successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendEmailOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_id' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email_id', $request->email_id)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found'
                ], 404);
            }

            $otp = $this->generateOTP();
            $user->email_otp = $otp;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                'debug_otp' => config('app.debug') ? $otp : null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyEmailOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_id' => 'required|string|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email_id', $request->email_id)
                ->where('email_otp', $request->otp)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            $user->email_otp = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateOTP($length = 6)
    {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function generateRefreshToken($user)
    {
        $payload = [
            'user_id' => $user->user_id,
            'type' => 'refresh',
            'exp' => Carbon::now()->addDays(7)->timestamp,
        ];

        return JWTAuth::customClaims($payload)->fromUser($user);
    }

    private function createSession($user, $accessToken, $refreshToken, $request)
    {
        return Session::create([
            'user_id' => $user->user_id,
            'session_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'ip_address' => $request->ip(),
            'location' => null,
            'create_time' => now(),
            'expire_time' => Carbon::now()->addDays(7),
        ]);
    }

    private function formatUser($user)
    {
        return [
            'user_id' => $user->user_id,
            'phone_number' => $user->phone_number,
            'email_id' => $user->email_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'birthdate' => $user->birthdate ? $user->birthdate->format('Y-m-d') : null,
            'gender' => $user->gender,
            'user_interest' => $user->user_interest,
            'profile_photo' => $user->profile_photo,
            'has_business_profile' => $user->has_business_profile,
            'is_active' => $user->is_active,
            'phone_verified' => $user->hasVerifiedPhone(),
            'email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at->toISOString(),
        ];
    }
}
