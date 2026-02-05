<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meghívó - {{ $partnerName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 32px 0;
            color: #1f2933;
        }
        .wrapper {
            max-width: 640px;
            margin: 0 auto;
            padding: 0 16px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 32px;
        }
        .logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo span {
            display: inline-block;
            font-size: 20px;
            font-weight: 600;
            color: #1e7b34;
        }
        .content {
            font-size: 16px;
            line-height: 1.6;
        }
        .code-box {
            background-color: #f3f4f6;
            border-radius: 8px;
            padding: 16px 24px;
            text-align: center;
            margin: 24px 0;
        }
        .code {
            font-family: monospace;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #1e7b34;
        }
        .role-badge {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
        }
        .button {
            display: inline-block;
            background-color: #1e7b34;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            margin: 24px 0;
        }
        .button:hover {
            background-color: #166534;
        }
        .cta-container {
            text-align: center;
        }
        .footer {
            margin-top: 24px;
            font-size: 12px;
            text-align: center;
            color: #6b7280;
        }
        a {
            color: #1e7b34;
        }
        .note {
            background-color: #fef3c7;
            border-radius: 6px;
            padding: 12px 16px;
            font-size: 14px;
            color: #92400e;
            margin-top: 24px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="logo">
            <!--[if !mso]><!-->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="48" height="48" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                <rect width="512" height="512" rx="96" fill="#2563eb"/>
                <rect x="96" y="176" width="320" height="224" rx="32" fill="#fff" opacity="0.95"/>
                <rect x="192" y="144" width="96" height="48" rx="12" fill="#fff" opacity="0.95"/>
                <circle cx="256" cy="288" r="80" fill="#1d4ed8" opacity="0.9"/>
                <circle cx="256" cy="288" r="60" fill="#bfdbfe"/>
                <circle cx="256" cy="288" r="28" fill="#1e40af"/>
                <circle cx="242" cy="274" r="10" fill="#fff" opacity="0.6"/>
                <circle cx="352" cy="200" r="12" fill="#fbbf24"/>
                <rect x="296" y="152" width="40" height="16" rx="8" fill="#dbeafe"/>
            </svg>
            <!--<![endif]-->
            <span>Tablóstúdió</span>
        </div>
        <div class="content">
            <h2 style="margin-top: 0; color: #1f2933;">Kedves Leendő Munkatárs!</h2>

            <p>
                <strong>{{ $partnerName }}</strong> meghívott téged a Tablóstúdió rendszerébe
                <span class="role-badge">{{ $roleName }}</span> szerepkörben.
            </p>

            <p>
                A belépési kódod:
            </p>

            <div class="code-box">
                <span class="code">{{ $code }}</span>
            </div>

            <div class="cta-container">
                <a href="{{ $registerUrl }}" class="button">
                    Regisztráció
                </a>
            </div>

            <p style="text-align: center; font-size: 14px; color: #6b7280;">
                Vagy másold be a kódot a regisztrációs oldalon.
            </p>

            @if($expiresAt)
            <div class="note">
                ⏰ A meghívó kód {{ $expiresAt->format('Y. m. d. H:i') }}-ig érvényes.
            </div>
            @endif
        </div>
        <div class="footer">
            <p>
                Tablóstúdió &bull; <a href="https://tablostudio.hu">tablostudio.hu</a>
            </p>
            <p>
                Ez egy automatikus üzenet, kérjük ne válaszolj rá.
            </p>
        </div>
    </div>
</div>
</body>
</html>
