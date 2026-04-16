@extends('layouts.email')

@section('preheader')

{{config('app.name')}}. Confirmación de la order #{{$order->id}} gracias por su compra
@endsection

@section('content')



<table cellpadding="0" cellspacing="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">
    <tr>
        <td class="py-lg" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding-top: 24px; padding-bottom: 24px;">
            <table cellspacing="0" cellpadding="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">
                <tr>
                    <td style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;">
                        <a href="{{route('home')}}" style="color: #EE4E34; text-decoration: none; text-align:center">
                            <img src="{{asset('img/tuti.png')}}" width="100" alt="" style="line-height: 100%; outline: none; text-decoration: none; vertical-align: baseline; font-size: 0; border: 0 none;" />
                        </a>
                    </td>
                    <td class="text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;" align="right">
                        
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>


<div class="main-content">
    <table class="box" cellpadding="0" cellspacing="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; border-radius: 3px; -webkit-box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05); box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0;" bgcolor="#ffffff">
        <tr>
            <td style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;">
                <table cellpadding="0" cellspacing="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">
                    <tr>
                        <td class="content pb-0" align="center" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px 0;">
                            <table class="icon icon-lg bg-blue" cellspacing="0" cellpadding="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 0; border-collapse: separate; width: 72px; border-radius: 50%; line-height: 100%; font-weight: 300; height: 72px; font-size: 48px; text-align: center; color: #ffffff;" bgcolor="#EE4E34">
                                <tr>
                                    <td valign="middle" align="center" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;">
                                        <img src="{{asset('img/email/icons-white-shopping-cart.png')}}" class=" va-middle" width="40" height="40" alt="shopping-cart" style="line-height: 100%; border: 0 none; outline: none; text-decoration: none; vertical-align: middle; font-size: 0; display: block; width: 40px; height: 40px;" />
                                    </td>
                                </tr>
                            </table>
                            <h1 class="text-center m-0 mt-md" style="font-weight: 300; font-size: 28px; line-height: 130%; margin: 16px 0 0;" align="center">Resumen del pedido</h1>
                        </td>
                    </tr>
                    <tr>
                        <td class="content" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px;">
                            <p style="margin: 0 0 1em;">{{$order->user->name}},</p>
                            <p style="margin: 0 0 1em;">Tu pedido ha sido realizado con éxito. Encontrarás todos los detalles sobre tu pedido a continuación y te enviaremos un correo electrónico de confirmación de envío lo antes posible. Si tienes alguna pregunta o sugerencia
                                <a href="#" style="color: #4a84ed; text-decoration: underline;">envíanos un correo electrónico</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td class="content pt-0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 0 48px 40px;">
                            <h4 style="font-weight: 600; font-size: 16px; margin: 0 0 .5em;">Aquí tienes lo que ordenaste:</h4>
                            <table class="table" cellspacing="0" cellpadding="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">
                                <tr>
                                    <th colspan="2" style="text-transform: uppercase; font-weight: 600; color: #9eb0b7; font-size: 12px; padding: 0 0 4px;"></th>
                                    <th style="text-transform: uppercase; font-weight: 600; color: #9eb0b7; font-size: 12px; padding: 0 0 4px;">Cantidad</th>
                                    <th class="text-right" style="text-transform: uppercase; font-weight: 600; color: #9eb0b7; font-size: 12px; padding: 0 0 4px;" align="right">Precio</th>
                                </tr>
                                @foreach ($order->products as $product )
                                    <tr>
                                        <td class="pr-0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 12px 4px 0;">
                                            <a href="{{route('product', $product->product->slug)}}" style="color: #4a84ed; text-decoration: none;">
                                                <img src="{{asset('storage/'.$product->product->image)}}" class=" rounded" width="64" height="64" alt="" style="line-height: 100%; outline: none; text-decoration: none; vertical-align: baseline; font-size: 0; border-radius: 3px; border: 0 none;" />
                                            </a>
                                        </td>
                                        <td class="pl-md w-100p" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; width: 100%; padding: 4px 12px;">
                                            <strong style="font-weight: 600;">{{$product->product->name}}</strong><br />
                                            @if($product->variation)
                                            <span class="text-muted" style="color: #9eb0b7;">{{$product->variation->name}}  {{$product->item->name}}</span>
                                            @endif
                                            
                                        </td>
                                        <td class="text-center" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 12px;" align="center">{{$product->quantity}}</td>
                                        <td class="text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 12px;" align="right">${{number_format($product->price, 2)}}</td>
                                    </tr>

                                 


                                @endforeach
                                <tr>
                                    <td colspan="3" class="border-top text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-top-width: 1px; border-top-color: #f0f0f0; border-top-style: solid; padding: 4px 12px 4px 0;" align="right">Subtotal</td>
                                    <td class="border-top text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-top-width: 1px; border-top-color: #f0f0f0; border-top-style: solid; padding: 4px 0 4px 12px;" align="right"> ${{number_format(($order->total+$order->discount), 0)}}</td>
                                </tr>
                                @if ($order->discount)
                                    <tr>
                                        <td colspan="3" class="text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 12px 4px 0;" align="right">Descuento
                                        </td>
                                        <td class="text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 12px;" align="right">${{number_format($order->discount, 0)}}</td>
                                    </tr>
                                        
                                @endif
                               


                            {{-- <tr>
                                <td colspan="3" class="text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 12px 4px 0;" align="right">Envio
                                    <br><small>Regular</small>
                                </td>
                                <td class="text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 12px;" align="right">
                                    ${{number_format($order->delivery, 2)}}
                                </td>
                            </tr> --}}


                               
                            <tr>
                                <td colspan="3" class="text-right font-strong h3 m-0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; font-weight: 600; font-size: 20px; line-height: 120%; margin: 0; padding: 4px 12px 4px 0;" align="right">Total</td>
                                <td class="font-strong h3 m-0 text-right" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; font-weight: 600; font-size: 20px; line-height: 120%; margin: 0; padding: 4px 0 4px 12px;" align="right"> 
                                   ${{number_format(($order->total+$order->discount) - $order->discount)}}
                                </td>
                            </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="content border-top" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-top-width: 1px; border-top-color: #f0f0f0; border-top-style: solid; padding: 40px 48px;">
                            <table class="row mb-md" cellpadding="0" cellspacing="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; table-layout: fixed; margin-bottom: 16px;">
                                <tr>
                                    <td class="col" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;" valign="top">
                                        <h4 class="m-0" style="font-weight: 600; font-size: 16px; margin: 0;">Número de orden</h4>
                                        <div>#{{$order->id}}</div>
                                    </td>
                                    <td class="col" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;" valign="top">
                                       
                                    </td>
                                    <td class="col" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;" valign="top">
                                        <h4 class="mb-0" style="font-weight: 600; font-size: 16px; margin: 0;">Fecha de entrega</h4>
                                        <div>{{$order->delivery_date}}</div>
                                    </td>
                                </tr>
                            </table>
                            <table class="row" cellpadding="0" cellspacing="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; table-layout: fixed;">
                                <tr>
                                    <td class="col" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;" valign="top">
                                        <h4 class="m-0" style="font-weight: 600; font-size: 16px; margin: 0;">Dirección de envio</h4>
                                        <div>
                                            {{$order->user->name}}<br />
                                            {{$order->zone->address}}, {{$order->zone->zone}}<br />
                                            Celular: <strong>{{$order->user->phone}}</strong> <br>
                                            Email: <strong>{{$order->user->email}}</strong> <br>
                                            <br>
                                        </div>
                                    </td>
                                    <td class="col" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;" valign="top">
                                       
                                    </td>
                                   
                                </tr>
                               
                            </table>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</div>






@endsection