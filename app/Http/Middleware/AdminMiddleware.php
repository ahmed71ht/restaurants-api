<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Access denied (admin only)'
            ], 403);
        }

        return $next($request);
    }
}