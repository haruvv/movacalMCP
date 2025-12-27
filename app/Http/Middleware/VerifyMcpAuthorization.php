<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCPサーバーへのアクセスをBearerトークンで検証するミドルウェア。
 * OpenAI Responses API からのアクセスを想定。
 */
class VerifyMcpAuthorization
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.mcp.authorization');

        if ($expectedToken === '') {
            return response()->json([
                'error' => 'MCP authorization token is not configured',
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

