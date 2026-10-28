<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin() || $user->isApproved()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your account is pending HR approval. Full portal access is currently restricted.',
            ], 403);
        }

        return redirect()->route('admin.dashboard')->with('warning', 'Your account is pending HR approval. Full portal access is currently restricted.');
    }
}
