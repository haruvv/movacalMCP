<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use App\Mcp\Tools\MovacalPostTool;

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
        This MCP server provides access to the Movacal API, a medical information system.

        ## Available Tools

        ### movacal_post
        Send POST requests to Movacal API endpoints with Basic Authentication.

        **Usage:**
        - Specify the endpoint filename (e.g., `getPatient.php`, `postSchedule.php`, `getDiaglist.php`)
        - Provide request parameters as an object in the `payload` field
        - The payload will be merged with default parameters configured in the environment
        - Choose between JSON (`as_json: true`) or form-encoded (`as_json: false`) request format

        **Example endpoints:**
        - `getPatient.php` - Get patient information
        - `getPatientlist.php` - Get list of patients
        - `postSchedule.php` - Create or update schedule
        - `getDiaglist.php` - Get diagnosis list
        - `getVersion.php` - Get API version

        **Important:**
        - Only endpoints in the allowlist can be accessed
        - All requests use Basic Authentication (configured server-side)
        - Default parameters are automatically merged with your payload
        - Response format depends on the endpoint (usually JSON)

        When a user asks about Movacal data or operations, use the `movacal_post` tool with the appropriate endpoint and parameters.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        MovacalPostTool::class,
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
