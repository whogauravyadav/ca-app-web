<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ReadingProgress;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans()
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function current(Request $request)
    {
        $user = $request->user();
        $active = $user->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $user->isAdFree() ? 'active' : 'free',
                'is_ad_free' => $user->isAdFree(),
                'expires_at' => $user->subscription_expires_at?->toIso8601String(),
                'subscription' => $active,
                'user' => $user->toApiArray(),
            ],
        ]);
    }

    /**
     * Phase 1 stub — activates subscription without real payment.
     * Phase 2 will verify Razorpay / Play Billing before calling this.
     */
    public function activate(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_ref' => 'nullable|string|max:191',
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $user = $request->user();

        $starts = now();
        $ends = now()->addDays($plan->duration_days);

        // Extend if already active
        if ($user->isAdFree() && $user->subscription_expires_at) {
            $starts = $user->subscription_expires_at;
            $ends = $user->subscription_expires_at->copy()->addDays($plan->duration_days);
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'payment_provider' => 'manual',
            'payment_ref' => $data['payment_ref'] ?? 'test-'.uniqid(),
        ]);

        $user->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => $ends,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated',
            'data' => [
                'subscription' => $subscription->load('plan'),
                'user' => $user->fresh()->toApiArray(),
            ],
        ]);
    }

    public function saveProgress(Request $request)
    {
        $data = $request->validate([
            'article_id' => 'required|exists:articles,id',
            'progress_pct' => 'required|integer|min:0|max:100',
        ]);

        $progress = ReadingProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'article_id' => $data['article_id'],
            ],
            [
                'progress_pct' => $data['progress_pct'],
                'last_read_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'data' => $progress]);
    }

    public function appConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'admob_banner_id' => AppSetting::getValue('admob_banner_id', 'ca-app-pub-3940256099942544/6300978111'),
                'admob_native_id' => AppSetting::getValue('admob_native_id', 'ca-app-pub-3940256099942544/2247696110'),
                'admob_interstitial_id' => AppSetting::getValue('admob_interstitial_id', 'ca-app-pub-3940256099942544/1033173712'),
                'min_app_version' => AppSetting::getValue('min_app_version', '1.0.0'),
                'force_update' => AppSetting::getValue('force_update', '0') === '1',
            ],
        ]);
    }
}
