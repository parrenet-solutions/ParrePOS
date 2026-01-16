<?php

namespace App\Core;

class Request
{
    private string $method;
    private string $path;
    private array $headers;
    private array $query;
    private string $rawBody;
    private ?array $json;
    private array $attributes = [];
    private array $params = [];

    public function __construct(string $method, string $path, array $headers, array $query, string $rawBody, ?array $json)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->headers = $headers;
        $this->query = $query;
        $this->rawBody = $rawBody;
        $this->json = $json;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $query = $_GET ?? [];
        $rawBody = file_get_contents('php://input') ?: '';

        return new self($method, $path, $headers, $query, $rawBody, null);
    }

    public function withJson(?array $json): self
    {
        $clone = clone $this;
        $clone->json = $json;

        return $clone;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }

    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;

        return $clone;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $target = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $target) {
                return is_array($value) ? implode(',', $value) : $value;
            }
        }

        return null;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getJson(): ?array
    {
        return $this->json;
    }
}
