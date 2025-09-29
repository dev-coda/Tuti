<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Order Status Templates
            [
                'name' => 'Order Status - Pending',
                'slug' => 'order_status_pending',
                'subject' => 'Tu pedido #{order_id} está pendiente de procesamiento',
                'type' => EmailTemplate::TYPE_ORDER_STATUS,
                'body' => $this->getOrderStatusPendingBody(),
                'variables' => ['order_id', 'order_status', 'customer_name', 'customer_email', 'order_total', 'order_date', 'delivery_date', 'tracking_url'],
            ],
            [
                'name' => 'Order Status - Processed',
                'slug' => 'order_status_processed',
                'subject' => 'Tu pedido #{order_id} ha sido procesado',
                'type' => EmailTemplate::TYPE_ORDER_STATUS,
                'body' => $this->getOrderStatusProcessedBody(),
                'variables' => ['order_id', 'order_status', 'customer_name', 'customer_email', 'order_total', 'order_date', 'delivery_date', 'tracking_url'],
            ],
            [
                'name' => 'Order Status - Shipped',
                'slug' => 'order_status_shipped',
                'subject' => 'Tu pedido #{order_id} ha sido enviado',
                'type' => EmailTemplate::TYPE_ORDER_STATUS,
                'body' => $this->getOrderStatusShippedBody(),
                'variables' => ['order_id', 'order_status', 'customer_name', 'customer_email', 'order_total', 'order_date', 'delivery_date', 'tracking_url'],
            ],
            [
                'name' => 'Order Status - Delivered',
                'slug' => 'order_status_delivered',
                'subject' => 'Tu pedido #{order_id} ha sido entregado',
                'type' => EmailTemplate::TYPE_ORDER_STATUS,
                'body' => $this->getOrderStatusDeliveredBody(),
                'variables' => ['order_id', 'order_status', 'customer_name', 'customer_email', 'order_total', 'order_date', 'delivery_date', 'tracking_url'],
            ],
            [
                'name' => 'Order Status - Cancelled',
                'slug' => 'order_status_cancelled',
                'subject' => 'Tu pedido #{order_id} ha sido cancelado',
                'type' => EmailTemplate::TYPE_ORDER_STATUS,
                'body' => $this->getOrderStatusCancelledBody(),
                'variables' => ['order_id', 'order_status', 'customer_name', 'customer_email', 'order_total', 'order_date', 'delivery_date', 'tracking_url'],
            ],

            // Order Confirmation Template
            [
                'name' => 'Order Confirmation',
                'slug' => 'order_confirmation',
                'subject' => 'Confirmación de tu pedido #{order_id}',
                'type' => EmailTemplate::TYPE_ORDER_CONFIRMATION,
                'body' => $this->getOrderConfirmationBody(),
                'variables' => ['order_id', 'customer_name', 'customer_email', 'order_total', 'order_products', 'delivery_date', 'order_url'],
            ],

            // User Registration Template
            [
                'name' => 'User Registration Welcome',
                'slug' => 'user_registration',
                'subject' => 'Bienvenido a Tuti - {customer_name}',
                'type' => EmailTemplate::TYPE_USER_REGISTRATION,
                'body' => $this->getUserRegistrationBody(),
                'variables' => ['customer_name', 'customer_email', 'activation_link', 'login_url'],
            ],

            // Contact Form Admin Notification
            [
                'name' => 'Contact Form Admin Notification',
                'slug' => 'contact_form',
                'subject' => 'Nuevo contacto registrado - {contact_name}',
                'type' => EmailTemplate::TYPE_CONTACT_FORM,
                'body' => $this->getContactFormBody(),
                'variables' => ['contact_name', 'contact_email', 'contact_phone', 'business_name', 'city', 'nit', 'message', 'contact_date'],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }

    private function getOrderStatusPendingBody()
    {
        return '<h2>Estado del pedido actualizado</h2>
        <p>Hola {customer_name},</p>
        <p>Tu pedido #{order_id} está actualmente <strong>{order_status}</strong>.</p>
        <p>Te notificaremos cuando cambie el estado de tu pedido.</p>
        <p><strong>Detalles del pedido:</strong></p>
        <ul>
            <li>Total: ${order_total}</li>
            <li>Fecha del pedido: {order_date}</li>
            <li>Fecha de entrega estimada: {delivery_date}</li>
        </ul>
        <p>Puedes seguir tu pedido en: <a href="{tracking_url}">Ver pedido</a></p>';
    }

    private function getOrderStatusProcessedBody()
    {
        return '<h2>Pedido procesado exitosamente</h2>
        <p>Hola {customer_name},</p>
        <p>¡Buenas noticias! Tu pedido #{order_id} ha sido <strong>{order_status}</strong> y está siendo preparado para envío.</p>
        <p><strong>Detalles del pedido:</strong></p>
        <ul>
            <li>Total: ${order_total}</li>
            <li>Fecha del pedido: {order_date}</li>
            <li>Fecha de entrega estimada: {delivery_date}</li>
        </ul>
        <p>Puedes seguir tu pedido en: <a href="{tracking_url}">Ver pedido</a></p>';
    }

    private function getOrderStatusShippedBody()
    {
        return '<h2>Tu pedido ha sido enviado</h2>
        <p>Hola {customer_name},</p>
        <p>¡Tu pedido #{order_id} ha sido <strong>{order_status}</strong>!</p>
        <p>Ya está en camino hacia ti. Recibirás una notificación cuando sea entregado.</p>
        <p><strong>Detalles del pedido:</strong></p>
        <ul>
            <li>Total: ${order_total}</li>
            <li>Fecha del pedido: {order_date}</li>
            <li>Fecha de entrega estimada: {delivery_date}</li>
        </ul>
        <p>Puedes seguir tu pedido en: <a href="{tracking_url}">Ver pedido</a></p>';
    }

    private function getOrderStatusDeliveredBody()
    {
        return '<h2>¡Pedido entregado!</h2>
        <p>Hola {customer_name},</p>
        <p>¡Tu pedido #{order_id} ha sido <strong>{order_status}</strong> exitosamente!</p>
        <p>Esperamos que disfrutes de tu compra. ¡Gracias por elegir Tuti!</p>
        <p><strong>Detalles del pedido:</strong></p>
        <ul>
            <li>Total: ${order_total}</li>
            <li>Fecha del pedido: {order_date}</li>
            <li>Fecha de entrega: {delivery_date}</li>
        </ul>
        <p>¿Necesitas ayuda con algo más? <a href="mailto:soporte@tuti.com">Contáctanos</a></p>';
    }

    private function getOrderStatusCancelledBody()
    {
        return '<h2>Pedido cancelado</h2>
        <p>Hola {customer_name},</p>
        <p>Tu pedido #{order_id} ha sido <strong>{order_status}</strong>.</p>
        <p>Si tienes alguna pregunta sobre esta cancelación o necesitas ayuda con algo más, no dudes en contactarnos.</p>
        <p><strong>Detalles del pedido:</strong></p>
        <ul>
            <li>Total: ${order_total}</li>
            <li>Fecha del pedido: {order_date}</li>
        </ul>
        <p>¿Necesitas ayuda? <a href="mailto:soporte@tuti.com">Contáctanos</a></p>';
    }

    private function getOrderConfirmationBody()
    {
        return '<h2>Confirmación de pedido</h2>
        <p>Hola {customer_name},</p>
        <p>¡Gracias por tu compra! Hemos recibido tu pedido #{order_id} y está siendo procesado.</p>
        <p><strong>Resumen de tu pedido:</strong></p>
        <table border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                </tr>
            </thead>
            <tbody>
                {order_products}
            </tbody>
        </table>
        <p><strong>Total: ${order_total}</strong></p>
        <p><strong>Fecha de entrega estimada: {delivery_date}</strong></p>
        <p>Puedes ver los detalles completos en: <a href="{order_url}">Ver pedido</a></p>';
    }

    private function getUserRegistrationBody()
    {
        return '<h2>¡Bienvenido a Tuti!</h2>
        <p>Hola {customer_name},</p>
        <p>¡Gracias por registrarte en Tuti! Tu cuenta ha sido creada exitosamente.</p>
        <p>Para activar tu cuenta y comenzar a disfrutar de todos nuestros beneficios, haz clic en el siguiente enlace:</p>
        <p><a href="{activation_link}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Activar mi cuenta</a></p>
        <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
        <p>{activation_link}</p>
        <p>Una vez activada tu cuenta, podrás:</p>
        <ul>
            <li>Hacer pedidos en línea</li>
            <li>Seguir el estado de tus pedidos</li>
            <li>Acceder a ofertas exclusivas</li>
            <li>Gestionar tu información personal</li>
        </ul>
        <p>¿Tienes alguna pregunta? <a href="mailto:soporte@tuti.com">Contáctanos</a></p>';
    }

    private function getContactFormBody()
    {
        return '<h2>Nuevo contacto registrado</h2>
        <p>Se ha registrado un nuevo contacto en el sitio web:</p>
        <table border="1" style="border-collapse: collapse; width: 100%;">
            <tr>
                <td><strong>Nombre:</strong></td>
                <td>{contact_name}</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td>{contact_email}</td>
            </tr>
            <tr>
                <td><strong>Teléfono:</strong></td>
                <td>{contact_phone}</td>
            </tr>
            <tr>
                <td><strong>Empresa:</strong></td>
                <td>{business_name}</td>
            </tr>
            <tr>
                <td><strong>Ciudad:</strong></td>
                <td>{city}</td>
            </tr>
            <tr>
                <td><strong>NIT:</strong></td>
                <td>{nit}</td>
            </tr>
            <tr>
                <td><strong>Fecha de registro:</strong></td>
                <td>{contact_date}</td>
            </tr>
        </table>
        <p>Este es un mensaje automático del sistema de Tuti.</p>';
    }
}
