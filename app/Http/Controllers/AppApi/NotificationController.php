<?php

namespace App\Http\Controllers\AppApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notification centre (contracts/app-api.md, FR-040). Users only ever see their own.
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function unreadCount(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['count' => $user->unreadNotifications()->count()]);
    }

    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $page = $user->notifications()->simplePaginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? null,
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? '/',
                'readAt' => $notification->read_at?->toIso8601String(),
                'createdAt' => $notification->created_at?->toIso8601String(),
            ]),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    public function markRead(#[CurrentUser] User $user, string $notification): Response
    {
        $user->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->noContent();
    }
}
