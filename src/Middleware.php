<?php

namespace Debva\Elnix;

abstract class Middleware
{
    abstract function handle($router, $next);
}
