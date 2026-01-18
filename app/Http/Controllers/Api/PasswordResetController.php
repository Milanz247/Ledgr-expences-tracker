<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    /**
     * Send OTP to user's email for password reset
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email format',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;

        // Check if user exists
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address'
            ], 404);
        }

        // Rate limiting - check if OTP was recently sent
        $rateLimitKey = 'otp_rate_limit_' . $email;
        if (Cache::has($rateLimitKey)) {
            $remainingTime = Cache::get($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another OTP',
                'retry_after' => $remainingTime
            ], 429);
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache for 10 minutes
        $otpKey = 'password_reset_otp_' . $email;
        $attemptsKey = 'otp_attempts_' . $email;
        
        Cache::put($otpKey, $otp, now()->addMinutes(10));
        Cache::put($attemptsKey, 0, now()->addMinutes(10)); // Reset attempts
        Cache::put($rateLimitKey, 60, now()->addSeconds(60)); // 60 second rate limit

        // Send email
        try {
            Mail::to($email)->send(new ResetPasswordMail($user->name, $otp));
            
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to your email',
                'expires_in' => 600 // 10 minutes in seconds
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Verify OTP and reset password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $otp = $request->otp;
        $password = $request->password;

        // Check if user exists
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request'
            ], 404);
        }

        // Check attempts to prevent brute force
        $attemptsKey = 'otp_attempts_' . $email;
        $attempts = Cache::get($attemptsKey, 0);

        if ($attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please request a new OTP.'
            ], 429);
        }

        // Verify OTP
        $otpKey = 'password_reset_otp_' . $email;
        $storedOtp = Cache::get($otpKey);

        if (!$storedOtp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.'
            ], 400);
        }

        if ($storedOtp !== $otp) {
            // Increment failed attempts
            Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(10));
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
                'attempts_remaining' => 5 - ($attempts + 1)
            ], 400);
        }

        // OTP is valid - update password
        $user->password = Hash::make($password);
        $user->save();

        // Clear cache
        Cache::forget($otpKey);
        Cache::forget($attemptsKey);
        Cache::forget('otp_rate_limit_' . $email);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.'
        ], 200);
    }

    /**
     * Resend OTP (with rate limiting)
     */
    public function resendOtp(Request $request)
    {
        return $this->forgotPassword($request);
    }
}
