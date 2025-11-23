<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NetworkResource;
use App\Http\Resources\DataPlanResource;
use App\Http\Resources\ElectricityProviderResource;
use App\Models\Network;
use App\Models\DataPlan;
use App\Models\ElectricityProvider;
use App\Models\CommissionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkController extends Controller
{
    /**
     * Get all networks for airtime
     */
    public function networks(): JsonResponse
    {
        $networks = Network::active()->airtimeEnabled()->get();

        return response()->json([
            'success' => true,
            'networks' => NetworkResource::collection($networks),
        ]);
    }

    /**
     * Get networks with data plans
     */
    public function dataNetworks(): JsonResponse
    {
        $networks = Network::active()
            ->dataEnabled()
            ->with(['dataPlans' => fn($q) => $q->active()->orderBy('sort_order')->orderBy('amount')])
            ->get();

        return response()->json([
            'success' => true,
            'networks' => $networks->map(fn($n) => [
                'id' => $n->id,
                'name' => $n->name,
                'code' => $n->code,
                'logo' => $n->logo,
                'plans' => DataPlanResource::collection($n->dataPlans),
            ]),
        ]);
    }

    /**
     * Get data plans for a network
     */
    public function dataPlans(string $networkCode): JsonResponse
    {
        $network = Network::where('code', $networkCode)->active()->firstOrFail();
        
        $plans = $network->dataPlans()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('amount')
            ->get();

        return response()->json([
            'success' => true,
            'network' => new NetworkResource($network),
            'plans' => DataPlanResource::collection($plans),
        ]);
    }

    /**
     * Get electricity providers
     */
    public function electricityProviders(): JsonResponse
    {
        $providers = ElectricityProvider::active()->get();

        return response()->json([
            'success' => true,
            'providers' => ElectricityProviderResource::collection($providers),
        ]);
    }

    /**
     * Get pricing info for logged in distributor
     */
    public function pricing(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get airtime discounts per network
        $networks = Network::active()->airtimeEnabled()->get();
        $airtimePricing = $networks->map(function ($network) use ($user) {
            $discount = CommissionSetting::getDiscount('airtime', $network->code);
            
            // Check custom pricing
            $custom = $user->distributorPricing()
                ->where('product_type', 'airtime')
                ->where('network', $network->code)
                ->first();
            
            return [
                'network' => $network->code,
                'network_name' => $network->name,
                'discount_percent' => $custom?->discount_percent ?? $discount,
                'example' => [
                    'face_value' => 1000,
                    'you_pay' => 1000 - (1000 * ($custom?->discount_percent ?? $discount) / 100),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'airtime_pricing' => $airtimePricing,
            'note' => 'You pay the discounted price and keep the difference as profit',
        ]);
    }
}