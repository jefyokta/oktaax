<?php

namespace Oktaax\Types;

use InvalidArgumentException;
use Oktaax\Interfaces\Application;

class Laravel implements Application
{
    private $host;
    private $directory;

    /**
     * @var \Illuminate\Foundation\Application 
     */
    private $app;
    private $interactWithSocket = false;

    public function __construct($host, $directory)
    {
        $this->host = $host;
        $this->directory = $directory;
    }


    /**
     * 
     *  Laravel application can interact with socket
     * 
     * @return bool
     */


    public function isAbleToInteractWithSocket()
    {
        return $this->interactWithSocket;
    }
    /**
     * 
     *  Laravel application can interact with socket
     * 
     * @return Laravel
     */

    public function canInteractWithSocket()
    {
        $this->interactWithSocket = true;
        return $this;
    }

    /**
     * 
     * Get Laravel Application
     * 
     * @return \Illuminate\Foundation\Application
     */

    public function getApplication()
    {
        return  require_once $this->directory . "/bootstrap/app.php";;
    }

    /**
     * 
     * Load Laravel Vendor
     * 
     * 
     */

    public function loadVendor()
    {
        require_once $this->directory . "/vendor/autoload.php";

        ;
    }

    /**
     * 
     * Get Laravel Public Path
     * 
     * @return string
     */

    public function getPublicPath()
    {

        return $this->directory . "/public";
    }

    /**
     * 
     * Get Laravel Storage Path
     * 
     * @return string
     */

    public function getStoragePath()
    {
        $this->ensureDirectoryHasBeenDefined();

        return $this->directory . "/storage";
    }
    /**
     * 
     * ensure directory has been defined
     * 
     * 
     * @return void
     * @throws InvalidArgumentException
     */
    public function ensureDirectoryHasBeenDefined()
    {
        if (is_null($this->directory)) {
            throw new InvalidArgumentException("Laravel directory not defined.");
        }
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getDirectory()
    {
        return $this->directory;
    }
}
