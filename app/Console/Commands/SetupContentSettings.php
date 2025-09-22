<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class SetupContentSettings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'content:setup';

    /**
     * The console command description.
     */
    protected $description = 'Setup content settings for Terms, Privacy Policy and FAQ pages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up content page settings...');

        // Terms and Conditions
        Setting::firstOrCreate(
            ['key' => 'terms_conditions_content'],
            [
                'name' => 'Términos y Condiciones',
                'value' => '<h2>Términos y Condiciones</h2>
<p>Bienvenido a Tuti. Estos términos y condiciones describen las reglas y regulaciones para el uso del sitio web de Tuti.</p>

<h3>1. Términos</h3>
<p>Al acceder a este sitio web, asumimos que aceptas estos términos y condiciones. No continúes usando Tuti si no aceptas todos los términos y condiciones establecidos en esta página.</p>

<h3>2. Uso de Licencias</h3>
<p>A menos que se indique lo contrario, Tuti y/o sus licenciantes poseen los derechos de propiedad intelectual de todo el material en Tuti. Todos los derechos de propiedad intelectual están reservados.</p>

<h3>3. Restricciones</h3>
<p>Está específicamente restringido:</p>
<ul>
<li>Publicar cualquier material del sitio web en cualquier otro medio</li>
<li>Vender, sublicenciar y/o comercializar cualquier material del sitio web</li>
<li>Usar este sitio web de cualquier manera que sea o pueda ser perjudicial para este sitio web</li>
<li>Usar este sitio web de cualquier manera que impacte el acceso de los usuarios a este sitio web</li>
</ul>

<h3>4. Privacidad</h3>
<p>Su privacidad es importante para nosotros. Nuestra Política de Privacidad explica cómo recopilamos, usamos y protegemos su información cuando usa nuestro servicio.</p>

<h3>5. Limitación de Responsabilidad</h3>
<p>En ningún caso Tuti, ni ninguno de sus directores, empleados o agentes, serán responsables de cualquier daño que surja del uso o la imposibilidad de usar los materiales en el sitio web de Tuti.</p>',
                'show' => true,
            ]
        );

        // Privacy Policy
        Setting::firstOrCreate(
            ['key' => 'privacy_policy_content'],
            [
                'name' => 'Políticas de Privacidad',
                'value' => '<h2>Política de Privacidad</h2>
<p>En Tuti, accesible desde nuestro sitio web, una de nuestras principales prioridades es la privacidad de nuestros visitantes. Este documento de Política de Privacidad contiene tipos de información que es recopilada y registrada por Tuti y cómo la usamos.</p>

<h3>1. Información que Recopilamos</h3>
<p>La información personal que nos proporciona incluye:</p>
<ul>
<li>Nombre y apellidos</li>
<li>Dirección de correo electrónico</li>
<li>Número de teléfono</li>
<li>Dirección de envío</li>
<li>Información de facturación</li>
</ul>

<h3>2. Cómo Usamos su Información</h3>
<p>Usamos la información que recopilamos de varias maneras, incluyendo:</p>
<ul>
<li>Proporcionar, operar y mantener nuestro sitio web</li>
<li>Mejorar, personalizar y expandir nuestro sitio web</li>
<li>Entender y analizar cómo usa nuestro sitio web</li>
<li>Desarrollar nuevos productos, servicios, características y funcionalidades</li>
<li>Comunicarnos con usted para brindar servicio al cliente y actualizaciones</li>
<li>Enviarle correos electrónicos promocionales</li>
<li>Encontrar y prevenir fraudes</li>
</ul>

<h3>3. Cookies</h3>
<p>Al igual que cualquier otro sitio web, Tuti utiliza "cookies". Estas cookies se usan para almacenar información, incluidas las preferencias de los visitantes y las páginas del sitio web a las que el visitante accedió o visitó.</p>

<h3>4. Seguridad de Datos</h3>
<p>La seguridad de sus datos es importante para nosotros, pero recuerde que ningún método de transmisión por Internet o método de almacenamiento electrónico es 100% seguro.</p>

<h3>5. Contacto</h3>
<p>Si tiene alguna pregunta sobre esta Política de Privacidad, puede contactarnos usando la información proporcionada en nuestra página de contacto.</p>',
                'show' => true,
            ]
        );

        // FAQ
        Setting::firstOrCreate(
            ['key' => 'faq_content'],
            [
                'name' => 'Preguntas Frecuentes',
                'value' => '<h2>Preguntas Frecuentes</h2>
<p>Aquí encontrarás respuestas a las preguntas más comunes sobre nuestros productos y servicios.</p>

<h3>¿Cómo puedo realizar un pedido?</h3>
<p>Para realizar un pedido, simplemente navega por nuestro catálogo, selecciona los productos que deseas, agrégalos al carrito y procede al checkout. Necesitarás crear una cuenta o iniciar sesión para completar tu pedido.</p>

<h3>¿Qué métodos de pago aceptan?</h3>
<p>Aceptamos varios métodos de pago incluyendo tarjetas de crédito y débito (Visa, MasterCard, American Express), transferencias bancarias y pagos en efectivo contra entrega (sujeto a disponibilidad en tu zona).</p>

<h3>¿Cuánto tiempo tarda la entrega?</h3>
<p>Los tiempos de entrega varían según tu ubicación y el tipo de producto. Generalmente, las entregas en Bogotá toman de 1-2 días hábiles, mientras que en otras ciudades pueden tomar de 2-5 días hábiles.</p>

<h3>¿Puedo cambiar o cancelar mi pedido?</h3>
<p>Puedes cambiar o cancelar tu pedido dentro de las primeras 2 horas después de haberlo realizado. Después de este tiempo, el pedido entra en proceso de preparación y no puede ser modificado.</p>

<h3>¿Ofrecen devoluciones?</h3>
<p>Sí, aceptamos devoluciones dentro de los 30 días posteriores a la entrega, siempre que el producto esté en su estado original y con su empaque. Los costos de envío de devolución corren por cuenta del cliente.</p>

<h3>¿Cómo puedo rastrear mi pedido?</h3>
<p>Una vez que tu pedido sea despachado, recibirás un correo electrónico con la información de seguimiento. También puedes consultar el estado de tu pedido iniciando sesión en tu cuenta.</p>

<h3>¿Tienen tienda física?</h3>
<p>Actualmente operamos únicamente en línea. No tenemos tiendas físicas, pero ofrecemos entregas a domicilio en las principales ciudades del país.</p>

<h3>¿Cómo puedo contactar al servicio al cliente?</h3>
<p>Puedes contactarnos a través de nuestra página de contacto, por correo electrónico o teléfono. Nuestro horario de atención es de lunes a viernes de 8:00 AM a 6:00 PM.</p>

<h3>¿Los precios incluyen IVA?</h3>
<p>Sí, todos los precios mostrados en nuestro sitio web incluyen IVA. No hay costos adicionales ocultos.</p>

<h3>¿Puedo comprar al por mayor?</h3>
<p>Sí, ofrecemos precios especiales para compras al por mayor. Contacta a nuestro equipo de ventas para obtener una cotización personalizada.</p>',
                'show' => true,
            ]
        );

        $this->info('Content settings created successfully!');
        $this->info('You can now edit these contents from the admin panel under Settings.');

        return 0;
    }
}
