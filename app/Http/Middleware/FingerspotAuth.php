<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FingerspotAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Option 1: Check IP whitelist
        $allowedIps = [
            '127.0.0.1',
            // Add your device IPs or network ranges
        ];
        
        if (!in_array($request->ip(), $allowedIps)) {
            Log::warning('Unauthorized access attempt', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // Option 2: Check API key/token
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');
        $validKey = env('FINGERSPOT_API_KEY', 'your-secret-key');
        
        if ($apiKey !== $validKey) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }
        
        return $next($request);
    }
}