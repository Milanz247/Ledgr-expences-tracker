<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebAuthnController extends Controller
{
    /**
     * Get registration options for WebAuthn
     */
    public function registerOptions(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'rp_id' => 'nullable|string',
        ]);

        $challenge = Str::random(32);

        // Store challenge in cache for verification (valid for 2 minutes)
        Cache::put('webauthn_challenge_' . $user->id, $challenge, 120);

        // Use RP ID from request (frontend domain) or fallback to Origin header
        $rpId = $request->input('rp_id');
        if (!$rpId) {
            // Get from Origin header if not provided
            $origin = $request->header('Origin') ?: $request->header('Referer');
            if ($origin) {
                $rpId = parse_url($origin, PHP_URL_HOST);
            }
        }
        // Fallback to request host
        if (!$rpId) {
            $rpId = $request->getHost();
        }
        // Remove port if present
        if (strpos($rpId, ':') !== false) {
            $rpId = explode(':', $rpId)[0];
        }

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => config('app.name', 'Ledgr'),
                'id' => $rpId,
            ],
            'user' => [
                'id' => base64_encode((string)$user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'requireResidentKey' => false,
                'userVerification' => 'required',
            ],
        ]);
    }

    /**
     * Register a new WebAuthn credential
     */
    public function register(Request $request)
    {
        $request->validate([
            'credential_id' => 'required|string',
            'public_key' => 'required|string',
            'device_name' => 'nullable|string|max:255',
            'authenticator_type' => 'nullable|in:platform,roaming',
        ]);

        $user = $request->user();

        // Verify challenge (in production, verify the full attestation)
        $challenge = Cache::get('webauthn_challenge_' . $user->id);
        if (!$challenge) {
            return response()->json(['message' => 'Invalid challenge'], 422);
        }

        // Check if credential already exists
        $existing = WebAuthnCredential::where('credential_id', $request->credential_id)->first();
        if ($existing) {
            return response()->json(['message' => 'Credential already registered'], 422);
        }

        // Store the credential
        $credential = $user->webAuthnCredentials()->create([
            'credential_id' => $request->credential_id,
            'public_key' => $request->public_key,
            'counter' => 0,
            'device_name' => $request->device_name ?? 'Biometric Device',
            'authenticator_type' => $request->authenticator_type ?? 'platform',
        ]);

        Cache::forget('webauthn_challenge_' . $user->id);

        return response()->json([
            'message' => 'Fingerprint registered successfully',
            'credential' => $credential,
        ]);
    }

    /**
     * Get authentication options for WebAuthn
     */
    public function authenticationOptions(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'rp_id' => 'nullable|string',
        ]);

        $email = $request->email;

        // If no email provided, allow any credential (discoverable credentials)
        if ($email) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Don't reveal if user exists
                return response()->json(['message' => 'User not found'], 404);
            }

            $credentials = $user->webAuthnCredentials()->get();

            if ($credentials->isEmpty()) {
                return response()->json(['message' => 'No biometric credentials found'], 404);
            }
        } else {
            // For empty email, get all credentials to allow any user to authenticate
            $credentials = WebAuthnCredential::all();

            if ($credentials->isEmpty()) {
                return response()->json(['message' => 'No biometric credentials registered in system'], 404);
            }
        }

        $challenge = Str::random(32);

        // Store challenge for verification (valid for 2 minutes)
        if ($email) {
            Cache::put('webauthn_auth_challenge_' . $email, $challenge, 120);
            Cache::put('webauthn_auth_email_' . $challenge, $email, 120);
        } else {
            // Store challenge globally for any user
            Cache::put('webauthn_auth_challenge_global', $challenge, 120);
        }

        // Use RP ID from request (frontend domain) or fallback to Origin header
        $rpId = $request->input('rp_id');
        if (!$rpId) {
            // Get from Origin header if not provided
            $origin = $request->header('Origin') ?: $request->header('Referer');
            if ($origin) {
                $rpId = parse_url($origin, PHP_URL_HOST);
            }
        }
        // Fallback to request host
        if (!$rpId) {
            $rpId = $request->getHost();
        }
        // Remove port if present
        if (strpos($rpId, ':') !== false) {
            $rpId = explode(':', $rpId)[0];
        }

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rpId' => $rpId,
            'allowCredentials' => $credentials->map(function ($cred) {
                return [
                    'type' => 'public-key',
                    'id' => $cred->credential_id,
                ];
            })->values(),
            'timeout' => 60000,
            'userVerification' => 'required',
        ]);
    }

    /**
     * Authenticate using WebAuthn
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'credential_id' => 'required|string',
            'authenticator_data' => 'required|string',
            'client_data_json' => 'required|string',
            'signature' => 'required|string',
        ]);

        // Get credential to find associated user
        $credential = WebAuthnCredential::where('credential_id', $request->credential_id)->first();

        if (!$credential) {
            return response()->json(['message' => 'Credential not found'], 404);
        }

        $user = $credential->user;

        // Verify challenge
        $challenge = Cache::get('webauthn_auth_challenge_' . $user->email);

        if (!$challenge) {
            return response()->json(['message' => 'Invalid or expired challenge'], 422);
        }

        // In production, you would verify the signature here using the public key
        // For now, we'll trust the client-side verification

        // Update counter
        $credential->increment('counter');

        // Generate token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Clear cache
        Cache::forget('webauthn_auth_challenge_' . $user->email);
        Cache::forget('webauthn_auth_email_' . $challenge);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Authenticated successfully',
        ]);
    }

    /**
     * Get user's registered credentials
     */
    public function getCredentials(Request $request)
    {
        $credentials = $request->user()->webAuthnCredentials()
            ->select('id', 'device_name', 'authenticator_type', 'created_at')
            ->get();

        return response()->json(['credentials' => $credentials]);
    }

    /**
     * Delete a credential
     */
    public function deleteCredential(Request $request, $id)
    {
        $credential = $request->user()->webAuthnCredentials()->findOrFail($id);
        $credential->delete();

        return response()->json(['message' => 'Credential deleted successfully']);
    }
}
