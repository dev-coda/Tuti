<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $cities = \App\Models\City::orderBy('name')->get(['id', 'name']);
        return view('auth.register', compact('cities'));
    }

    public function complete(Request $request)
    {
        $validate = $request->validate([
            'document' => ['required', 'string', 'max:255'],
        ]);

        $document = $validate['document'];
        //Valido si el usuario existe en la base de datos
        $user = User::where('document', $document)->whereStatusId(User::ACTIVE)->first();

        if ($user) {
            //Si el usuario existe, lo redirijo a la pagina de login
            return redirect()->route('login')->with('error', 'El usuario ya se encuentra registrado, ingresá con tus credenciales.');
        }

        //busco el usuario en ax
        $client = UserRepository::getCustomRuteroId($document);

        if (!$client) {
            return to_route('form')->with('error', 'El usuario no se encuentra registrado en el sistema.');
        }

        return view('auth.complete');

        // $validate['password'] = Hash::make($validate['password']);

        // $validate['document_front'] = $request->document_front->store('documents', 'public');
        // $validate['document_back'] = $request->document_back->store('documents', 'public');
        // $validate['company_document'] = $request->company_document->store('documents', 'public');

        // $user = User::create($validate);

        // event(new Registered($user));

        // Auth::login($user);

        // return redirect(RouteServiceProvider::HOME);
    }

    public function register(Request $request)
    {
        $validate = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['required', 'string', 'max:20'],
            'city_id' => ['required', 'exists:cities,id'],
            'document' => ['required', 'string', 'max:255', 'unique:users'],
            'terms_accepted' => ['required', 'accepted'],
        ]);

        // Remove terms_accepted from data to be saved (we don't need to store it as true/false)
        unset($validate['terms_accepted']);

        $validate['password'] = Hash::make($validate['password']);
        $validate['status_id'] = User::PENDING; // Set as pending until admin approval

        // Try to get additional data from external system
        $document = $validate['document'];
        try {
            $data = UserRepository::getCustomRuteroId($document);

            if ($data && isset($data['name'])) {
                // If external data exists, you might want to merge or validate against it
                // For now, we'll use the user-provided name
            }
        } catch (\Exception $e) {
            // If external service is unavailable, continue with user-provided data
            Log::warning('External service unavailable during registration', ['document' => $document]);
        }

        $user = User::create($validate);

        // If external route data exists, create zones
        if (isset($data) && isset($data['routes'])) {
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

        event(new Registered($user));

        // Don't auto-login, redirect to a pending approval page
        return redirect()->route('form')->with('success', 'Registro exitoso. Tu cuenta está pendiente de aprobación por parte del equipo de Tuti. Te contactaremos pronto.');
    }
}
