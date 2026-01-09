<?php

namespace App\Http\Middleware;

use App\Models\HotelOwner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HotelOwnerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user instanceof HotelOwner)) {
            return response()->json([
                'message' => 'Unauthorized. Hotel owner access required.',
            ], 401);
        }

        return $next($request);
    }
}
