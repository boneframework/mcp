<?php

namespace Tests\Bone\MCP;

use Barnacle\Container;
use Bone\MCP\McpPackage;
use Codeception\Test\Unit;

class MCPPackageTest extends Unit
{
    protected McpPackage $mcpPackage;

    protected function _before()
    {
        $this->mcpPackage = new McpPackage();
    }

    protected function _after()
    {
        unset($this->mcpPackage);
    }

    public function testAddToContainer()
    {
        $container = new Container();
        $this->mcpPackage->addToContainer($container);

        $this->assertTrue($container->has(\Mcp\Server::class));
    }
}
