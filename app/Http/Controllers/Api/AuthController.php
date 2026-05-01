<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger)
    {
    }

    public function signup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            'name' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'in:en,es,fr,de,pt,ar,zh,sw'],
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => strtolower($data['email']),
            'password' => $data['password'], // hashed via cast
            'name' => $data['name'] ?? null,
            'locale' => $data['locale'] ?? 'en',
        ]);

        // Phase 1.1 will: dispatch the Registered event to trigger SMTP verification email.
        // For Phase 1, we don't fire Registered to avoid the auto-mailer crashing on a
        // missing 'verification.verify' route. Email verification is opt-in via env flag.
        if (config('secuai.require_email_verification', false)) {
            event(new Registered($user));
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user' => $this->presentUser($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = ['email' => strtolower($data['email']), 'password' => $data['password']];

        $token = Auth::guard('api')->attempt($credentials);
        if ($token === false) {
            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Block login until email verified, but only if the feature is on.
        // Default is OFF in Phase 1 — flip ON in Phase 1.1 once email is wired up.
        if (config('secuai.require_email_verification', false) && !$user->hasVerifiedEmail()) {
            Auth::guard('api')->logout();
            return response()->json([
                'error' => 'email_not_verified',
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        return response()->json([
            'user' => $this->presentUser($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();
        return response()->json(['ok' => true]);
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('api')->refresh();
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // IMPORTANT: don't pass a column list to ->get() on a belongsToMany
        // relationship — Laravel drops pivot data when you do, and pivot->role
        // ends up null. Just select all columns and project in the map below.
        $tenants = $user->tenants()->get();

        return response()->json([
            'user' => $this->presentUser($user),
            'tenants' => $tenants->map(fn ($t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'plan' => $t->plan,
                'role' => $t->pivot->role,
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'locale' => $user->locale,
            'avatar_url' => $user->avatar_url,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }
}
