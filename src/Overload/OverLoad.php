<?php

class OverLoad
{
    protected $classes = [];

    public function add($class)
    {
        $this->classes[] = $class;
    }

    public function remove($class)
    {

        $this->classes =  array_filter($this->classes, function ($val) use ($class) {
            return $val == $class;
        });
    }

    public function getClasses(): array
    {

        return $this->classes;
    }
};
