<?php

namespace Tests\Bone\DebugBar;

use Bone\Contracts\Container\ContainerInterface;
use Bone\DebugBar\MCPPackage;
use Codeception\Test\Unit;

class MCPPackageTest extends Unit
{
    protected MCPPackage $mcpPackage;

    protected function _before()
    {
        $this->mcpPackage = new MCPPackage();
    }

    protected function _after()
    {
        unset($this->mcpPackage);
    }

    public function testBlah()
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('addToContainer');
        $this->mcpPackage->addToContainer();
    }
}
