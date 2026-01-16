<?php

namespace App\Core\Middleware;

use App\Core\Request;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): array;
}
