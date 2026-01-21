<?php

namespace App\Mcp\Servers;

use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Methods\Initialize;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use App\Mcp\Tools\MovacalGetTool;

class MovacalServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Movacal Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.1.0';

    /**
     * Supported MCP protocol versions.
     *
     * @var array<int, string>
     */
    protected array $supportedProtocolVersion = [
        '2025-06-18',
        '2025-03-26',
        '2024-11-05',
    ];

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        このMCPサーバーはモバカルAPI（医療情報システム）への**読み取り専用**アクセスを提供します。
        
        ## 安全性に関する注意
        - **書き込み操作（作成/更新/削除）は絶対に行わないでください**。このサーバーは読み取り専用です。
        - 提供されている `movacal_get` ツールのみを使用してください。
        - 返却されるデータは機密性の高い医療情報です。個人情報（PII）の不必要な繰り返しは避けてください。
        
        ## 利用可能なツール
        
        ### movacal_get（読み取り専用）
        モバカルAPIからデータを取得します。
        - operation（操作名）を指定してください
        - endpoint名を直接指定する必要はありません
        
        **パラメータ**
        - `operation` (string, required): 操作名（例: `get_version`）
        - `args` (object, optional): 操作に必要な引数
        
        **利用可能な operation**
        - `get_version`: APIバージョン情報を取得
        
        **使用上の注意**
        - どの operation を使うべきかわからない場合は、推測せずにユーザーに確認してください。
    MARKDOWN;


    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        MovacalGetTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];

    /**
     * Handle the initialize message and log the response.
     */
    protected function handleInitializeMessage(JsonRpcRequest $request, ServerContext $context): void
    {
        $response = (new Initialize)->handle($request, $context);
        $responseJson = $response->toJson();

        Log::info('MCP initialize response', [
            'mcp.method' => 'initialize',
            'mcp.request_id' => $request->id,
            'response.json' => $responseJson,
        ]);

        $this->transport->send($responseJson, $this->generateSessionId());
    }
}
