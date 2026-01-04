<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Movacal APIのcredentialを取得・キャッシュするサービス
 */
class MovacalCredentialService
{
    private const CACHE_KEY = 'movacal_credential';

    private string $baseUrl;
    private string $provider;
    private string $secretKey;
    private string $basicId;
    private string $basicPw;
    private int $credentialTtl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('movacal.base_url'), '/');
        $this->provider = (string) config('movacal.provider');
        $this->secretKey = (string) config('movacal.secret_key');
        $this->basicId = (string) config('movacal.basic.id');
        $this->basicPw = (string) config('movacal.basic.password');
        $this->credentialTtl = (int) config('movacal.credential_ttl', 900);

        if ($this->baseUrl === '') {
            throw new RuntimeException('MOVACAL_BASE_URL is not configured.');
        }
        if ($this->provider === '') {
            throw new RuntimeException('MOVACAL_PROVIDER is not configured.');
        }
        if ($this->secretKey === '') {
            throw new RuntimeException('MOVACAL_SECRET_KEY is not configured.');
        }
        if ($this->basicId === '' || $this->basicPw === '') {
            throw new RuntimeException('MOVACAL_BASIC_ID / MOVACAL_BASIC_PASSWORD is not configured.');
        }
    }

    /**
     * credentialを取得（キャッシュがあればそれを返す）
     */
    public function getCredential(): string
    {
        return Cache::remember(self::CACHE_KEY, $this->credentialTtl, function (): string {
            return $this->fetchCredential();
        });
    }

    /**
     * キャッシュをクリアしてcredentialを再取得
     */
    public function refreshCredential(): string
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getCredential();
    }

    /**
     * credential.phpからcredentialを取得
     */
    private function fetchCredential(): string
    {
        $url = "{$this->baseUrl}/credential.php";

        // 乱数を生成
        $randomBytes = random_bytes(32);
        $randomB64 = base64_encode($randomBytes);

        // 署名値を生成
        $signatureHex = hash_hmac('sha256', $randomBytes, $this->secretKey);
        $signatureB64 = base64_encode($signatureHex);

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withBasicAuth($this->basicId, $this->basicPw)
            ->contentType('application/json; charset=utf-8')
            ->acceptJson()
            ->timeout(30)
            ->post($url, [
                'provider'   => $this->provider,
                'random'     => $randomB64,
                'signature'  => $signatureB64,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException("Failed to fetch credential: HTTP {$response->status()}");
        }

        $data = $response->json();

        if (!isset($data['credential']) || !is_string($data['credential'])) {
            throw new RuntimeException('Invalid credential response: missing credential field.');
        }

        return $data['credential'];
    }
}

