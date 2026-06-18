<?php

namespace Tests\Bone\MCP;

use Bone\Contracts\Container\ContainerInterface;
use Bone\MCP\McpPackage;
use Barnacle\Container;
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

        $this->assertTrue($container->has(\Mcp\Server\Server::class));
    }
}
