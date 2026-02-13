<?php

namespace App\Bootstrap;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException('Servicio no registrado: ' . $id);
        }

        $this->instances[$id] = ($this->bindings[$id])($this);

        return $this->instances[$id];
    }
}
