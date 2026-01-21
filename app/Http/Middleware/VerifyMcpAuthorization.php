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
        // OpenAIクライアント等から来るMCP(JSON-RPC)リクエストを、原因調査しやすい形でログに残す。
        // 注意: 医療情報/トークン等が混ざり得るので、ログには「要点」＋「サニタイズ済みのリクエスト」だけ残す。
        $this->logJsonRpcRequestIfPossible($request);

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

    private function logJsonRpcRequestIfPossible(Request $request): void
    {
        $raw = (string) $request->getContent();
        $rawLength = strlen($raw);

        [$parsed, $jsonError] = $this->decodeJsonRpcBody($raw, $request->all());

        if (!is_array($parsed)) {
            if ($raw !== '' && $jsonError !== null) {
                Log::warning('MCP invalid JSON body received', [
                    'http_method' => $request->method(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'body_bytes' => $rawLength,
                    'json_error' => $jsonError,
                    'raw_preview' => $this->truncate($raw, 2000),
                ]);
            }
            return;
        }

        $method = $parsed['method'] ?? null;
        if (!is_string($method) || $method === '') {
            return;
        }

        $params = $parsed['params'] ?? null;
        $paramsKeys = is_array($params) ? array_values(array_keys($params)) : null;

        Log::info('MCP JSON-RPC request received', [
            'mcp' => [
                'method' => $method,
                'id' => $parsed['id'] ?? null,
                'jsonrpc' => $parsed['jsonrpc'] ?? null,
                'params_keys' => $paramsKeys,
                'summary' => $this->summarizeJsonRpc($method, is_array($params) ? $params : []),
                'body_bytes' => $rawLength,
            ],
            'http' => [
                'http_method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->headers->get('x-request-id'),
                'content_type' => $request->headers->get('content-type'),
                'accept' => $request->headers->get('accept'),
            ],
            // Authorization/Cookie/arguments等はサニタイズしてログ出し
            'request' => [
                'headers' => $this->headersForLog($request),
                'sanitized_json' => $this->truncate($this->sanitizeJsonRpcForLog($parsed), 8000),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $fallbackBody
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function decodeJsonRpcBody(string $raw, array $fallbackBody): array
    {
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return [$decoded, null];
            }
            return [null, json_last_error_msg()];
        }

        // JSON以外（フォーム等）の可能性。最低限JSON-RPCっぽい場合だけ拾う。
        if (isset($fallbackBody['jsonrpc'], $fallbackBody['method']) && is_string($fallbackBody['method'])) {
            /** @var array<string, mixed> $fallbackBody */
            return [$fallbackBody, null];
        }

        return [null, null];
    }

    /**
     * @return array<string, mixed>
     */
    private function headersForLog(Request $request): array
    {
        // トークンが混ざりやすいヘッダは値を残さない（存在だけ）
        return [
            'host' => $request->headers->get('host'),
            'x_forwarded_for' => $request->headers->get('x-forwarded-for'),
            'x_real_ip' => $request->headers->get('x-real-ip'),
            'cf_connecting_ip' => $request->headers->get('cf-connecting-ip'),
            'authorization_present' => $request->headers->has('authorization'),
            'cookie_present' => $request->headers->has('cookie'),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function summarizeJsonRpc(string $method, array $params): array
    {
        if ($method === 'initialize') {
            $clientInfo = $params['clientInfo'] ?? null;
            $capabilities = $params['capabilities'] ?? null;

            return [
                'protocolVersion' => $params['protocolVersion'] ?? null,
                'clientInfo' => is_array($clientInfo)
                    ? [
                        'name' => $clientInfo['name'] ?? null,
                        'version' => $clientInfo['version'] ?? null,
                    ]
                    : null,
                'capabilities_keys' => is_array($capabilities) ? array_values(array_keys($capabilities)) : null,
            ];
        }

        if ($method === 'tools/call') {
            $args = $params['arguments'] ?? null;
            $argsSummary = [];
            if (is_array($args)) {
                // movacal_get想定: operationは残し、argsはキーだけ
                if (isset($args['operation']) && is_string($args['operation'])) {
                    $argsSummary['operation'] = $args['operation'];
                }
                if (isset($args['args']) && is_array($args['args'])) {
                    $argsSummary['args_keys'] = array_values(array_keys($args['args']));
                } elseif (isset($args['args'])) {
                    $argsSummary['args_type'] = gettype($args['args']);
                }
                $argsSummary['arguments_keys'] = array_values(array_keys($args));
            }

            return [
                'tool_name' => $params['name'] ?? null,
                'arguments' => $argsSummary === [] ? null : $argsSummary,
            ];
        }

        if ($method === 'tools/list') {
            return [
                'cursor' => $params['cursor'] ?? null,
                'per_page' => $params['per_page'] ?? null,
            ];
        }

        return [
            'params_keys' => array_values(array_keys($params)),
        ];
    }

    /**
     * @param  array<string, mixed>  $jsonRpc
     */
    private function sanitizeJsonRpcForLog(array $jsonRpc): string
    {
        $method = $jsonRpc['method'] ?? null;
        $params = isset($jsonRpc['params']) && is_array($jsonRpc['params']) ? $jsonRpc['params'] : [];

        $sanitized = [
            'jsonrpc' => $jsonRpc['jsonrpc'] ?? null,
            'id' => $jsonRpc['id'] ?? null,
            'method' => $method,
            'params' => null,
        ];

        if ($method === 'initialize') {
            $capabilities = $params['capabilities'] ?? null;
            $clientInfo = $params['clientInfo'] ?? null;
            $sanitized['params'] = [
                'protocolVersion' => $params['protocolVersion'] ?? null,
                'clientInfo' => is_array($clientInfo)
                    ? [
                        'name' => $clientInfo['name'] ?? null,
                        'version' => $clientInfo['version'] ?? null,
                    ]
                    : null,
                'capabilities_keys' => is_array($capabilities) ? array_values(array_keys($capabilities)) : null,
                '_meta_present' => array_key_exists('_meta', $params),
            ];
        } elseif ($method === 'tools/call') {
            $arguments = $params['arguments'] ?? null;
            $argSummary = null;
            if (is_array($arguments)) {
                $argSummary = [
                    'keys' => array_values(array_keys($arguments)),
                ];
                if (isset($arguments['operation']) && is_string($arguments['operation'])) {
                    $argSummary['operation'] = $arguments['operation'];
                }
                if (isset($arguments['args']) && is_array($arguments['args'])) {
                    $argSummary['args_keys'] = array_values(array_keys($arguments['args']));
                }
            }

            $sanitized['params'] = [
                'name' => $params['name'] ?? null,
                'arguments' => $argSummary,
                '_meta_present' => array_key_exists('_meta', $params),
            ];
        } elseif ($method === 'tools/list') {
            $sanitized['params'] = [
                'cursor' => $params['cursor'] ?? null,
                'per_page' => $params['per_page'] ?? null,
                '_meta_present' => array_key_exists('_meta', $params),
            ];
        } else {
            $sanitized['params'] = [
                'keys' => array_values(array_keys($params)),
                '_meta_present' => array_key_exists('_meta', $params),
            ];
        }

        return (string) json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function truncate(string $value, int $maxBytes): string
    {
        if ($maxBytes <= 0) {
            return '';
        }

        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        return substr($value, 0, $maxBytes) . '...(truncated)';
    }
}

