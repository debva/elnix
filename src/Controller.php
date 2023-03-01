<?php

namespace Debva\Elnix;

abstract class Controller
{
    public $middleware = [];

    public function middleware(...$middlewares)
    {
        $this->middleware = flatten($middlewares);
        return $this;
    }
}
