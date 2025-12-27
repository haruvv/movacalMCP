<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Middleware\VerifyChatApiToken;

// 固定トークン検証 + レート制限（1分間に10回まで）
Route::post('/chat', [ChatController::class, 'chat'])
    ->middleware([VerifyChatApiToken::class, 'throttle:10,1']);

