<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionAdminController extends Controller
{
    public function plans()
    {
        return response()->json(['success' => true, 'data' => SubscriptionPlan::orderBy('sort_order')->get()]);
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:subscription_plans,slug',
            'price_inr' => 'required|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $plan = SubscriptionPlan::create($data);

        return response()->json(['success' => true, 'data' => $plan], 201);
    }

    public function updatePlan(Request $request, int $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:120|unique:subscription_plans,slug,'.$id,
            'price_inr' => 'sometimes|integer|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);
        $plan->update($data);

        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function destroyPlan(int $id)
    {
        SubscriptionPlan::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function subscribers(Request $request)
    {
        $q = User::query()->where('role', 'student')->latest();
        if ($request->get('status') === 'active') {
            $q->where('subscription_status', 'active')->where('subscription_expires_at', '>', now());
        }

        $users = $q->paginate(30);

        return response()->json([
            'success' => true,
            'data' => collect($users->items())->map(fn (User $u) => $u->toApiArray())->values(),
            'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'total' => $users->total()],
        ]);
    }

    public function grant(Request $request, int $userId)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'days' => 'nullable|integer|min:1',
        ]);

        $user = User::findOrFail($userId);
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $days = $data['days'] ?? $plan->duration_days;
        $ends = now()->addDays($days);

        if ($user->isAdFree() && $user->subscription_expires_at) {
            $ends = $user->subscription_expires_at->copy()->addDays($days);
        }

        Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $ends,
            'payment_provider' => 'manual',
            'payment_ref' => 'admin-grant-'.uniqid(),
        ]);

        $user->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => $ends,
        ]);

        return response()->json([
            'success' => true,
            'user' => $user->fresh()->toApiArray(),
        ]);
    }

    public function revoke(int $userId)
    {
        $user = User::findOrFail($userId);
        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);
        $user->update([
            'subscription_status' => 'expired',
            'subscription_expires_at' => now(),
        ]);

        return response()->json(['success' => true, 'user' => $user->fresh()->toApiArray()]);
    }
}
