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
        <h1>Photo Stack</h1>
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
            <p>Üdvözlettel,<br>Photo Stack csapat</p>
            <p style="font-size: 12px; color: #9ca3af;">
                Ez egy automatikus biztonsági értesítés. Ha nem te változtattad meg a jelszavad, kérjük, azonnal lépj kapcsolatba velünk.
            </p>
        </div>
    </div>
</body>
</html>
