<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class TronexMigrationController extends Controller
{
    private const VERIFY_SESSION_KEY = 'tronex_migration_verify';
    private const FAIL_SESSION_KEY = 'tronex_migration_failures';
    private const VERIFY_TTL_MINUTES = 10;
    private const MAX_FAILURES = 5;
    private const FAILURE_WINDOW_MINUTES = 30;

    /**
     * AJAX: Two-step Tronex migration flow.
     * Step A: document lookup.
     * Step B: phone verification.
     */
    public function migrate(Request $request)
    {
        $request->validate([
            'document' => ['required', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $document = $this->normalizeDocument((string) $request->document);
        if ($document === '') {
            return response()->json([
                'success' => false,
                'message' => 'Ingresa un número de cédula válido.',
            ], 422);
        }

        if ($this->isRateLimited($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Demasiados intentos de validación. Inténtalo más tarde o contáctanos por nuestros canales oficiales.',
            ], 429);
        }

        $phone = $this->normalizePhone((string) $request->input('phone', ''));

        if ($phone === '') {
            return $this->startPhoneVerification($request, $document);
        }

        return $this->completePhoneVerification($request, $document, $phone);
    }

    private function startPhoneVerification(Request $request, string $document)
    {
        $existingUser = User::where('document', $document)->first();

        if ($existingUser && !$existingUser->tronex_migration_pending) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes una cuenta en Tuti. Ingresa con tu correo y contraseña.',
            ], 422);
        }

        $data = UserRepository::getCustomRuteroId($document);
        if (!$data) {
            $this->recordFailure($request, $document);

            return response()->json([
                'success' => false,
                'message' => 'No encontramos tu cédula en el sistema Tronex. Verifica el número o regístrate como nuevo cliente.',
            ], 422);
        }

        $storedPhone = $this->resolveStoredPhone($existingUser, $data);
        if ($storedPhone === null) {
            $this->recordFailure($request, $document);
            Log::warning('Tronex migration blocked: no phone in stored data', [
                'document' => $document,
                'existing_user_id' => $existingUser?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No pudimos validar tu identidad con la información disponible. Por favor contáctanos por nuestros canales oficiales.',
            ], 422);
        }

        $verificationContext = [
            'document' => $document,
            'existing_user_id' => $existingUser?->id,
            'name' => $data['name'] ?? 'Cliente',
            'routes' => collect($data['routes'] ?? [])->values()->all(),
            'stored_phone' => $storedPhone,
            'created_at' => now()->toIso8601String(),
        ];

        $request->session()->put(self::VERIFY_SESSION_KEY, $verificationContext);

        return response()->json([
            'success' => true,
            'requires_phone_verification' => true,
            'message' => 'Ingresa tu número de celular para validar tu identidad.',
        ]);
    }

    private function completePhoneVerification(Request $request, string $document, string $inputPhone)
    {
        $context = $request->session()->get(self::VERIFY_SESSION_KEY);

        if (!$this->isValidVerificationContext($context, $document)) {
            $this->recordFailure($request, $document);

            return response()->json([
                'success' => false,
                'message' => 'La validación expiró. Vuelve a ingresar tu cédula para continuar.',
            ], 422);
        }

        if (!$this->phonesMatchCountryTolerant($inputPhone, (string) $context['stored_phone'])) {
            $this->recordFailure($request, $document);
            $request->session()->forget(self::VERIFY_SESSION_KEY);

            Log::warning('Tronex migration blocked: phone mismatch', [
                'document' => $document,
                'existing_user_id' => $context['existing_user_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'verification_failed' => true,
                'message' => 'No pudimos validar tu identidad con la información disponible. Por favor contáctanos por nuestros canales oficiales.',
            ], 422);
        }

        $user = null;
        if (!empty($context['existing_user_id'])) {
            $user = User::find((int) $context['existing_user_id']);
        }

        if (!$user) {
            $user = User::create([
                'name' => $context['name'] ?? 'Cliente',
                'email' => 'tronex.' . time() . '@tuti.com',
                'document' => $document,
                'password' => Str::random(12),
                'status_id' => User::PENDING,
                'tronex_migration_pending' => true,
            ]);

            foreach (($context['routes'] ?? []) as $route) {
                $user->zones()->create([
                    'route' => $route['route'] ?? null,
                    'zone' => $route['zone'] ?? null,
                    'day' => $route['day'] ?? null,
                    'address' => $route['address'] ?? null,
                    'code' => $route['code'] ?? null,
                ]);
            }
        }

        Auth::login($user);
        $request->session()->forget(self::VERIFY_SESSION_KEY);

        return response()->json([
            'success' => true,
            'verified_redirect' => route('tronex.complete-profile'),
            'redirect' => route('tronex.complete-profile'),
        ]);
    }

    private function normalizeDocument(string $document): string
    {
        return trim(preg_replace('/\D/', '', $document));
    }

    private function normalizePhone(string $phone): string
    {
        return trim(preg_replace('/\D/', '', $phone));
    }

    private function resolveStoredPhone(?User $user, array $ruteroData): ?string
    {
        $routeRows = collect($ruteroData['routes'] ?? []);
        $firstMobile = $this->firstNonEmptyPhone($routeRows->pluck('mobile_phone')->all());
        $firstPhone = $this->firstNonEmptyPhone($routeRows->pluck('phone')->all());
        $firstWhatsapp = $this->firstNonEmptyPhone($routeRows->pluck('whatsapp')->all());

        // Prefer Tronex rutero data over local user columns so sync remains canonical.
        $candidates = [
            $firstMobile,
            $firstPhone,
            $firstWhatsapp,
            $user?->mobile_phone,
            $user?->phone,
            $user?->whatsapp,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePhone((string) $candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function firstNonEmptyPhone(array $phones): ?string
    {
        foreach ($phones as $phone) {
            $normalized = $this->normalizePhone((string) $phone);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function phoneVariants(string $phone): array
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return [];
        }

        $variants = [$normalized];
        if (str_starts_with($normalized, '57') && strlen($normalized) > 10) {
            $variants[] = substr($normalized, 2);
        }
        if (str_starts_with($normalized, '0057') && strlen($normalized) > 12) {
            $variants[] = substr($normalized, 4);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function phonesMatchCountryTolerant(string $inputPhone, string $storedPhone): bool
    {
        $inputVariants = $this->phoneVariants($inputPhone);
        $storedVariants = $this->phoneVariants($storedPhone);

        if (empty($inputVariants) || empty($storedVariants)) {
            return false;
        }

        return !empty(array_intersect($inputVariants, $storedVariants));
    }

    private function isValidVerificationContext($context, string $document): bool
    {
        if (!is_array($context)) {
            return false;
        }

        if (($context['document'] ?? null) !== $document) {
            return false;
        }

        $createdAt = $context['created_at'] ?? null;
        if (!$createdAt) {
            return false;
        }

        return now()->diffInMinutes(Carbon::parse($createdAt)) <= self::VERIFY_TTL_MINUTES;
    }

    private function isRateLimited(Request $request): bool
    {
        $state = $request->session()->get(self::FAIL_SESSION_KEY);
        if (!is_array($state)) {
            return false;
        }

        $firstAt = $state['first_at'] ?? null;
        if (!$firstAt || now()->diffInMinutes(Carbon::parse($firstAt)) > self::FAILURE_WINDOW_MINUTES) {
            $request->session()->forget(self::FAIL_SESSION_KEY);

            return false;
        }

        return (int) ($state['count'] ?? 0) >= self::MAX_FAILURES;
    }

    private function recordFailure(Request $request, string $document): void
    {
        $state = $request->session()->get(self::FAIL_SESSION_KEY, [
            'count' => 0,
            'first_at' => now()->toIso8601String(),
        ]);

        if (now()->diffInMinutes(Carbon::parse($state['first_at'] ?? now()->toIso8601String())) > self::FAILURE_WINDOW_MINUTES) {
            $state = ['count' => 0, 'first_at' => now()->toIso8601String()];
        }

        $state['count'] = (int) ($state['count'] ?? 0) + 1;
        $request->session()->put(self::FAIL_SESSION_KEY, $state);

        Log::warning('Tronex migration failure recorded', [
            'document' => $document,
            'count' => $state['count'],
            'window_minutes' => self::FAILURE_WINDOW_MINUTES,
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
