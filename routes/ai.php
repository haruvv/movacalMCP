<?php

use App\Http\Middleware\VerifyMcpAuthorization;
use App\Mcp\Servers\MovacalServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/movacal', MovacalServer::class)
    ->middleware(VerifyMcpAuthorization::class);


// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);
