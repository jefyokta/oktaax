<?php

namespace Oktaax\Types;

use InvalidArgumentException;

class Laravel
{
    public $domain;
    public $directory;
    private $app;

    public function withdomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    public function withDirectory($directory)
    {

        $this->directory = $directory;
        return $this;
    }


    public function getApplication(): \Illuminate\Foundation\Application
    {
        $this->ensureDirectoryHasBeenDefined();

        if (!file_exists($this->directory . "/bootstrap/app.php")) throw new InvalidArgumentException("Laravel Application not found!");

        return $this->app =  require $this->directory . "/bootstrap/app.php";
    }

    public function loadVendor()
    {
        $this->ensureDirectoryHasBeenDefined();

        require $this->directory . "/vendor/autoload.php";
    }

    public function getPublicPath()
    {
        $this->ensureDirectoryHasBeenDefined();

        return $this->directory . "/public";
    }

    public function getStoragePath()
    {
        $this->ensureDirectoryHasBeenDefined();

        return $this->directory . "/storage";
    }

    public function ensureDirectoryHasBeenDefined()
    {
        if (is_null($this->directory)) {
            throw new InvalidArgumentException("Laravel directory not defined.");
        }
    }
}
