@extends('layouts.email')

@section('preheader')
Tu código de verificación para ingresar a {{ config('app.name') }}
@endsection

@section('content')

<table cellpadding="0" cellspacing="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">
    <tr>
        <td class="py-lg" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding-top: 24px; padding-bottom: 24px;">
            <table cellspacing="0" cellpadding="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">
                <tr>
                    <td style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;">
                        <a href="{{ route('home') }}" style="color: #467fcf; text-decoration: none; text-align:center">
                            <img src="{{ asset('img/tuti.png') }}" width="100" alt="{{ config('app.name') }}" style="line-height: 100%; outline: none; text-decoration: none; vertical-align: baseline; font-size: 0; border: 0 none;" />
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="main-content">
    <table class="box" cellpadding="0" cellspacing="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; border-radius: 3px; -webkit-box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05); box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0;" bgcolor="#ffffff">
        <tr>
            <td style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif;">
                <table cellpadding="0" cellspacing="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">
                    <tr>
                        <td class="content pb-0" align="center" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px 0;">
                            <table class="icon icon-lg bg-blue" cellspacing="0" cellpadding="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 0; border-collapse: separate; width: 72px; border-radius: 50%; line-height: 100%; font-weight: 300; height: 72px; font-size: 48px; text-align: center; color: #ffffff;" bgcolor="#EE4E34">
                                <tr>
                                    <td valign="middle" align="center" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; font-size: 36px; color: #ffffff;">
                                        ✉
                                    </td>
                                </tr>
                            </table>
                            <h1 class="text-center m-0 mt-md" style="font-weight: 300; font-size: 28px; line-height: 130%; margin: 16px 0 0;" align="center">Código de verificación</h1>
                        </td>
                    </tr>
                    <tr>
                        <td class="content" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 24px 48px;">
                            <p style="margin: 0 0 1em; text-align: center;">
                                Hemos recibido una solicitud de ingreso sin contraseña para tu cuenta. Usa el siguiente código para verificar tu identidad:
                            </p>
                            <table cellspacing="0" cellpadding="0" style="border-collapse: collapse; width: 100%; margin: 24px 0;">
                                <tr>
                                    <td align="center">
                                        <div style="background-color: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; padding: 20px 40px; display: inline-block;">
                                            <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #333333; font-family: monospace;">{{ $magicCode->code }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 1em; text-align: center; color: #9eb0b7; font-size: 13px;">
                                Este código expira en <strong>10 minutos</strong>. Si no solicitaste este código, puedes ignorar este correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

@endsection
