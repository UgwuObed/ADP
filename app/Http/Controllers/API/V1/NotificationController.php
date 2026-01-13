<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Get all notifications
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_read', 'type', 'category', 'priority', 'per_page']);

        $notifications = $this->notificationService->getUserNotifications(
            $request->user(),
            $filters
        );

        return response()->json([
            'success' => true,
            'notifications' => NotificationResource::collection($notifications),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Get unread notifications
     */
    public function unread(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getUserNotifications(
            $request->user(),
            ['is_read' => false, 'per_page' => $request->input('per_page', 20)]
        );

        return response()->json([
            'success' => true,
            'notifications' => NotificationResource::collection($notifications),
            'unread_count' => $notifications->total(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $result = $this->notificationService->markAsRead($id, $request->user());

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marked as read",
            'count' => $count,
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $result = $this->notificationService->deleteNotification($id, $request->user());

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Get notification preferences
     */
    public function preferences(Request $request): JsonResponse
    {
        $preferences = $request->user()->notificationPreferences 
            ?? $this->notificationService->updatePreferences($request->user(), []);

        return response()->json([
            'success' => true,
            'preferences' => [
                'email_enabled' => $preferences->email_enabled,
                'push_enabled' => $preferences->push_enabled,
                'transaction_alerts' => $preferences->transaction_alerts,
                'system_updates' => $preferences->system_updates,
                'marketing' => $preferences->marketing,
                'notification_types' => $preferences->notification_types ?? [],
            ],
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'email_enabled' => 'sometimes|boolean',
            'push_enabled' => 'sometimes|boolean',
            'transaction_alerts' => 'sometimes|boolean',
            'system_updates' => 'sometimes|boolean',
            'marketing' => 'sometimes|boolean',
            'notification_types' => 'sometimes|array',
        ]);

        $preferences = $this->notificationService->updatePreferences(
            $request->user(),
            $request->only([
                'email_enabled',
                'push_enabled',
                'transaction_alerts',
                'system_updates',
                'marketing',
                'notification_types',
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated',
            'preferences' => [
                'email_enabled' => $preferences->email_enabled,
                'push_enabled' => $preferences->push_enabled,
                'transaction_alerts' => $preferences->transaction_alerts,
                'system_updates' => $preferences->system_updates,
                'marketing' => $preferences->marketing,
                'notification_types' => $preferences->notification_types ?? [],
            ],
        ]);
    }
}