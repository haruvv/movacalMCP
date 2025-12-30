<?php

namespace App\Services\Movacal;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * operation を API メソッドに振り分けるルーター
 */
class MovacalReadRouter
{
    /**
     * 許可された operation 一覧
     */
    public const ALLOWED_OPERATIONS = [
        'get_version',
        // 今後追加: 'get_patient', 'get_patient_list', etc.
    ];

    private VersionApi $versionApi;

    public function __construct(VersionApi $versionApi)
    {
        $this->versionApi = $versionApi;
    }

    /**
     * operation を実行
     *
     * @param string $operation 操作名（例: get_version）
     * @param array $args 引数
     * @return array レスポンス
     * @throws RuntimeException
     */
    public function execute(string $operation, array $args = []): array
    {
        Log::info('[Router] Executing operation', [
            'operation' => $operation,
        ]);

        if (!$this->isAllowedOperation($operation)) {
            throw new RuntimeException("Unknown or disallowed operation: {$operation}");
        }

        $result = match ($operation) {
            'get_version' => $this->versionApi->getVersion(),
            default => throw new RuntimeException("Operation not implemented: {$operation}"),
        };

        Log::info('[Router] Operation completed', [
            'operation' => $operation,
        ]);

        return $result;
    }

    /**
     * operation が許可されているか
     */
    public function isAllowedOperation(string $operation): bool
    {
        return in_array($operation, self::ALLOWED_OPERATIONS, true);
    }

    /**
     * 許可された operation 一覧を取得
     */
    public function getAllowedOperations(): array
    {
        return self::ALLOWED_OPERATIONS;
    }
}

