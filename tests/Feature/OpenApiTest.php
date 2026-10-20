<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

function documentedOperations(): array
{
    $spec = Yaml::parseFile(base_path('openapi.yaml'));

    expect($spec['openapi'])->toStartWith('3.1')
        ->and($spec['servers'][0]['url'])->toBe('/api/v1');

    return collect($spec['paths'])
        ->flatMap(fn (array $item, string $path) => collect($item)
            ->keys()
            ->intersect(['get', 'post', 'put', 'patch', 'delete'])
            ->map(fn (string $method) => strtoupper($method).' /api/v1'.$path))
        ->sort()->values()->all();
}

it('toda rota da API está no openapi.yaml e vice-versa', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (RouteDefinition $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' /'.$route->uri()))
        ->sort()->values()->all();

    expect($routes)->not->toBeEmpty()
        ->and(documentedOperations())->toBe($routes);
});
