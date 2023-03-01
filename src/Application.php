<?php

namespace Debva\Elnix;

class Application extends Router
{
    const VERSION = 'development';
    
    public function setAppPath($path)
    {
        $this->appPath = trim($path, DIRECTORY_SEPARATOR);
    }
}
