<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'student',
            'subscription_status' => 'free',
        ]);

        $token = $user->createToken('Mobile App')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->toApiArray(),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->role === 'admin' || $user->role === 'editor') {
            // Admins can still use mobile for testing, but typically students login here
        }

        $this->touchStreak($user);

        $token = $user->createToken('Mobile App')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->fresh()->toApiArray(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $this->touchStreak($user);

        return response()->json([
            'success' => true,
            'user' => $user->fresh()->toApiArray(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'password' => 'sometimes|string|min:6|confirmed',
            'avatar_url' => 'sometimes|nullable|string|max:500',
        ]);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['password'])) {
            $user->password = $data['password'];
        }
        if (array_key_exists('avatar_url', $data)) {
            $user->avatar_url = $data['avatar_url'];
        }
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user->toApiArray(),
        ]);
    }

    private function touchStreak(User $user): void
    {
        $today = now()->toDateString();
        $last = $user->last_active_date?->toDateString();

        if ($last === $today) {
            return;
        }

        if ($last === now()->subDay()->toDateString()) {
            $user->streak_count = ($user->streak_count ?? 0) + 1;
        } else {
            $user->streak_count = 1;
        }

        $user->last_active_date = $today;
        $user->save();
    }
}
