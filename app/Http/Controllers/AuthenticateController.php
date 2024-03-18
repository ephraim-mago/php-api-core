<?php

namespace App\Http\Controllers;

use App\Models\User;
use Framework\Http\Request;

class AuthenticateController extends Controller
{
    public function login(Request $request)
    {
        $user = User::query()->where("email", "=", $request->input('email'))->first();

        if ($user && $user->password === $request->input('password')) {
            return response()->json($user->createToken("API_TOKEN"));
        }

        return response()->json([
            'message' => 'User no found.',
        ], 404);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->deleteAccessToken();

        return response()->json([
            'message' => 'User disconnected successully.',
        ]);
    }
}
