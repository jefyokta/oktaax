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

namespace Oktaax\Blade;

use Error;
use Exception;
use Oktaax\Http\Request;

class BladeDirectives
{
    /**
     * Blade method field
     * 
     * @param string $method
     * @return string
     * 
     */
    public static function methodField($method)
    {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($method) . '">';
    }

    /**
     * 
     * Blade csrf field
     * 
     * @param \Oktaax\Http\Request $request
     * @return string
     * 
     * 
     */
    public static function csrf(Request $request)
    {

        return '<input type="hidden" name="_token" value="' . $request->_token . '">';
    }

    /**
     * 
     * Blade vite field
     * 
     * @param string|array $resources
     * @param string $publicDir
     * 
     * @return string
     * 
     * 
     */
    public static function vite($resources, $publicDir)
    {
        $output = '';
        $manifestPath = $publicDir . '/build/.vite/manifest.json';
        if (!file_exists($manifestPath)) {
            if (file_exists($publicDir . "/vite-okta")) {
                $viteHost = file_get_contents($publicDir . "/vite-okta");
                 $output .= '<script type="module" src="' . $viteHost . '/@vite/client"></script>';
                foreach ($resources as $resource) {
                    $asset = $resource;
                    $extension = pathinfo($asset, PATHINFO_EXTENSION);
                    if ($extension === "js") {
                        $output .= "<script type='module' src='http://{$viteHost}/$asset'></script>\n";
                    } elseif ($extension === "css") {
                        $output .= "<link rel='stylesheet' href='http://{$viteHost}/$asset'>\n";
                    } else {
                        $output .= "<!-- Unsupported asset type: $resource -->\n";
                    }
                }
                return $output;
            } else {
                throw new Exception("Vite Server is not running! please run `npm run dev` or `npm run build`");
            }
        } else {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            foreach ($resources as $resource) {

                $resource = trim($resource, "'");
                $resource = str_replace("\\", "", $resource);

                if (isset($manifest[$resource])) {
                    $asset = "/build/" . $manifest[$resource]['file'];
                    if (file_exists($publicDir . $asset)) {
                        $extension = pathinfo($resource, PATHINFO_EXTENSION);
                        if ($extension === "js") {
                            $output .= "<script type='module' src='$asset'></script>\n";
                        } elseif ($extension === "css") {
                            $output .= "<link rel='stylesheet' href='$asset'>\n";
                        } else {
                            $output .= "<!-- Unsupported asset type: $resource -->\n";
                        }
                    } else {
                        throw new Exception("$publicDir$asset does'nt exist! please run npx vite build first!");
                    }
                } else {

                    throw new Exception("Manifest $resource does'nt exist! please add the resource to your vite config! then run npx vite build");
                }
            }

            return $output;
        }
    }
}
