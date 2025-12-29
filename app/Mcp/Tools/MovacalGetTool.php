<?php

namespace App\Mcp\Tools;

use App\Services\MovacalCredentialService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Movacal API の取得専用ツール
 * - getで始まるendpointのみ許可
 * - allowlistで許可されたendpointのみ実行
 * - credential認証を使用（401時のみrefreshして1回だけリトライ）
 */
class MovacalGetTool extends Tool
{
    protected string $name = 'movacal_get';
    protected string $title = 'Movacal API GET (Read-only)';
    protected string $description = 'Fetch data from Movacal API using credential authentication. Only get* endpoints are allowed.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'endpoint' => $schema->string()
                ->description('Endpoint filename starting with "get" (e.g., getPatient.php, getDiaglist.php)')
                ->required(),
            'params' => $schema->object()
                ->description('Request parameters as object')
                ->nullable(),
            'timeout_seconds' => $schema->integer()
                ->description('HTTP timeout seconds (min 1, max 60)')
                ->default(30),
        ];
    }

    public function handle(Request $request): Response
    {
        $rawEndpoint = (string) $request->get('endpoint');
        $params = $request->get('params') ?? [];
        $timeout = (int) ($request->get('timeout_seconds') ?? 30);

        // timeoutを軽くガード
        $timeout = max(1, min(60, $timeout));

        // endpointサニタイズ（../ や / を潰してファイル名だけにする）
        $endpoint = $this->sanitizeEndpoint($rawEndpoint);

        // getで始まるかチェック
        if (!str_starts_with($endpoint, 'get')) {
            return Response::error("Only get* endpoints are allowed. Got: {$rawEndpoint}");
        }

        // 許可リストに含まれているかチェック
        if (!$this->isAllowed($endpoint)) {
            return Response::error("Endpoint is not in allowlist: {$endpoint}");
        }

        // 設定値を取得
        $baseUrl = rtrim((string) config('movacal.base_url'), '/');
        $basicId = (string) config('movacal.basic.id');
        $basicPw = (string) config('movacal.basic.password');

        if ($baseUrl === '') {
            return Response::error('MOVACAL_BASE_URL is not configured.');
        }
        if ($basicId === '' || $basicPw === '') {
            return Response::error('MOVACAL_BASIC_ID / MOVACAL_BASIC_PASSWORD is not configured.');
        }

        // デフォルトパラメータとマージ
        $defaults = $this->decodeDefaultParams((string) config('movacal.default_params_json', '{}'));
        $paramsMerged = array_merge($defaults, is_array($params) ? $params : []);

        $url = "{$baseUrl}/{$endpoint}";

        try {
            /** @var MovacalCredentialService $credentialService */
            $credentialService = app(MovacalCredentialService::class);

            // 通常のcredentialでリクエスト
            $credential = $credentialService->getCredential();
            $res = $this->postMovacal($url, $basicId, $basicPw, $timeout, $this->withCredential($paramsMerged, $credential));

            // 401なら：credential refreshして1回だけリトライ
            if ($res->status() === 401) {
                $credential = $credentialService->refreshCredential();
                $res = $this->postMovacal($url, $basicId, $basicPw, $timeout, $this->withCredential($paramsMerged, $credential));
            }

            if (!$res->successful()) {
                return Response::error("Upstream error: HTTP {$res->status()}");
            }

            $contentType = (string) $res->header('content-type', '');

            if (str_contains($contentType, 'application/json')) {
                return Response::json($res->json());
            }

            // JSON以外はそのまま返す
            return Response::json([
                'content_type' => $contentType,
                'raw' => $res->body(),
            ]);
        } catch (\Throwable $e) {
            return Response::error("Request failed: {$e->getMessage()}");
        }
    }

    /**
     * endpointをサニタイズしてファイル名のみにする
     */
    private function sanitizeEndpoint(string $endpoint): string
    {
        // null byte を除去
        $endpoint = str_replace("\0", '', $endpoint);
        // basename() で最後のパス要素だけ取得（ディレクトリトラバーサル対策）
        $endpoint = basename($endpoint);
        return trim($endpoint);
    }

    /**
     * POST実行を共通化
     */
    private function postMovacal(string $url, string $basicId, string $basicPw, int $timeout, array $body): \Illuminate\Http\Client\Response
    {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withBasicAuth($basicId, $basicPw)
            ->contentType('application/json; charset=utf-8')
            ->acceptJson()
            ->timeout($timeout)
            ->post($url, $body);

        return $response;
    }

    /**
     * payloadにcredentialを付与
     */
    private function withCredential(array $params, string $credential): array
    {
        $params['credential'] = $credential;
        return $params;
    }

    /**
     * endpointが許可リストに含まれているかチェック
     */
    private function isAllowed(string $endpoint): bool
    {
        $allowed = (array) config('movacal.allowed_endpoints', []);
        return in_array($endpoint, $allowed, true);
    }

    /**
     * JSON文字列をデコードして配列として返す
     */
    private function decodeDefaultParams(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}