<?php

namespace App\Models;

use Framework\Auth\Api\HasApiTokens;
use Framework\Database\Model;

class User extends Model
{
    use HasApiTokens;

    protected $table = "users";

    protected $fillable = [
        'name',
        'email',
        'password'
    ];
}
