<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use App\Mcp\Tools\MovacalGetTool;

class MovacalServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Movacal Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        This MCP server provides **read-only** access to the Movacal API (a medical information system).
        
        ## Safety / Non-negotiables
        - **Do NOT perform any write operations** (create/update/delete). This server is read-only.
        - Use only the provided tool `movacal_get`.
        - Only endpoints starting with `get` AND present in the server-side allowlist can be called.
        - Treat all returned data as sensitive medical information. Avoid unnecessary repetition of personally identifiable information (PII).
        
        ## Available Tool
        
        ### movacal_get (read-only)
        Fetch data from Movacal API using credential authentication.
        - Only `get*` endpoints are allowed (read-only, safe)
        - Uses Basic Authentication + credential (HMAC-SHA256) managed by the server
        - Credential is automatically managed and cached
        - Provide request parameters via `params` as a JSON object
        
        **Parameters**
        - `endpoint` (string, required): Endpoint filename (e.g., `getPatientlist.php`)
        - `params` (object, optional): Request parameters to include in the POST body
        - `timeout_seconds` (integer, optional): Request timeout (default 30)
        
        **Usage Notes**
        - Prefer the minimum parameters needed to fulfill the request.
        - If you are unsure which endpoint to call, ask the user for clarification rather than guessing.
        - The server will reject non-allowlisted endpoints.
    MARKDOWN;
    

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        MovacalGetTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
