<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $identity->company_name }}</title></head>
<body style="margin:0;padding:0;background-color:#f3f4f6;color:#111827;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;"><tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border:1px solid #d1d5db;"><tr><td style="padding:24px;">
            @if($identity->imageUrl('logo'))
                <img src="{{ $identity->imageUrl('logo') }}" width="160" alt="{{ $identity->company_name }}" style="max-width:100%;height:auto;display:block;margin-bottom:16px;">
            @endif
            <p style="font-weight:bold;margin:0 0 24px;">{{ $identity->company_name }}</p>
            @yield('content')
        </td></tr></table>
    </td></tr></table>
</body>
</html>
