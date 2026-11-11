<?php

namespace App\Services\Shipping;

use App\Models\Order;
use RuntimeException;
use Throwable;

/**
 * A shipping-quote failure with a classified, real cause.
 *
 * The generic "No se pudo cotizar el envío en este momento" told the shopper
 * (and support) nothing. Every failure path in CoordinadoraQuoteService now
 * throws this instead, carrying:
 *  - errorCode: stable machine code (e.g. missing_destination_dane)
 *  - failureCategory: who owns the fix — user_data (the shopper/client must
 *    update their delivery data), server_configuration (env in stage/prod),
 *    product_data (catalog weight/dimensions), external_service (Coordinadora
 *    itself), cart, or unknown.
 *  - requiresUserDataUpdate: only true for user_data, so the cart can toast
 *    "debes actualizar tus datos" exclusively when that is actually the cause.
 *  - diagnostics: non-secret context (zone, DANE codes, SKUs, HTTP status…)
 *    safe to print in the browser console and in laravel.log.
 */
class CoordinadoraQuoteException extends RuntimeException
{
    public function __construct(
        string $reason,
        public readonly string $errorCode,
        public readonly string $failureCategory,
        public readonly bool $requiresUserDataUpdate = false,
        public readonly array $diagnostics = [],
        public readonly ?string $userMessage = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($reason, 0, $previous);
    }

    public function toResponseArray(): array
    {
        return [
            'success' => false,
            'provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
            // Short message for the inline cart error; `reason` keeps the full
            // root-cause explanation for console/log diagnostics.
            'message' => $this->userMessage ?? 'No se pudo cotizar el envío en este momento.',
            'reason' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'failure_category' => $this->failureCategory,
            'requires_user_data_update' => $this->requiresUserDataUpdate,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
