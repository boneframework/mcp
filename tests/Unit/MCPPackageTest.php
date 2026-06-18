<?php

namespace Tests\Bone\MCP;

use Bone\MCP\MCPPackage;
use Barnacle\Container;
use Codeception\Test\Unit;
use Mcp\Server;

class MCPPackageTest extends Unit
{
    protected McpPackage $mcpPackage;

    protected function _before()
    {
        $this->mcpPackage = new MCPPackage();
    }

    protected function _after()
    {
        unset($this->mcpPackage);
    }

    public function testAddToContainer()
    {
        $container = new Container();
        $this->mcpPackage->addToContainer($container);
        $this->assertTrue($container->has(Server::class));
    }
}
