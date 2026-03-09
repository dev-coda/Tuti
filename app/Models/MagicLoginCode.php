<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MagicLoginCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    /**
     * Generate a new magic login code for the given email.
     */
    public static function generateFor(string $email): self
    {
        // Invalidate any existing unused codes for this email
        static::where('email', $email)
            ->where('used', false)
            ->update(['used' => true]);

        return static::create([
            'email' => $email,
            'code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
    }

    /**
     * Check if the code is valid (not expired and not used).
     */
    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    /**
     * Mark the code as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['used' => true]);
    }

    /**
     * Find a valid code for the given email.
     */
    public static function findValidCode(string $email, string $code): ?self
    {
        return static::where('email', $email)
            ->where('code', $code)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();
    }

    /**
     * Cleanup expired codes (can be called from a scheduled task).
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', Carbon::now())
            ->orWhere('used', true)
            ->where('created_at', '<', Carbon::now()->subDay())
            ->delete();
    }
}
