<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        // Generate verification token and send email (except for admin users)
        if ($user->role !== 'admin') {
            $verificationToken = $user->generateEmailVerificationToken();
            $user->notify(new VerifyEmailNotification($verificationToken));
        } else {
            // Admin users are automatically verified
            $user->markEmailAsVerified();
        }

        // Don't create token until email is verified (except for admin)
        $token = null;
        if ($user->role === 'admin' || $user->hasVerifiedEmail()) {
            $token = $user->createToken('auth_token')->plainTextToken;
        }

        $message = $user->role === 'admin' ? 
            'Admin account created successfully' : 
            'User registered successfully. Please check your email to verify your account.';

        $responseData = [
            'message' => $message,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at,
            ],
            'requires_verification' => !$user->hasVerifiedEmail(),
        ];

        if ($token) {
            $responseData['token'] = $token;
        }

        return response()->json($responseData, Response::HTTP_CREATED);
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Refresh user from database to get latest verification status
        $user->refresh();

        // Check if email is verified (except for admin users)
        if ($user->role !== 'admin' && !$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'requires_verification' => true,
                'user_id' => $user->id,
            ], Response::HTTP_FORBIDDEN);
        }

        // Update last login time
        $user->update(['last_login_at' => now()]);

        // Revoke all existing tokens for this user
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'last_login_at' => $user->last_login_at,
            ],
            'token' => $token,
        ], Response::HTTP_OK);
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:users,id',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid verification link',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::find($request->id);

        if (!$user || $user->email_verification_token !== $request->token) {
            return response()->json([
                'message' => 'Invalid or expired verification link',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($user->hasVerifiedEmail()) {
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'message' => 'Email already verified',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'email_verified' => true,
                ],
                'token' => $token,
            ], Response::HTTP_OK);
        }

        $user->markEmailAsVerified();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully! You can now login.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified' => true,
            ],
            'token' => $token,
        ], Response::HTTP_OK);
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts do not require verification',
            ], Response::HTTP_BAD_REQUEST);
        }

        $verificationToken = $user->generateEmailVerificationToken();
        $user->notify(new VerifyEmailNotification($verificationToken));

        return response()->json([
            'message' => 'Verification email sent successfully',
        ], Response::HTTP_OK);
    }
}
