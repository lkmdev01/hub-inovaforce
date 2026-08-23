<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f4f4f5;color:#18181b;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;">
                <tr><td style="padding:0 8px 20px;">
                    <table role="presentation" width="100%"><tr>
                        <td style="font-size:20px;font-weight:800;letter-spacing:-.4px;color:#18181b;">INOVA<span style="color:#7c3aed;">FORCE</span></td>
                        <td align="right" style="font-size:12px;color:#71717a;">Hub de clientes</td>
                    </tr></table>
                </td></tr>
                <tr><td style="overflow:hidden;border:1px solid #e4e4e7;border-radius:20px;background:#ffffff;box-shadow:0 8px 30px rgba(24,24,27,.06);">
                    <div style="height:6px;background:linear-gradient(90deg,#7c3aed,#4f46e5,#2563eb);"></div>
                    <div style="padding:42px 42px 36px;">
                        <div style="display:inline-block;margin-bottom:18px;border-radius:999px;background:#f5f3ff;padding:7px 11px;color:#6d28d9;font-size:11px;font-weight:800;letter-spacing:1px;">{{ $eyebrow }}</div>
                        <h1 style="margin:0 0 18px;color:#18181b;font-size:30px;line-height:1.2;letter-spacing:-.8px;">{{ $title }}</h1>
                        <p style="margin:0 0 12px;font-size:16px;line-height:1.65;color:#3f3f46;">{{ $greeting }}</p>
                        <p style="margin:0;font-size:16px;line-height:1.65;color:#52525b;">{{ $intro }}</p>

                        @if ($actionUrl)
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:30px 0;">
                                <tr><td style="border-radius:12px;background:#6d28d9;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">{{ $actionLabel }}</a>
                                </td></tr>
                            </table>
                        @endif

                        @foreach ($details as $detail)
                            <p style="margin:10px 0;border-left:3px solid #8b5cf6;padding:10px 14px;background:#fafafa;color:#52525b;font-size:13px;line-height:1.55;">{{ $detail }}</p>
                        @endforeach

                        <p style="margin:24px 0 0;color:#71717a;font-size:14px;line-height:1.6;">{{ $outro }}</p>

                        @if ($actionUrl)
                            <div style="margin-top:28px;padding-top:22px;border-top:1px solid #e4e4e7;">
                                <p style="margin:0 0 7px;color:#71717a;font-size:12px;line-height:1.5;">Se o botão não funcionar, copie este endereço no navegador:</p>
                                <p style="margin:0;word-break:break-all;color:#6d28d9;font-size:11px;line-height:1.5;">{{ $actionUrl }}</p>
                            </div>
                        @endif
                    </div>
                </td></tr>
                <tr><td align="center" style="padding:22px 20px 0;color:#71717a;font-size:12px;line-height:1.6;">
                    <strong style="color:#52525b;">{{ $securityNote }}</strong><br>
                    © {{ date('Y') }} Inovaforce. Todos os direitos reservados.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
