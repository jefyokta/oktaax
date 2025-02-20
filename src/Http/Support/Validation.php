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



namespace Oktaax\Http\Support;


class Validation
{
    protected static $rules;
    public  array $errors = [];

    public function register(string $rule, callable $callback)
    {

        if (isset(static::$rules[$rule]) || method_exists($this, $rule)) {
            throw new \InvalidArgumentException("Validation rule $rule is already registered.");
        }
        static::$rules[$rule] = $callback;

        return $this;
    }


    public function validate(array $data, array $rules): array
    {
        foreach ($rules as $field => $ruleSet) {
            $ruleList = explode('|', $ruleSet);

            foreach ($ruleList as $rule) {
                $this->applyRule($data, $field, $rule);
            }
        }
        $errors = static::$errors;
        static::$errors = [];
        return $errors;
    }



    protected function applyRule(array $data, string $field, string $rule): void
    {
        [$ruleName, $ruleParam] = $this->parseRule($rule);

        if (method_exists($this, $ruleName)) {
            $this->$ruleName($data, $field, ...$ruleParam);
        } elseif (isset(static::$rules[$ruleName])) {
            static::$rules[$ruleName]($data, $field, ...$ruleParam);
        } else {
            throw new \InvalidArgumentException("Validation rule $ruleName is not supported.");
        }
    }

    protected function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            $parts = explode(':', $rule, 2);
            $name = $parts[0];
            $params = explode(',', $parts[1]);
            return [$name, $params];
        }

        return [$rule, []];
    }
    protected function required(array $data, string $field): void
    {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $this->addError($field, 'required', "$field is required.");
        }
    }

    protected function email(array $data, string $field): void
    {
        if (isset($data[$field])) {
            if (!filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, 'email', "$field must be a valid email address.");
            }
        }
    }

    protected function numeric(array $data, string $field): void
    {
        if (isset($data[$field])) {
            if (!is_numeric($data[$field])) {
                $this->addError($field, 'numeric', "$field must be a numeric value.");
            }
        }
    }

    protected function min(array $data, string $field, string $param): void
    {
        if (isset($data[$field])) {
            if (strlen($data[$field]) < (int)$param) {
                $this->addError($field, 'min', "$field must be at least $param characters.");
            }
        }
    }

    protected function max(array $data, string $field, string $param): void
    {
        if (isset($data[$field])) {
            if (isset($data[$field]) && strlen($data[$field]) > (int)$param) {
                $this->addError($field, 'max', "$field must not exceed $param characters.");
            }
        }
    }

    protected function in(array $data, string $field, string $param): void
    {

        if (isset($data[$field])) {
            $allowedValues = explode(',', $param);
            if (!in_array($data[$field], $allowedValues)) {
                $this->addError($field, 'in', "$field must be one of: " . implode(', ', $allowedValues) . ".");
            }
        }
    }
    protected function between(array $data, string $field, array $params): void
    {
        [$min, $max] = $params;
        if (isset($data[$field])) {
            $length = strlen($data[$field]);
            if ($length < (int)$min || $length > (int)$max) {
                $this->addError($field, 'between', "$field must be between $min and $max characters.");
            }
        }
    }

    public function addError(string $field, string $rule, string $message): void
    {
        $this->errors[$field][$rule] = $message;
    }
}
