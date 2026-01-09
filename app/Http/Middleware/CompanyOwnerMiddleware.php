<?php

namespace App\Http\Middleware;

use App\Models\CompanyOwner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyOwnerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user instanceof CompanyOwner)) {
            return response()->json([
                'message' => 'Unauthorized. Company owner access required.',
            ], 401);
        }

        return $next($request);
    }
}
