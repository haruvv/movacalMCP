<?php

use App\Mcp\Servers\MovacalServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/movacal', MovacalServer::class);


// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);
