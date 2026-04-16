@extends('layouts.email')

@section('preheader')
{{ $preheader ?? '' }}
@endsection

@section('content')

<div class="main-content">
    <table class="box" cellpadding="0" cellspacing="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; border-collapse: collapse; width: 100%; border-radius: 8px; -webkit-box-shadow: 0 2px 8px rgba(24, 15, 9, 0.08); box-shadow: 0 2px 8px rgba(24, 15, 9, 0.08); border: 1px solid #e7e6e4;" bgcolor="#ffffff">
        <tr>
            <td style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <table cellpadding="0" cellspacing="0" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; border-collapse: collapse; width: 100%;">
                    <tr>
                        <td class="content email-template-content" style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px 48px; color: #180F09;">
                            {!! $body !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

@endsection
