<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUserWithRoles($user),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ME
    |--------------------------------------------------------------------------
    */
    public function me(Request $request)
    {
        return response()->json([
            'data' => $this->formatUserWithRoles($request->user()),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Load user with restaurants and append role object to each pivot.
     */
    private function formatUserWithRoles(User $user): array
    {
        $user->load('restaurants');
        $userData = $user->toArray();

        $roleIds = collect($userData['restaurants'] ?? [])
            ->pluck('pivot.role_id')
            ->filter()
            ->unique();

        $roles = Role::whereIn('id', $roleIds)->get()->keyBy('id');

        foreach ($userData['restaurants'] as &$restaurant) {
            $roleId = $restaurant['pivot']['role_id'] ?? null;
            $restaurant['pivot']['role'] = $roleId && isset($roles[$roleId])
                ? $roles[$roleId]->toArray()
                : null;
        }

        return $userData;
    }
}
