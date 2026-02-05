<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jelszó megváltoztatva</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f59e0b;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        .warning-box {
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .info-box {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div style="margin-bottom: 12px;">
            <!--[if !mso]><!-->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="64" height="64" style="display: inline-block; vertical-align: middle;">
                <rect width="512" height="512" rx="96" fill="#f59e0b"/>
                <rect x="96" y="176" width="320" height="224" rx="32" fill="#fff" opacity="0.95"/>
                <rect x="192" y="144" width="96" height="48" rx="12" fill="#fff" opacity="0.95"/>
                <circle cx="256" cy="288" r="80" fill="#d97706" opacity="0.9"/>
                <circle cx="256" cy="288" r="60" fill="#fde68a"/>
                <circle cx="256" cy="288" r="28" fill="#b45309"/>
                <circle cx="242" cy="274" r="10" fill="#fff" opacity="0.6"/>
                <circle cx="352" cy="200" r="12" fill="#fbbf24"/>
                <rect x="296" y="152" width="40" height="16" rx="8" fill="#fef3c7"/>
            </svg>
            <!--<![endif]-->
        </div>
        <h1>TablóStúdió</h1>
        <h2>Jelszó megváltoztatva</h2>
    </div>

    <div class="content">
        <p>Kedves {{ $user->name }}!</p>

        <p>Értesítünk, hogy a fiókodhoz tartozó jelszó megváltozott.</p>

        <div class="info-box">
            <strong>Részletek:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>Dátum: {{ now()->format('Y. m. d. H:i') }}</li>
                <li>IP cím: {{ $ipAddress ?? 'Ismeretlen' }}</li>
            </ul>
        </div>

        <div class="warning-box">
            <strong>Nem te voltál?</strong><br>
            Ha nem te változtattad meg a jelszavad, azonnal lépj kapcsolatba velünk, és változtasd meg a jelszavad egy biztonságos eszközrol!
        </div>

        <div class="footer">
            <p>Üdvözlettel,<br>TablóStúdió csapat</p>
            <p style="font-size: 12px; color: #9ca3af;">
                Ez egy automatikus biztonsági értesítés. Ha nem te változtattad meg a jelszavad, kérjük, azonnal lépj kapcsolatba velünk.
            </p>
        </div>
    </div>
</body>
</html>
