<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCPサーバーへのアクセスを許可するミドルウェア。
 * 認証チェックなしでアクセスを許可します。
 */
class VerifyMcpAuthorization
{
    public function handle(Request $request, Closure $next): Response
    {
        // tools/list リクエストの生データをログに記録
        $requestBody = $request->all();
        $method = $requestBody['method'] ?? null;
        
        if ($method === 'tools/list') {
            Log::info('MCP tools/list request received', [
                'raw_request' => $request->getContent(),
                'parsed_body' => $requestBody,
                'headers' => $request->headers->all(),
            ]);
        }

        // $expectedToken = (string) config('services.mcp.authorization');

        // if ($expectedToken === '') {
        //     return response()->json([
        //         'error' => 'MCP authorization token is not configured',
        //     ], 500);
        // }

        // $providedToken = (string) $request->bearerToken();

        // if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        //     return response()->json([
        //         'error' => 'Unauthorized',
        //     ], 401);
        // }

        return $next($request);
    }
}

