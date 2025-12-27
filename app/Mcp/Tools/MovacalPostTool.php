<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MovacalPostTool extends Tool
{
    protected string $name = 'movacal_post';
    protected string $title = 'Movacal API POST';
    protected string $description = 'POST to an allowlisted Movacal endpoint with Basic Auth.';

    // 入力スキーマを定義
    public function schema(JsonSchema $schema): array
    {
        return [
            'endpoint' => $schema->string()
                ->description('Endpoint filename like getVersion.php, credential.php')
                ->required(),
            'payload' => $schema->object()
                ->description('POST parameters as object (merged with MOVACAL_DEFAULT_PARAMS_JSON)')
                ->nullable(),
            'as_json' => $schema->boolean()
                ->description('true: send JSON body / false: send application/x-www-form-urlencoded')
                ->default(false),
            'timeout_seconds' => $schema->integer()
                ->description('HTTP timeout seconds')
                ->default(20),
        ];
    }

    // 実行本体
    public function handle(Request $request): Response
    {
        $endpoint = (string) $request->get('endpoint');
        $payload  = $request->get('payload') ?? [];
        $asJson   = (bool) ($request->get('as_json') ?? false);
        $timeout  = (int) ($request->get('timeout_seconds') ?? 20);

        // 許可されていないエンドポイントはエラー
        if (! $this->isAllowed($endpoint)) {
            return Response::error("Endpoint is not allowed: {$endpoint}");
        }
        // ベースURLを取得
        $baseUrl = rtrim((string) config('movacal.base_url'), '/');
        if ($baseUrl === '') {
            return Response::error('MOVACAL_BASE_URL is not set.');
        }

        // 基本認証情報を取得
        $basicId = (string) config('movacal.basic.id');
        $basicPw = (string) config('movacal.basic.password');
        if ($basicId === '' || $basicPw === '') {
            return Response::error('MOVACAL_BASIC_ID / MOVACAL_BASIC_PASSWORD is not set.');
        }

        // デフォルトパラメータ（環境変数で設定）と、リクエストで渡されたpayloadをマージ
        $defaults = $this->decodeDefaultParams((string) config('movacal.default_params_json', '{}'));
        $payloadMerged = array_merge($defaults, is_array($payload) ? $payload : []);

        // リクエスト先URL
        $url = "{$baseUrl}/{$endpoint}";

        // HTTPクライアントを構築（Basic認証 + JSONレスポンス受け入れ + タイムアウト設定）
        $http = Http::withBasicAuth($basicId, $basicPw)
            ->acceptJson()
            ->timeout($timeout);

        /** @var \Illuminate\Http\Client\Response $resp */
        $resp = $asJson
            ? $http->post($url, $payloadMerged)
            : $http->asForm()->post($url, $payloadMerged);

        // ステータスコードが4xx/5xxの場合はエラーを返す
        if (! $resp->successful()) {
            return Response::error("Upstream error: {$resp->status()}");
        }

        // レスポンスのContent-Typeを確認
        $contentType = (string) $resp->header('content-type', '');

        // JSONレスポンスの場合はパースして返す
        if (str_contains($contentType, 'application/json')) {
            return Response::json($resp->json());
        }

        // JSON以外（PDF/添付ファイル等）はそのまま返す
        return Response::json([
            'content_type' => $contentType,
            'raw' => $resp->body(),
        ]);
    }

    /**
     * エンドポイントが許可リストに含まれているかチェックします
     * config/movacal.php の allowed_endpoints に定義されているもののみ許可します
     */
    private function isAllowed(string $endpoint): bool
    {
        $allowed = (array) config('movacal.allowed_endpoints', []);
        return in_array($endpoint, $allowed, true);
    }

    /**
     * JSON文字列をデコードして配列として返します
     * 無効なJSONの場合は空配列を返します
     */
    private function decodeDefaultParams(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
