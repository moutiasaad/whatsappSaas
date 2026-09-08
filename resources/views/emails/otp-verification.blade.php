<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Your verification code</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:'Segoe UI',system-ui,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:40px 16px">
    <tr><td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#fff;border-radius:16px;border:1px solid #e2e8f0;overflow:hidden">

            {{-- Header --}}
            <tr>
                <td style="background:#0d1117;padding:28px 40px">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding-right:10px">
                                <img src="{{ asset('images/logo.svg') }}" width="32" height="32" alt="{{ config('app.name') }}" style="border-radius:8px;display:block">
                            </td>
                            <td style="font-size:18px;font-weight:700;color:#f1f5f9;letter-spacing:-.3px">
                                {{ config('app.name', 'wavadesk') }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- Body --}}
            <tr>
                <td style="padding:40px 40px 32px">
                    <p style="font-size:22px;font-weight:700;color:#0f172a;margin:0 0 8px">Verify your email address</p>
                    <p style="font-size:15px;color:#64748b;margin:0 0 32px;line-height:1.6">
                        Use the code below to complete your registration. It expires in <strong>10 minutes</strong>.
                    </p>

                    {{-- OTP code --}}
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:32px">
                        <tr>
                            <td align="center" style="background:#ecf7f6;border:1px solid #d6efed;border-radius:14px;padding:28px 20px">
                                <div style="font-size:42px;font-weight:800;letter-spacing:14px;color:#0f172a;font-variant-numeric:tabular-nums">{{ $otp }}</div>
                                <div style="font-size:13px;color:#64748b;margin-top:10px">One-time verification code</div>
                            </td>
                        </tr>
                    </table>

                    <p style="font-size:14px;color:#94a3b8;margin:0;line-height:1.6">
                        If you didn't request this, you can safely ignore this email. Someone may have typed your email address by mistake.
                    </p>
                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td style="padding:20px 40px 28px;border-top:1px solid #e2e8f0">
                    <p style="font-size:12px;color:#94a3b8;margin:0">
                        © {{ date('Y') }} {{ config('app.name', 'wavadesk') }} &mdash; All rights reserved
                    </p>
                </td>
            </tr>

        </table>
    </td></tr>
</table>
</body>
</html>
