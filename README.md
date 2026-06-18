# mcp
[![Build Status](https://travis-ci.org/boneframework/mcp.png?branch=master)](https://travis-ci.org/boneframework/mcp) [![Code Coverage](https://scrutinizer-ci.com/g/boneframework/mcp/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/boneframework/mcp/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/boneframework/mcp/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/boneframework/mcp/?branch=master) <br />
MCP for Bone Framework
## installation
Install via composer
```
composer require --dev boneframework/mcp
```
And then enable the package in your `config/packages.php`

```php
<?php

// use statements here
use Bone\MCP\MCPPackage;

return [
    'packages' => [
        // packages here...,
       MCPPackage::class,
    ],
];
```
# usage
