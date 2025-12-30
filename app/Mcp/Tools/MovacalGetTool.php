<?php

namespace App\Mcp\Tools;

use App\Services\Movacal\MovacalReadRouter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Movacal API取得専用ツール（get* エンドポイント用）
 * - operationで操作を指定
 * - endpointをチャットで直接指定させない（サーバー側でマッピング）
 */
class MovacalGetTool extends Tool
{
    protected string $name = 'movacal_get';
    protected string $title = 'Movacal API GET (Read-only)';
    protected string $description = 'Fetch data from Movacal API. Specify operation name and arguments.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()
                ->enum(MovacalReadRouter::ALLOWED_OPERATIONS)
                ->description('Operation name (e.g., get_version)')
                ->required(),
            'args' => $schema->object()
                ->description('Operation arguments (optional, depends on operation)')
                ->nullable(),
        ];
    }

    public function handle(Request $request): Response
    {
        $operation = (string) $request->get('operation');
        $args = $request->get('args') ?? [];

        if (!is_array($args)) {
            $args = [];
        }

        Log::info('[MCP] Tool invoked', [
            'tool' => 'movacal_get',
            'operation' => $operation,
        ]);

        try {
            /** @var MovacalReadRouter $router */
            $router = app(MovacalReadRouter::class);

            // operation が許可されているかチェック
            if (!$router->isAllowedOperation($operation)) {
                Log::warning('[MCP] Operation not allowed', [
                    'operation' => $operation,
                ]);
                return Response::error("Unknown or disallowed operation: {$operation}. Allowed: " . implode(', ', $router->getAllowedOperations()));
            }

            $result = $router->execute($operation, $args);

            Log::info('[MCP] Tool succeeded', [
                'tool' => 'movacal_get',
                'operation' => $operation,
            ]);

            return Response::json($result);
        } catch (\Throwable $e) {
            Log::error('[MCP] Tool failed', [
                'tool' => 'movacal_get',
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
            return Response::error("Operation failed: {$e->getMessage()}");
        }
    }
}

