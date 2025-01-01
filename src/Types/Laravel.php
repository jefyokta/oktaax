<?php 
/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 * 
 * @link https://github.com/jefyokta/oktaax
 *
 * @copyright Copyright (c) 2024, Jefyokta
 *
 * MIT License
 *
 * Copyright (c) 2024 Jefyokta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */


namespace Oktaax\Types;

use Oktaax\Interfaces\Application;

class Laravel implements Application
{
    private $directory;

    /**
     * @var \Illuminate\Foundation\Application 
     */
    private $app;
    private $interactWithSocket = false;

    public function __construct( $directory)
    {
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

        return $this->directory . "/storage";
    }
 


    public function getDirectory()
    {
        return $this->directory;
    }
}
