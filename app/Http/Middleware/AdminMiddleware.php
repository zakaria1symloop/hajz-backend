<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Please login.',
            ], 401);
        }

        // Check if user is an Admin (using table name check for Sanctum compatibility)
        $isAdmin = $user instanceof Admin ||
                   (method_exists($user, 'getTable') && $user->getTable() === 'admins');

        if (!$isAdmin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        // Check if admin is active
        if (property_exists($user, 'is_active') || isset($user->is_active)) {
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Your account has been deactivated.',
                ], 403);
            }
        }

        return $next($request);
    }
}
