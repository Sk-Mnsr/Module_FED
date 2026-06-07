<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maravel\Http\Controllers\APIController;

/**
 * @group Authentification
 *
 * EndPoints pour gérer l'authentification
 */
class AuthController extends APIController
{
    /**
     * Connecte un utilisateur
     *
     * @bodyParam email     string  required L'email de l'utilsateur.                   Example: charles.gamligo@gmail.com
     * @bodyParam password  string  required Le mot de passe complet de l'utilisateur.  Example: password
     *
     * @response 200
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 400);
        } else {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return $this->responseError(['password' => ['Mot de passe incorrect']], 400);
            }

            if (! $user->activated) {
                return $this->responseError(['email' => ['Compte désactivé']], 403);
            }

            return $this->responseOk([
                'userToken' => $user->createToken($request->email)->plainTextToken,
                'user' => $user,
                'password_change_required' => $user->password_change_required,
            ]);
        }
    }

    /**
     * Affiche l'utilisateur connecté
     *
     * @response 200
     */
    public function data(Request $request)
    {
        return $this->responseOk($request->user());
    }

    /**
     * Déconnecte l'utilisateur connecté
     *
     * @response 204
     */
    public function logout(Request $request)
    {
        if ($request->user()->currentAccessToken()->delete()) {
            return $this->responseOk(['messages' => ['logout done']]);
        } else {
            return $this->responseError(['errors' => ['error during logout']]);
        }
    }
}
