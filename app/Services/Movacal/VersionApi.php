<?php

namespace App\Services\Movacal;

use App\Services\MovacalClient;

/**
 * Movacal Version API ラッパ
 */
class VersionApi
{
    private MovacalClient $client;

    public function __construct(MovacalClient $client)
    {
        $this->client = $client;
    }

    /**
     * APIバージョン情報を取得
     *
     * @return array
     */
    public function getVersion(): array
    {
        return $this->client->request('getVersion.php');
    }
}

