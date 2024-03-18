<?php

use Framework\Http\Request;
use Framework\Routing\Router;

require __DIR__ . "/../vendor/autoload.php";

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->withRouting(function (Router $router) {
    $router->get("/", function (Request $request) {
        return "Welcome";
    });

    $router->get("/products", [App\Http\Controllers\ProductController::class, 'index'])->middleware('api')->prefix('api');
    $router->post("/products", [App\Http\Controllers\ProductController::class, 'store'])->middleware('api')->prefix('api');
    $router->get("/products/{id}", [App\Http\Controllers\ProductController::class, 'show'])->middleware('api')->prefix('api');

    $router->get("/users", [App\Http\Controllers\UserController::class, 'index'])->middleware(['api', 'auth'])->prefix('api');
    $router->post("/users", [App\Http\Controllers\UserController::class, 'store'])->middleware('api')->prefix('api');
    $router->get("/users/{id}", [App\Http\Controllers\UserController::class, 'show'])->middleware('api')->prefix('api');
    $router->put("/users/{id}", [App\Http\Controllers\UserController::class, 'update'])->middleware('api')->prefix('api');
    $router->delete("/users/{id}", [App\Http\Controllers\UserController::class, 'delete'])->middleware('api')->prefix('api');
});

$app->handleRequest(Request::capture());
