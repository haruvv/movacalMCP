<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChatController extends Controller
{
    /**
     * チャットメッセージを処理しOpenAI Responses API経由でMCPサーバーに問い合わせます
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
            'previous_response_id' => ['nullable', 'string'],
        ]);

        $openaiApiKey = (string) config('services.openai.api_key');
        if ($openaiApiKey === '') {
            return response()->json([
                'error' => 'OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.',
            ], 500);
        }

        $userMessage = (string) $validated['message'];
        $previousResponseId = isset($validated['previous_response_id'])
            ? (string) $validated['previous_response_id']
            : null;

        $requestData = $this->buildResponsesRequestData(
            userMessage: $userMessage,
            previousResponseId: $previousResponseId,
        );

        try {
            $openaiApiUrl = $this->openaiResponsesUrl();

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withToken($openaiApiKey)
                ->asJson()
                ->acceptJson()
                ->timeout(120)
                ->post($openaiApiUrl, $requestData);

            if (!$response->successful()) {
                // 個人情報が混ざり得るので body / details はログに残さない
                $err = $response->json();

                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'x_request_id' => $response->header('x-request-id'),
                    'error_type' => data_get($err, 'error.type'),
                    'error_code' => data_get($err, 'error.code'),
                ]);

                return response()->json([
                    'error' => 'Failed to get response from OpenAI API',
                ], $response->status());
            }

            $responseData = $response->json();

            try {
                $assistantMessage = $this->extractOutputTextOrFail($responseData);
            } catch (\Throwable $e) {
                // 個人情報が入らないメタ情報のみログ
                Log::warning('OpenAI response missing output text', [
                    'openai_response_id' => $responseData['id'] ?? null,
                    'model' => $responseData['model'] ?? null,
                    'output_item_types' => $this->summarizeOutputTypes($responseData),
                ]);

                return response()->json([
                    'error' => 'OpenAI returned no message text.',
                ], 502);
            }

            return response()->json([
                'message' => $assistantMessage,
                'response_id' => $responseData['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'An error occurred while processing your request',
            ], 500);
        }
    }

    /**
     * OpenAI Responses APIのURL（/v1/responses）を返します。
     */
    private function openaiResponsesUrl(): string
    {
        $base = (string) config('services.openai.api_base_url', 'https://api.openai.com/v1');
        $base = rtrim($base, '/');

        if ($base === '') {
            throw new RuntimeException('OpenAI API base URL is not configured (services.openai.api_base_url).');
        }

        return $base . '/responses';
    }

    /**
     * Responses APIのリクエストボディを組み立てます。
     *
     * @return array<string, mixed>
     */
    private function buildResponsesRequestData(string $userMessage, ?string $previousResponseId): array
    {
        $model = (string) config('services.openai.model', 'gpt-4o');
        if ($model === '') {
            throw new RuntimeException('OpenAI model is not configured (services.openai.model).');
        }

        $data = [
            'model' => $model,
            'instructions' =>
                'You are a helpful assistant that can access Movacal medical information system through MCP tools. ' .
                'When users ask about Movacal data, use the available MCP tools to retrieve the information.',
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $userMessage,
                        ],
                    ],
                ],
            ],
            'tools' => [
                $this->buildMcpToolConfig(),
            ],
        ];

        // 会話継続したい場合にクライアントから渡すID
        if ($previousResponseId !== null && $previousResponseId !== '') {
            $data['previous_response_id'] = $previousResponseId;
        }

        return $data;
    }

    /**
     * Responses API の MCP tool 設定を組み立てます。
     *
     * @return array<string, mixed>
     */
    private function buildMcpToolConfig(): array
    {
        $serverUrl = (string) config('services.mcp.server_url', '');
        if ($serverUrl === '') {
            throw new RuntimeException('MCP server URL is not configured (services.mcp.server_url).');
        }

        $authorization = config('services.mcp.authorization');
        if (!is_string($authorization) || $authorization === '') {
            throw new RuntimeException('MCP authorization is not configured (services.mcp.authorization).');
        }

        $allowedTools = config('services.mcp.allowed_tools');
        if (!is_array($allowedTools) || $allowedTools === []) {
            throw new RuntimeException('MCP allowed_tools is not configured (services.mcp.allowed_tools).');
        }

        $tool = [
            'type' => 'mcp',
            'server_label' => 'movacal',
            'server_url' => $serverUrl,
            'require_approval' => 'never',
            'authorization' => $authorization,
            'allowed_tools' => array_values($allowedTools),
        ];

        return $tool;
    }

    /**
     * Responses APIのレスポンスから「ユーザー向けの出力テキスト」だけを抽出。
     * 取れなければ例外。
     */
    private function extractOutputTextOrFail(array $responseData): string
    {
        if (isset($responseData['output_text']) && is_string($responseData['output_text'])) {
            $t = trim($responseData['output_text']);
            if ($t !== '') {
                return $t;
            }
        }

        $chunks = [];

        if (!empty($responseData['output']) && is_array($responseData['output'])) {
            foreach ($responseData['output'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (($item['type'] ?? null) === 'message'
                    && !empty($item['content'])
                    && is_array($item['content'])
                ) {
                    foreach ($item['content'] as $contentPart) {
                        if (!is_array($contentPart)) {
                            continue;
                        }

                        if (($contentPart['type'] ?? null) === 'output_text'
                            && isset($contentPart['text'])
                            && is_string($contentPart['text'])
                        ) {
                            $t = trim($contentPart['text']);
                            if ($t !== '') {
                                $chunks[] = $t;
                            }
                        }
                    }
                }
            }
        }

        if (!empty($chunks)) {
            return implode("\n", $chunks);
        }

        throw new RuntimeException('OpenAI response has no output text.');
    }

    /**
     * 個人情報を含まない形で、output 内のitem type一覧だけを返す。
     */
    private function summarizeOutputTypes(array $responseData): array
    {
        $types = [];

        if (!empty($responseData['output']) && is_array($responseData['output'])) {
            foreach ($responseData['output'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $types[] = $item['type'] ?? 'unknown';
            }
        }

        return array_values(array_unique($types));
    }
}