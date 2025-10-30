<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\User;
use App\Models\Contact;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class MailingService
{
    /**
     * Update mail configuration from database settings
     */
    public function updateMailConfiguration(): void
    {
        try {
            // Update mail driver
            $mailDriver = Setting::getByKeyWithDefault('mail_mailer', 'smtp');
            Config::set('mail.default', $mailDriver);

            // Update from address and name
            $fromAddress = Setting::getByKeyWithDefault('mail_from_address', 'noreply@tuti.com');
            $fromName = Setting::getByKeyWithDefault('mail_from_name', 'Tuti');
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName);

            // Only configure Mailgun if the package is available (check composer autoloader)
            $mailgunAvailable = false;
            try {
                // Check if Mailgun transport factory exists (with autoload)
                $mailgunAvailable = class_exists('Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory');
            } catch (\Exception $e) {
                // Mailgun package not available
                Log::debug("Mailgun package check failed: " . $e->getMessage());
                $mailgunAvailable = false;
            }

            if ($mailDriver === 'mailgun' && $mailgunAvailable) {
                $mailgunDomain = Setting::getByKey('mailgun_domain');
                $mailgunSecret = Setting::getByKey('mailgun_secret');
                $mailgunEndpoint = Setting::getByKeyWithDefault('mailgun_endpoint', 'api.mailgun.net');

                if ($mailgunDomain && $mailgunSecret) {
                    Config::set('mail.mailers.mailgun.domain', $mailgunDomain);
                    Config::set('mail.mailers.mailgun.secret', $mailgunSecret);
                    Config::set('mail.mailers.mailgun.endpoint', $mailgunEndpoint);
                    Config::set('services.mailgun.domain', $mailgunDomain);
                    Config::set('services.mailgun.secret', $mailgunSecret);
                    Config::set('services.mailgun.endpoint', $mailgunEndpoint);
                } else {
                    Log::warning("Mailgun selected but credentials missing. Domain: " . ($mailgunDomain ? 'set' : 'missing') . ", Secret: " . ($mailgunSecret ? 'set' : 'missing') . ". Falling back to log driver.");
                    // Fallback to log driver instead of throwing exception - allows app to run for development
                    Config::set('mail.default', 'log');
                }
            }

            // Update SMTP configuration
            $smtpHost = Setting::getByKeyWithDefault('smtp_host', 'smtp.mailgun.org');
            $smtpPort = Setting::getByKeyWithDefault('smtp_port', '587');
            $smtpUsername = Setting::getByKey('smtp_username');
            $smtpPassword = Setting::getByKey('smtp_password');
            $smtpEncryption = Setting::getByKeyWithDefault('smtp_encryption', 'tls');

            Config::set('mail.mailers.smtp.host', $smtpHost);
            Config::set('mail.mailers.smtp.port', (int)$smtpPort);
            Config::set('mail.mailers.smtp.encryption', $smtpEncryption);
            Config::set('mail.mailers.smtp.timeout', 30); // 30 second timeout

            if ($smtpUsername && $smtpPassword) {
                Config::set('mail.mailers.smtp.username', $smtpUsername);
                Config::set('mail.mailers.smtp.password', $smtpPassword);
            }

            // If Mailgun was requested but not available, fallback to log driver
            if ($mailDriver === 'mailgun' && !$mailgunAvailable) {
                Log::warning("Mailgun selected but package not installed. Falling back to log driver. To install: composer require symfony/mailgun-mailer symfony/http-client");
                Config::set('mail.default', 'log');
            }
        } catch (\Exception $e) {
            Log::error("Failed to update mail configuration: " . $e->getMessage());
            // Fallback to log driver for development/testing purposes
            Config::set('mail.default', 'log');
        }
    }

    /**
     * Send email using template
     */
    public function sendTemplateEmail(string $templateSlug, array $data, $recipient = null)
    {
        try {
            // Update mail configuration from database settings
            $this->updateMailConfiguration();

            $template = EmailTemplate::where('slug', $templateSlug)
                ->where('is_active', true)
                ->first();

            if (!$template) {
                Log::warning("Email template not found or inactive: {$templateSlug}");
                return false;
            }

            $processedContent = $template->replaceVariables($data);

            $emailData = [
                'subject' => $processedContent['subject'],
                'body' => $processedContent['body'],
                'template' => $template,
                'data' => $data,
            ];

            // Determine recipient
            if ($recipient) {
                $to = $recipient;
            } elseif (isset($data['customer_email'])) {
                $to = $data['customer_email'];
            } elseif (isset($data['contact_email'])) {
                $to = $data['contact_email'];
            } else {
                Log::error("No recipient email provided for template: {$templateSlug}");
                return false;
            }

            // Send the email
            Mail::raw($emailData['body'], function ($message) use ($emailData, $to) {
                $message->to($to)
                    ->subject($emailData['subject'])
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info("Email sent successfully: {$templateSlug} to {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to send email: {$templateSlug}", [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return false;
        }
    }

    /**
     * Send order status change email
     */
    public function sendOrderStatusEmail(Order $order, string $newStatus)
    {
        $templateSlug = "order_status_{$newStatus}";

        $data = [
            'order_id' => $order->id,
            'order_status' => $this->getStatusLabel($newStatus),
            'customer_name' => $order->user->name,
            'customer_email' => $order->user->email,
            'order_total' => number_format($order->total, 2),
            'order_date' => $order->created_at->format('d/m/Y'),
            'delivery_date' => $order->delivery_date ?? 'Pendiente',
            'tracking_url' => route('orders.show', $order->id),
        ];

        return $this->sendTemplateEmail($templateSlug, $data);
    }

    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmationEmail(Order $order)
    {
        $products = $order->products->map(function ($item) {
            return [
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => number_format($item->price, 2),
            ];
        })->toArray();

        $data = [
            'order_id' => $order->id,
            'customer_name' => $order->user->name,
            'customer_email' => $order->user->email,
            'order_total' => number_format($order->total, 2),
            'order_products' => $products,
            'delivery_date' => $order->delivery_date ?? 'Pendiente',
            'order_url' => route('orders.show', $order->id),
        ];

        return $this->sendTemplateEmail('order_confirmation', $data);
    }

    /**
     * Send user registration email
     */
    public function sendUserRegistrationEmail(User $user)
    {
        $activationToken = $this->generateActivationToken($user);

        $data = [
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'activation_link' => route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]),
            'login_url' => route('login'),
        ];

        return $this->sendTemplateEmail('user_registration', $data);
    }

    /**
     * Send contact form notification to admin
     */
    public function sendContactFormNotification(Contact $contact)
    {
        // Get admin emails from settings or use default
        $adminEmails = $this->getAdminEmails();

        $data = [
            'contact_name' => $contact->name,
            'contact_email' => $contact->email,
            'contact_phone' => $contact->phone,
            'business_name' => $contact->business_name,
            'city' => $contact->city->name ?? 'No especificada',
            'nit' => $contact->nit,
            'message' => 'Nuevo contacto registrado',
            'contact_date' => $contact->created_at->format('d/m/Y H:i'),
        ];

        $sent = false;
        foreach ($adminEmails as $adminEmail) {
            if ($this->sendTemplateEmail('contact_form', $data, $adminEmail)) {
                $sent = true;
            }
        }

        return $sent;
    }

    /**
     * Get status label for order status
     */
    private function getStatusLabel(string $status)
    {
        $labels = [
            'pending' => 'Pendiente',
            'processed' => 'Procesado',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Generate activation token for user
     */
    private function generateActivationToken(User $user)
    {
        // Generate a secure token for email verification
        return hash_hmac('sha256', $user->email . $user->created_at, config('app.key'));
    }

    /**
     * Get admin email addresses
     */
    private function getAdminEmails()
    {
        // You can customize this to get admin emails from settings
        // For now, return a default admin email
        return ['admin@tuti.com'];
    }
}
