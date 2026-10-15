<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Registrar usuario
     *
     * Crea una cuenta nueva y devuelve un token para autenticar solicitudes.
     *
     * @group Autenticación
     * @bodyParam name string required Nombre del usuario. Example: Ana Prueba
     * @bodyParam email string required Correo único del usuario. Example: ana@example.com
     * @bodyParam password string required Contraseña de al menos 8 caracteres. Example: password123
     * @response 201 {"user":{"id":1,"name":"Ana Prueba","email":"ana@example.com"},"token":"1|token"}
     * @response 422 {"message":"The email has already been taken."}
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Iniciar sesión
     *
     * Verifica el correo y contraseña y devuelve un token.
     *
     * @group Autenticación
     * @bodyParam email string required Correo registrado. Example: ana@example.com
     * @bodyParam password string required Contraseña. Example: password123
     * @response 200 {"user":{"id":1,"name":"Ana Prueba","email":"ana@example.com"},"token":"1|token"}
     * @response 401 {"message":"Credenciales incorrectas."}
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    /**
     * Cerrar sesión
     *
     * Revoca el token que se utilizó para esta solicitud.
     *
     * @group Autenticación
     * @authenticated
     * @response 200 {"message":"Sesión cerrada."}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.'], 200);
    }
}