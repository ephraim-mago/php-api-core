<?php

namespace App\Http\Controllers;

use App\Models\User;
use Framework\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        dd(app());
        
        $users = User::all();

        return $users;
    }

    public function show(int $id, Request $request)
    {
        $user = User::find($id);
        if ($user) {
            return $user;
        }

        return "User no found.";
    }

    public function store(Request $request)
    {
        $data = [
            "name" => "User Sample 3",
            "email" => "user-sample-3@mail.com",
            "password" => "User Sample 3"
        ];

        $user = new User($data);
        $user->save();

        return $user;
    }

    public function update($id, Request $request)
    {
        $user = User::find($id);
        if ($user) {
            $user->update($request->input());

            return $user;
        }

        return "User no found.";
    }

    public function delete(int $id)
    {
        $user = User::find($id);
        if ($user) {
            $user->delete();

            return "Success.";
        }

        return "User no found.";
    }
}
