<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $examKeys = array_keys(config('exams.options', []));

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^[6-9]\d{9}$/', 'unique:users,phone'],
            'exams' => 'required|array|min:1',
            'exams.*' => ['string', Rule::in($examKeys)],
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'exams' => array_values(array_unique($data['exams'])),
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
        $examKeys = array_keys(config('exams.options', []));

        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                'regex:/^[6-9]\d{9}$/',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'exams' => 'sometimes|array|min:1',
            'exams.*' => ['string', Rule::in($examKeys)],
            'avatar_url' => 'sometimes|nullable|string|max:500',
        ]);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        if (isset($data['exams'])) {
            $user->exams = array_values(array_unique($data['exams']));
        }
        if (array_key_exists('avatar_url', $data)) {
            $user->avatar_url = $data['avatar_url'];
        }
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user->fresh()->toApiArray(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
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
