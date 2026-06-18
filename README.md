# debug-bar
[![Build Status](https://travis-ci.org/delboy1978uk/blank.png?branch=master)](https://travis-ci.org/delboy1978uk/blank) [![Code Coverage](https://scrutinizer-ci.com/g/delboy1978uk/blank/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/delboy1978uk/blank/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/delboy1978uk/blank/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/delboy1978uk/blank/?branch=master) <br />
A PHP Debug Basr for Bone Framework
## installation
Install via composer
```
composer require --dev boneframework/debug-bar
```
And then enable the package in your `config/packages.php`

```php
<?php

// use statements here
use Bone\DebugBar\MCPPackage;

return [
    'packages' => [
        // packages here...,
       MCPPackage::class,
    ],
];
```
# usage
