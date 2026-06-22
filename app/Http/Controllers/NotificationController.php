<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    public function index(): JsonResponse
    {
        $notifications = Auth::user()
            ->notifications()
            ->where(function ($query) {
                $query->whereNull('read_at')
                      ->orWhere('is_marked', true)
                      ->orWhere('created_at', '>=', now()->startOfMonth());
            })
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? '#',
                'read_at' => $n->read_at,
                'is_marked' => (bool)($n->is_marked ?? false),
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function toggleRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        if ($notification->read_at) {
            $notification->read_at = null;
        } else {
            $notification->read_at = now();
        }
        $notification->save();

        return response()->json([
            'success' => true,
            'is_read' => $notification->read_at !== null
        ]);
    }

    public function toggleMark(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->is_marked = !($notification->is_marked ?? false);
        $notification->save();

        return response()->json([
            'success' => true,
            'is_marked' => (bool)$notification->is_marked
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'action' => 'required|string|in:read,unread,mark,unmark,delete'
        ]);

        $ids = $request->ids;
        $action = $request->action;
        $user = Auth::user();

        if ($action === 'read') {
            $user->notifications()->whereIn('id', $ids)->update(['read_at' => now()]);
        } elseif ($action === 'unread') {
            $user->notifications()->whereIn('id', $ids)->update(['read_at' => null]);
        } elseif ($action === 'mark') {
            $user->notifications()->whereIn('id', $ids)->update(['is_marked' => true]);
        } elseif ($action === 'unmark') {
            $user->notifications()->whereIn('id', $ids)->update(['is_marked' => false]);
        } elseif ($action === 'delete') {
            $user->notifications()->whereIn('id', $ids)->delete();
        }

        return response()->json(['success' => true]);
    }

    public function activityLog(Request $request)
    {
        $query = Auth::user()->notifications();

        // Filter by Read/Unread/Marked
        if ($request->has('filter')) {
            if ($request->filter === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->filter === 'marked') {
                $query->where('is_marked', true);
            }
        }

        // Search in message text
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('data->message', 'like', "%{$search}%")
                  ->orWhere('data->employee_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $notifications = $query->latest()->paginate($perPage)->withQueryString();

        return view('super-admin.notifications.activity', compact('notifications'));
    }
}
