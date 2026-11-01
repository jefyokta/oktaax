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
     * Store filtered input and an optional field/rule error map.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, array<string, string>>|null $errors
     */
    public function __construct(
        private $data = [],
        private ?array $errors = null,
    ) {}

    /**
     * Override existing field.rule messages without adding new validation failures.
     *
     * @param array<string, string> $messages
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

    public function isOk(): bool
    {
        return empty($this->errors);
    }
    /**
     * Return filtered input and the nullable validation error map as a tuple.
     *
     * @return array{array<array-key, mixed>, array<string, array<string, string>>|null}
     */
    public function getAll(): array
    {
        return [$this->data, $this->errors];
    }
    /**
     * Return validation failures, or an empty map when none are present.
     *
     * @return array<string, array<string, string>>
     */
    public function getErrors(): array
    {
        return $this->errors ?? [];
    }
    /**
     * Return only the input fields retained by validation.
     *
     * @return array<array-key, mixed>
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

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Return validation failures, or an empty map when none are present.
     *
     * @return array<string, array<string, string>>
     */
    public function errors(): array
    {
        return $this->getErrors();
    }

    /**
     * Return only the input fields retained by validation.
     *
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->getData();
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
}
