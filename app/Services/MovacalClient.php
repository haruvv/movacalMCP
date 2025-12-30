<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Movacal API 共通HTTPクライアント
 * - Basic認証 + credential付与
 * - allowlist + get* チェック
 * - 401リトライ（1回のみ）
 * - default_params マージ
 */
class MovacalClient
{
    private string $baseUrl;
    private string $basicId;
    private string $basicPw;
    private array $clinicInfo;
    private array $allowedEndpoints;
    private MovacalCredentialService $credentialService;

    public function __construct(MovacalCredentialService $credentialService)
    {
        $this->credentialService = $credentialService;

        $this->baseUrl = rtrim((string) config('movacal.base_url'), '/');
        $this->basicId = (string) config('movacal.basic.id');
        $this->basicPw = (string) config('movacal.basic.password');
        $this->allowedEndpoints = (array) config('movacal.allowed_endpoints', []);
        $this->clinicInfo = [
            'clinic_id' => (string) config('movacal.clinic_info.clinic_id', ''),
            'clinic_code' => (string) config('movacal.clinic_info.clinic_code', ''),
        ];

        $this->validateConfig();
    }

    /**
     * Movacal API にリクエストを送信
     *
     * @param string $endpoint エンドポイント名（例: getVersion.php）
     * @param array $params リクエストパラメータ
     * @param int $timeout タイムアウト秒数
     * @return array レスポンスデータ
     * @throws RuntimeException
     */
    public function request(string $endpoint, array $params = [], int $timeout = 30): array
    {
        // サニタイズ
        $endpoint = $this->sanitizeEndpoint($endpoint);

        // get* チェック
        if (!str_starts_with($endpoint, 'get')) {
            throw new RuntimeException("Only get* endpoints are allowed. Got: {$endpoint}");
        }

        // allowlist チェック
        if (!$this->isAllowed($endpoint)) {
            throw new RuntimeException("Endpoint is not in allowlist: {$endpoint}");
        }

        // timeout ガード
        $timeout = max(1, min(60, $timeout));

        // clinic_info を params にマージ
        $mergedParams = array_merge(['clinic_info' => $this->clinicInfo], $params);

        $url = "{$this->baseUrl}/{$endpoint}";

        // credential 取得してリクエスト
        $credential = $this->credentialService->getCredential();
        $response = $this->post($url, $this->withCredential($mergedParams, $credential), $timeout);

        // 401 なら refresh して1回だけリトライ
        if ($response->status() === 401) {
            $credential = $this->credentialService->refreshCredential();
            $response = $this->post($url, $this->withCredential($mergedParams, $credential), $timeout);
        }

        if (!$response->successful()) {
            throw new RuntimeException("Upstream error: HTTP {$response->status()}");
        }

        $contentType = (string) $response->header('content-type', '');

        if (str_contains($contentType, 'application/json')) {
            return $response->json() ?? [];
        }

        // JSON以外はそのまま返す
        return [
            'content_type' => $contentType,
            'raw' => $response->body(),
        ];
    }

    /**
     * HTTP POST 実行
     */
    private function post(string $url, array $body, int $timeout): \Illuminate\Http\Client\Response
    {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withBasicAuth($this->basicId, $this->basicPw)
            ->contentType('application/json; charset=utf-8')
            ->acceptJson()
            ->timeout($timeout)
            ->post($url, $body);

        return $response;
    }

    /**
     * params に credential を付与
     */
    private function withCredential(array $params, string $credential): array
    {
        $params['credential'] = $credential;
        return $params;
    }

    /**
     * endpoint をサニタイズ
     */
    private function sanitizeEndpoint(string $endpoint): string
    {
        // null byte 除去
        $endpoint = str_replace("\0", '', $endpoint);
        // basename() でディレクトリトラバーサル対策
        $endpoint = basename($endpoint);
        return trim($endpoint);
    }

    /**
     * endpoint が allowlist に含まれているか
     */
    private function isAllowed(string $endpoint): bool
    {
        return in_array($endpoint, $this->allowedEndpoints, true);
    }

    /**
     * 設定値のバリデーション
     */
    private function validateConfig(): void
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('MOVACAL_BASE_URL is not configured.');
        }
        if ($this->basicId === '' || $this->basicPw === '') {
            throw new RuntimeException('MOVACAL_BASIC_ID / MOVACAL_BASIC_PASSWORD is not configured.');
        }
        if ($this->clinicInfo['clinic_id'] === '' || $this->clinicInfo['clinic_code'] === '') {
            throw new RuntimeException('MOVACAL_CLINIC_ID / MOVACAL_CLINIC_CODE is not configured.');
        }
    }
}

