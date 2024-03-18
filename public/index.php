<?php

use Framework\Http\Request;
use Framework\Routing\Router;

require __DIR__ . "/../vendor/autoload.php";

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->withRouting(function (Router $router) {

    /*
    | Application Routing System
    | 
    | Middleware ['api': For routes api's; 'securize': Securize the route and required user authentified]
    |
    */

    $router->get("/", function (Request $request) {
        return "Welcome";
    });

    $router->post('/login', [App\Http\Controllers\AuthenticateController::class, 'login'])->middleware('api')->prefix('api');
    $router->post('/logout', [App\Http\Controllers\AuthenticateController::class, 'logout'])->middleware(['api', 'securize'])->prefix('api');

    $router->get("/products", [App\Http\Controllers\ProductController::class, 'index'])->middleware('api')->prefix('api');
    $router->post("/products", [App\Http\Controllers\ProductController::class, 'store'])->middleware('api')->prefix('api');
    $router->get("/products/{id}", [App\Http\Controllers\ProductController::class, 'show'])->middleware('api')->prefix('api');

    $router->get("/users", [App\Http\Controllers\UserController::class, 'index'])->middleware(['api', 'securize'])->prefix('api');
    $router->post("/users", [App\Http\Controllers\UserController::class, 'store'])->middleware(['api', 'securize'])->prefix('api');
    $router->get("/users/{id}", [App\Http\Controllers\UserController::class, 'show'])->middleware(['api', 'securize'])->prefix('api');
    $router->put("/users/{id}", [App\Http\Controllers\UserController::class, 'update'])->middleware(['api', 'securize'])->prefix('api');
    $router->delete("/users/{id}", [App\Http\Controllers\UserController::class, 'delete'])->middleware(['api', 'securize'])->prefix('api');
});

$app->handleRequest(Request::capture());
