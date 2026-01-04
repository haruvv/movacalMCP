<?php

namespace App\Services\Movacal;

use App\Services\MovacalClient;

/**
 * Movacal FileCategory API ラッパ
 */
class FileCategoryApi
{
    private MovacalClient $client;

    public function __construct(MovacalClient $client)
    {
        $this->client = $client;
    }

    /**
     * 書類カテゴリー一覧を取得
     *
     * @return array
     */
    public function getFileCategory(): array
    {
        return $this->client->request('getFileCategory.php');
    }
}

