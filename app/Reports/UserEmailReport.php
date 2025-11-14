<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserEmailReport
{
    /**
     * Generate user email report data
     */
    public function generate(): array
    {
        // Total registered users
        $totalUsers = User::count();

        // Total users with email field (not null and not empty)
        $usersWithEmail = User::whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        // Suspicious emails: @tuti.com or @tuti.com.co
        $suspiciousUsers = User::whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($query) {
                $query->where('email', 'like', '%@tuti.com')
                    ->orWhere('email', 'like', '%@tuti.com.co');
            })
            ->get();

        // Get detailed list of suspicious users
        $suspiciousUsersList = $suspiciousUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'document' => $user->document,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return [
            'total_users' => $totalUsers,
            'users_with_email' => $usersWithEmail,
            'suspicious_users_count' => $suspiciousUsers->count(),
            'suspicious_users' => $suspiciousUsersList,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get report name
     */
    public function getName(): string
    {
        return 'Reporte de Correos Electrónicos de Usuarios';
    }

    /**
     * Get report description
     */
    public function getDescription(): string
    {
        return 'Este reporte incluye estadísticas sobre los correos electrónicos de los usuarios registrados, incluyendo el total de usuarios, usuarios con correo electrónico y usuarios con correos sospechosos (@tuti.com o @tuti.com.co).';
    }
}

