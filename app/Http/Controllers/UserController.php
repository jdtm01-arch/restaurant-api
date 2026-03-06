<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Controlador de usuarios del restaurante.
 *
 * Permite al admin_restaurante gestionar los usuarios asociados a su restaurante.
 */
class UserController extends Controller
{
    /**
     * GET /users — Lista de usuarios del restaurante con su rol.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $restaurantId = $request->get('restaurant_id');

        $users = User::whereHas('restaurants', fn ($q) => $q->where('restaurants.id', $restaurantId))
            ->get()
            ->map(function ($user) use ($restaurantId) {
                $role = $user->roleForRestaurant($restaurantId);
                return [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $role ? [
                        'id'   => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ] : null,
                ];
            });

        return response()->json(['data' => $users]);
    }

    /**
     * POST /users — Crear usuario y asignarle rol en el restaurante.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $restaurantId = $request->get('restaurant_id');

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id'  => ['required', 'exists:roles,id'],
        ]);

        $this->preventAssigningProtectedRole($request, $validated['role_id']);

        $user = DB::transaction(function () use ($validated, $restaurantId) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            DB::table('restaurant_user')->insert([
                'restaurant_id' => $restaurantId,
                'user_id'       => $user->id,
                'role_id'       => $validated['role_id'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            return $user;
        });

        $role = Role::find($validated['role_id']);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => [
                    'id'   => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ],
            ],
        ], 201);
    }

    /**
     * GET /users/{user} — Detalle de usuario.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $restaurantId = $request->get('restaurant_id');

        // Verify user belongs to this restaurant
        $belongs = DB::table('restaurant_user')
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $belongs) {
            abort(404, 'Usuario no encontrado en este restaurante');
        }

        $role = $user->roleForRestaurant($restaurantId);

        return response()->json([
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $role ? [
                    'id'   => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ] : null,
            ],
        ]);
    }

    /**
     * PUT /users/{user} — Actualizar nombre, email y/o rol.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $restaurantId = $request->get('restaurant_id');

        $this->ensureNotProtectedAdmin($request, $user);

        $belongs = DB::table('restaurant_user')
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $belongs) {
            abort(404, 'Usuario no encontrado en este restaurante');
        }

        $validated = $request->validate([
            'name'    => ['sometimes', 'string', 'max:255'],
            'email'   => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role_id' => ['sometimes', 'exists:roles,id'],
        ]);

        if (isset($validated['role_id'])) {
            $this->preventAssigningProtectedRole($request, $validated['role_id']);
        }

        DB::transaction(function () use ($user, $validated, $restaurantId) {
            $user->update(collect($validated)->only(['name', 'email'])->toArray());

            if (isset($validated['role_id'])) {
                DB::table('restaurant_user')
                    ->where('restaurant_id', $restaurantId)
                    ->where('user_id', $user->id)
                    ->update([
                        'role_id'    => $validated['role_id'],
                        'updated_at' => now(),
                    ]);
            }
        });

        $role = $user->roleForRestaurant($restaurantId);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->fresh()->name,
                'email' => $user->fresh()->email,
                'role'  => $role ? [
                    'id'   => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ] : null,
            ],
        ]);
    }

    /**
     * DELETE /users/{user} — Desasociar usuario del restaurante.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $restaurantId = $request->get('restaurant_id');

        $this->ensureNotProtectedAdmin($request, $user);

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'No puedes eliminarte a ti mismo',
            ], 422);
        }

        DB::table('restaurant_user')
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'Usuario desasociado del restaurante exitosamente',
        ]);
    }

    /**
     * POST /users/{user}/reset-password — Resetear contraseña.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $restaurantId = $request->get('restaurant_id');

        $this->ensureNotProtectedAdmin($request, $user);

        $belongs = DB::table('restaurant_user')
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $belongs) {
            abort(404, 'Usuario no encontrado en este restaurante');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente. Se han revocado todas las sesiones activas.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        $restaurantId = $request->get('restaurant_id');
        $role = $user->roleForRestaurant($restaurantId);

        if (! $role || ! in_array($role->slug, ['admin_general', 'admin_restaurante'])) {
            abort(403, 'No tienes permiso para gestionar usuarios.');
        }
    }

    /**
     * Prevent admin_restaurante from modifying a user that has admin_general role.
     */
    private function ensureNotProtectedAdmin(Request $request, User $target): void
    {
        $restaurantId = $request->get('restaurant_id');
        $actorRole = $request->user()->roleForRestaurant($restaurantId);

        if ($actorRole && $actorRole->slug === 'admin_restaurante') {
            $targetRole = $target->roleForRestaurant($restaurantId);
            if ($targetRole && $targetRole->slug === 'admin_general') {
                abort(403, 'No tienes permiso para modificar un administrador general.');
            }
        }
    }

    /**
     * Prevent admin_restaurante from assigning the admin_general role.
     */
    private function preventAssigningProtectedRole(Request $request, int $roleId): void
    {
        $restaurantId = $request->get('restaurant_id');
        $actorRole = $request->user()->roleForRestaurant($restaurantId);

        if ($actorRole && $actorRole->slug === 'admin_restaurante') {
            $targetRole = Role::find($roleId);
            if ($targetRole && $targetRole->slug === 'admin_general') {
                abort(403, 'No tienes permiso para asignar el rol de administrador general.');
            }
        }
    }
}
