<?php

namespace {{namespace}};

class {{name}} extends \Debva\Elnix\Middleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}