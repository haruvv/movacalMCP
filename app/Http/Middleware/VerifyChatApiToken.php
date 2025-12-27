<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * チャットAPIへのアクセスをBearerトークンで検証するミドルウェア。
 * 軽い保護用
 */
class VerifyChatApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.chat.api_token');

        if ($expectedToken === '') {
            return response()->json([
                'error' => 'Chat API token is not configured',
            ], 500);
        }

        $providedToken = (string) $request->bearerToken();

        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
