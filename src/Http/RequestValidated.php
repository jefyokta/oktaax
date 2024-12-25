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
 




namespace Oktaax\Http;



class RequestValidated
{


    /**
     *  @var array<string[]> $data
     * @var ?array<string[]> $errors
     */
    public function __construct(private $data = [], private ?array $errors = null) {}


    /**
     * @param array<string> $message
     * @return static
     */
    public function setMessage(array $messages): static
    {

        foreach ($messages as $ms => $value) {
            [$field, $rule] = explode('.', $ms);

            if (isset($this->errors[$field])) {
                if (isset($this->errors[$field][$rule])) {
                    $this->errors[$field][$rule] = $value;
                }
            }
        }

        return $this;
    }
    /**
     * 
     * Return all validate result
     * @return array<array[],array[]>
     */
    public function getAll(): array
    {
        return [$this->data, $this->errors];
    }
    /**
     * 
     * Return all error from result
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors ?? [];
    }
    /**
     * 
     * Return all data from result
     * @return array
     */
    public function getData(): array
    {

        return $this->data;
    }
    /**
     * 
     * Return first errors
     * @return string|null
     */
    public function getError()
    {
        isset($this->errors) ? current(current($this->errors)) : null;
    }
};
