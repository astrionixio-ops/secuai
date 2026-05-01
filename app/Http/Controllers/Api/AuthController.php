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
use Illuminate\Support\Facades\Hash;
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

        event(new Registered($user)); // triggers email verification

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

        // Block login until email verified, unless feature-flagged off.
        if (!$user->hasVerifiedEmail() && config('secuai.require_email_verification', true)) {
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
        $tenants = $user->tenants()->get(['tenants.id', 'tenants.slug', 'tenants.name', 'tenants.plan']);

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
