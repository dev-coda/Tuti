<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class TronexMigrationController extends Controller
{
    /**
     * AJAX: Create Tuti account from Tronex cédula lookup.
     * Only works when user does NOT already exist in Tuti.
     */
    public function migrate(Request $request)
    {
        $request->validate([
            'document' => ['required', 'string', 'max:20'],
        ]);

        $document = trim(preg_replace('/\D/', '', $request->document));
        if (empty($document)) {
            return response()->json([
                'success' => false,
                'message' => 'Ingresa un número de cédula válido.',
            ], 422);
        }

        // User already exists in Tuti → error
        $existingUser = User::where('document', $document)->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes una cuenta en Tuti. Ingresa con tu correo y contraseña.',
            ], 422);
        }

        $data = UserRepository::getCustomRuteroId($document);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos tu cédula en el sistema Tronex. Verifica el número o regístrate como nuevo cliente.',
            ], 422);
        }

        $name = $data['name'] ?? 'Cliente';
        $email = 'tronex.' . time() . '@tuti.com';
        $tempPassword = Str::random(12);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'document' => $document,
            'password' => $tempPassword,
            'status_id' => User::PENDING,
            'tronex_migration_pending' => true,
        ]);

        if (!empty($data['routes'])) {
            foreach ($data['routes'] as $route) {
                $user->zones()->create([
                    'route' => $route['route'],
                    'zone' => $route['zone'],
                    'day' => $route['day'],
                    'address' => $route['address'],
                    'code' => $route['code'],
                ]);
            }
        }

        Auth::login($user);

        return response()->json([
            'success' => true,
            'redirect' => route('tronex.complete-profile'),
        ]);
    }

    /**
     * Show form to complete profile (email + password). Only for users with tronex_migration_pending.
     */
    public function showCompleteProfile()
    {
        $user = Auth::user();
        if (!$user || !$user->tronex_migration_pending) {
            return redirect()->route('home');
        }
        return view('auth.tronex-complete-profile');
    }

    /**
     * Store completed profile (email + password).
     */
    public function storeCompleteProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->tronex_migration_pending) {
            return redirect()->route('home');
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'tronex_migration_pending' => false,
        ]);

        return redirect()->route('home')->with('success', '¡Perfil completado! Ya puedes comprar en Tuti.');
    }
}
