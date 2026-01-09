<?php

namespace App\Http\Middleware;

use App\Models\RestaurantOwner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestaurantOwnerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user instanceof RestaurantOwner)) {
            return response()->json([
                'message' => 'Unauthorized. Restaurant owner access required.',
            ], 401);
        }

        return $next($request);
    }
}
