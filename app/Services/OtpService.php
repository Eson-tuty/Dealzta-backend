<?php

namespace App\Services;

use App\Models\LoginOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Generate a 6-digit OTP
     */
    public function generateOtp()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * ✅ FIXED: Create and send OTP with attempt lockout check
     */
    public function sendOtp($contact, $type, $userId = null, $ipAddress = null, $isResend = false)
    {
        try {
            Log::info("📧 Starting OTP send process", [
                'contact' => $contact,
                'type' => $type,
                'user_id' => $userId,
                'is_resend' => $isResend
            ]);

            // ✅ Check for existing OTP with max attempts
            $existingOtp = LoginOtp::where('contact', $contact)
                ->where('is_verified', false)
                ->latest()
                ->first();

            if ($existingOtp) {
                // ✅ Check if max attempts exceeded and still in lockout period
                if ($existingOtp->isMaxAttemptsExceeded()) {
                    // Check if 24 hours have passed
                    if (!$existingOtp->shouldResetAttempts()) {
                        $timeUntilReset = $existingOtp->getTimeUntilReset();
                        
                        Log::warning("🚫 Cannot send OTP - max attempts exceeded", [
                            'contact' => $contact,
                            'attempt_count' => $existingOtp->attempt_count,
                            'time_until_reset' => $timeUntilReset
                        ]);

                        throw new \Exception(
                            "Maximum verification attempts exceeded. You can try again in {$timeUntilReset}"
                        );
                    } else {
                        // ✅ 24 hours passed - reset attempts and continue
                        Log::info("🔄 24 hours passed, resetting attempts before new OTP");
                        $existingOtp->resetAttempts();
                    }
                }
            }

            // ✅ Delete old unverified OTPs (now safe to delete)
            $deletedCount = LoginOtp::where('contact', $contact)
                ->where('is_verified', false)
                ->delete();

            Log::info("🗑️ Deleted {$deletedCount} old unverified OTPs");

            // Generate new OTP
            $otpCode = $this->generateOtp();

            Log::info("🔢 Generated OTP", [
                'otp' => $otpCode,
                'type' => gettype($otpCode),
                'length' => strlen($otpCode)
            ]);

            // Create OTP record
            $otp = LoginOtp::create([
                'user_id' => $userId,
                'contact' => $contact,
                'otp_code' => $otpCode,
                'otp_type' => $type,
                'expires_at' => Carbon::now()->addMinutes(5),
                'ip_address' => $ipAddress,
                'attempt_count' => 0,
                'max_attempts' => 3,
                'attempts_started_at' => null, // Will be set on first failed attempt
            ]);

            $otp->refresh();
            
            Log::info("✅ OTP record created", [
                'otp_id' => $otp->id,
                'stored_otp' => $otp->otp_code,
                'expires_at' => $otp->expires_at->toISOString()
            ]);

            // Send OTP based on type
            if ($type === 'email') {
                $this->sendEmailOtp($contact, $otpCode);
            } else {
                $this->sendSmsOtp($contact, $otpCode);
            }

            return [
                'success' => true,
                'message' => 'OTP sent successfully',
                'otp_id' => $otp->id,
                'expires_at' => $otp->expires_at->toISOString(),
                'debug_otp' => config('app.debug') ? $otpCode : null,
            ];

        } catch (\Exception $e) {
            Log::error('❌ OTP Send Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e; // ✅ Re-throw to preserve the error message
        }
    }

    /**
     * Send OTP via Email (SMTP)
     */
    private function sendEmailOtp($email, $otpCode)
    {
        try {
            Log::info("📨 Attempting to send email OTP to: {$email}");

            if (!view()->exists('emails.otp')) {
                Log::error("❌ Email template not found");
                throw new \Exception('Email template not found');
            }

            try {
                Mail::send('emails.otp', ['otp' => $otpCode], function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Your Dealzta Verification Code')
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });

                Log::info("✅ Email OTP sent successfully to: {$email}");
                
            } catch (\Exception $mailException) {
                Log::error("❌ Mail sending failed: " . $mailException->getMessage());
                throw new \Exception('Failed to send email: ' . $mailException->getMessage());
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Email OTP Error: " . $e->getMessage());
            throw new \Exception('Failed to send email OTP: ' . $e->getMessage());
        }
    }

    /**
     * Send OTP via SMS (2Factor.in)
     */
    private function sendSmsOtp($phone, $otpCode)
    {
        try {
            Log::info("📱 Attempting to send SMS OTP to: {$phone}");

            $apiKey = config('services.twofactor.api_key');
            
            if (!$apiKey) {
                Log::error("❌ 2Factor API key not configured");
                throw new \Exception('2Factor API key not configured');
            }

            $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
            Log::info("📞 Cleaned phone number: {$cleanPhone}");

            $url = "https://2factor.in/API/V1/{$apiKey}/SMS/{$cleanPhone}/{$otpCode}/DEALZTA";
            Log::info("🌐 Calling 2Factor API");

            $response = Http::timeout(10)->get($url);
            $result = $response->json();

            Log::info("📡 2Factor API Response", ['response' => $result]);

            if ($response->successful() && isset($result['Status']) && $result['Status'] === 'Success') {
                Log::info("✅ SMS OTP sent successfully to: {$phone}");
            } else {
                Log::error("❌ 2Factor Error: " . json_encode($result));
                throw new \Exception('Failed to send SMS OTP: ' . ($result['Details'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            Log::error("❌ SMS OTP Error: " . $e->getMessage());
            throw new \Exception('Failed to send SMS OTP: ' . $e->getMessage());
        }
    }

    /**
     * ✅ UPDATED: Verify OTP with 24-hour attempt reset logic
     */
    public function verifyOtp($contact, $otpCode)
    {
        try {
            Log::info("🔐 ===== VERIFY OTP START =====");
            Log::info("📥 Input received", [
                'contact' => $contact,
                'otp_code' => $otpCode,
            ]);

            // Find the latest unverified OTP
            $otp = LoginOtp::where('contact', $contact)
                ->where('is_verified', false)
                ->latest()
                ->first();

            // Check if OTP record exists
            if (!$otp) {
                Log::warning("⚠️ No OTP record found", ['contact' => $contact]);
                return [
                    'success' => false,
                    'message' => 'No OTP found. Please request a new one.',
                ];
            }

            Log::info("✅ OTP record found", [
                'otp_id' => $otp->id,
                'stored_otp' => $otp->otp_code,
                'attempt_count' => $otp->attempt_count,
                'attempts_started_at' => $otp->attempts_started_at,
            ]);

            // ✅ Check if 24 hours passed and reset attempts if needed
            if ($otp->shouldResetAttempts()) {
                Log::info("🔄 24 hours passed, resetting attempts");
                $otp->resetAttempts();
                $otp->refresh();
            }

            // ✅ Check if OTP code matches (BEFORE checking attempts)
            if ($otp->otp_code !== $otpCode) {
                Log::warning("❌ OTP CODE MISMATCH", [
                    'expected' => $otp->otp_code,
                    'received' => $otpCode,
                ]);

                // ✅ Check max attempts BEFORE incrementing
                if ($otp->isMaxAttemptsExceeded()) {
                    $timeUntilReset = $otp->getTimeUntilReset();
                    Log::warning("🚫 Max attempts already exceeded");
                    
                    return [
                        'success' => false,
                        'message' => 'Maximum verification attempts exceeded. Please try again after 24 hours.',
                        'attempts_remaining' => 0,
                        'time_until_reset' => $timeUntilReset,
                    ];
                }

                // ✅ Increment failed attempt
                $otp->incrementAttempts();
                $otp->refresh();
                
                $attemptsRemaining = $otp->max_attempts - $otp->attempt_count;
                
                Log::info("⚠️ Failed attempt recorded", [
                    'new_attempt_count' => $otp->attempt_count,
                    'remaining_attempts' => $attemptsRemaining,
                    'time_until_reset' => $otp->getTimeUntilReset()
                ]);

                // Check if this was the last attempt
                if ($attemptsRemaining === 0) {
                    $timeUntilReset = $otp->getTimeUntilReset();
                    return [
                        'success' => false,
                        'message' => 'Maximum verification attempts exceeded. You can try again after 24 hours.',
                        'attempts_remaining' => 0,
                        'time_until_reset' => $timeUntilReset,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Invalid OTP code. Please check and try again.',
                    'attempts_remaining' => $attemptsRemaining,
                ];
            }

            Log::info("✅ OTP codes match!");

            // Check if expired
            if ($otp->isExpired()) {
                Log::warning("⏰ OTP expired");
                return [
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.',
                ];
            }

            // ✅ Mark as verified
            $otp->markAsVerified();
            $otp->refresh();

            Log::info("✅ ===== OTP VERIFIED SUCCESSFULLY =====", [
                'otp_id' => $otp->id,
                'contact' => $contact,
                'verified_at' => $otp->verified_at,
            ]);

            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'otp_id' => $otp->id,
            ];

        } catch (\Exception $e) {
            Log::error('❌ ===== OTP VERIFY ERROR =====');
            Log::error('Error message: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return [
                'success' => false,
                'message' => 'Failed to verify OTP: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Increment failed attempt (for backward compatibility)
     */
    public function incrementFailedAttempt($contact)
    {
        $otp = LoginOtp::where('contact', $contact)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if ($otp) {
            // Check if 24 hours passed
            if ($otp->shouldResetAttempts()) {
                $otp->resetAttempts();
                return;
            }

            $otp->incrementAttempts();
            Log::info("⚠️ Failed attempt incremented", [
                'contact' => $contact,
                'attempt_count' => $otp->attempt_count,
                'time_until_reset' => $otp->getTimeUntilReset()
            ]);
        }
    }
}