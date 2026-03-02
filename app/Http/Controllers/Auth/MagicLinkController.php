<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLoginCodeMail;
use App\Models\MagicLoginCode;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\MailingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class MagicLinkController extends Controller
{
    /**
     * Send a magic login code to the user's email.
     */
    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));

        // Rate limiting: max 3 codes per email per 5 minutes
        $rateLimitKey = 'magic-code:' . $email;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Demasiados intentos. Por favor espera {$seconds} segundos.",
            ], 429);
        }

        // Check if user exists
        $user = User::where('email', $email)->first();
        if (!$user) {
            // Return success even if user doesn't exist to prevent email enumeration
            return response()->json([
                'success' => true,
                'message' => 'Si el correo está registrado, recibirás un código de verificación.',
            ]);
        }

        // Generate the code
        $magicCode = MagicLoginCode::generateFor($email);

        // Send the email using the MailingService infrastructure
        try {
            $mailingService = new MailingService();
            $mailingService->updateMailConfiguration();

            Mail::to($email)->send(new MagicLoginCodeMail($magicCode));

            Log::info("Magic login code sent to: {$email}");
        } catch (\Exception $e) {
            Log::error("Failed to send magic login code to {$email}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el código. Por favor intenta de nuevo.',
            ], 500);
        }

        RateLimiter::hit($rateLimitKey, 300); // 5 minutes

        return response()->json([
            'success' => true,
            'message' => 'Si el correo está registrado, recibirás un código de verificación.',
        ]);
    }

    /**
     * Verify the magic login code and authenticate the user.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $email = strtolower(trim($request->email));
        $code = $request->code;

        // Rate limiting: max 5 verify attempts per email per 5 minutes
        $rateLimitKey = 'magic-verify:' . $email;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Demasiados intentos. Por favor espera {$seconds} segundos.",
            ], 429);
        }

        // Find the valid code
        $magicCode = MagicLoginCode::findValidCode($email, $code);

        if (!$magicCode) {
            RateLimiter::hit($rateLimitKey, 300);
            return response()->json([
                'success' => false,
                'message' => 'Código inválido o expirado. Por favor solicita uno nuevo.',
            ], 422);
        }

        // Find the user
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró una cuenta con este correo.',
            ], 422);
        }

        // Mark the code as used
        $magicCode->markAsUsed();

        // Clear rate limiters
        RateLimiter::clear($rateLimitKey);
        RateLimiter::clear('magic-code:' . $email);

        // Log the user in
        Auth::login($user, true);

        // Regenerate session
        $request->session()->regenerate();

        // Determine redirect URL based on user role
        $redirectUrl = $user->hasRole('admin')
            ? route('dashboard')
            : RouteServiceProvider::HOME;

        Log::info("Magic link login successful for: {$email}");

        return response()->json([
            'success' => true,
            'message' => '¡Ingreso exitoso!',
            'redirect' => $redirectUrl,
        ]);
    }
}
