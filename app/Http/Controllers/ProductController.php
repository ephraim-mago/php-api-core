<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Framework\Http\Request;
use Framework\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::all();

        return new JsonResponse($products);
    }

    public function show($id)
    {
        $product = Product::find($id);

        return  new JsonResponse($product);
    }

    public function store(Request $request)
    {
        $filePath = base_path("public/test.txt");
        $status = file_put_contents($filePath, json_encode($request->input()));

        return $status ? "Success" : "Error";
    }
}
