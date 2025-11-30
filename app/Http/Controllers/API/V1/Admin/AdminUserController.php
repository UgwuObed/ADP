<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\AirtimeSale;
use App\Models\DataSale;
use App\Models\StockPurchase;
use Illuminate\Http\JsonResponse;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Get all users 
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('role')->withCount(['airtimeSales', 'dataSales', 'stocks']);

        if ($request->has('role')) {
            $query->whereHas('role', fn($q) => $q->where('name', $request->role));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'users' => UserResource::collection($users),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get single user details
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['role', 'wallet', 'stocks'])->findOrFail($id);

        // Get user stats
        $stats = [
            'total_airtime_sales' => AirtimeSale::where('user_id', $id)->where('status', 'success')->sum('amount'),
            'total_data_sales' => DataSale::where('user_id', $id)->where('status', 'success')->sum('amount'),
            'total_stock_purchased' => StockPurchase::where('user_id', $id)->sum('amount'),
            'total_transactions' => AirtimeSale::where('user_id', $id)->count() + DataSale::where('user_id', $id)->count(),
        ];

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'stats' => $stats,
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $oldValues = $user->only(['full_name', 'phone', 'email']);

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $id,
            'email' => 'sometimes|email|unique:users,email,' . $id,
        ]);

        $user->update($validated);

        AuditLogService::logUserUpdated(
            $request->user(), 
            $user, 
            $oldValues,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Activate user
     */
    public function activate(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        AuditLogService::logUserStatusChanged(auth()->user(), $user, true);

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Deactivate user
     */
    public function deactivate(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);

        AuditLogService::logUserStatusChanged(auth()->user(), $user, false);

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Delete user
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin account',
            ], 422);
        }

        AuditLogService::logUserDeleted(auth()->user(), $user);

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get user's transactions
     */
    public function transactions(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $airtimeSales = AirtimeSale::where('user_id', $id)
            ->latest()
            ->take(50)
            ->get();

        $dataSales = DataSale::where('user_id', $id)
            ->latest()
            ->take(50)
            ->get();

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'airtime_sales' => $airtimeSales,
            'data_sales' => $dataSales,
        ]);
    }

    /**
     * Get user's stock balances
     */
    public function stock(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $stocks = $user->stocks;

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'stocks' => $stocks->map(fn($stock) => [
                'network' => strtoupper($stock->network),
                'type' => $stock->type,
                'balance' => (float) $stock->balance,
                'total_purchased' => (float) $stock->total_purchased,
                'total_sold' => (float) $stock->total_sold,
            ]),
        ]);
    }

    /**
     * Get user's wallet
     */
    public function wallet(int $id): JsonResponse
    {
        $user = User::with('wallet')->findOrFail($id);

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'wallet' => $user->wallet,
        ]);
    }

    /**
     * Create admin user 
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,manager',
        ]);

        $roleId = \App\Models\Role::where('name', $validated['role'])->first()->id;

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => bcrypt($validated['password']),
            'role_id' => $roleId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        AuditLogService::logAdminCreated(auth()->user(), $user);

        return response()->json([
            'success' => true,
            'message' => 'Admin user created successfully',
            'user' => new UserResource($user),
        ], 201);
    }
}