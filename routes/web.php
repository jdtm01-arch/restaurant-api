<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\Request;

Route::post('/login', function (Request $request) {

    $credentials = $request->only('email', 'password');

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Credenciales inválidas'], 401);
    }

    $request->session()->regenerate();

    return response()->json([
        'message' => 'Login correcto'
    ]);
});

Route::get('/', function () {
    return view('welcome');
});
