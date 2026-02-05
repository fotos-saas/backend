<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Üdvözlünk a TablóStúdióban!</title>
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
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        .feature-list {
            background-color: #f0f9ff;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .feature-list ul {
            margin: 0;
            padding-left: 20px;
        }
        .feature-list li {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div style="margin-bottom: 12px;">
            <!--[if !mso]><!-->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="64" height="64" style="display: inline-block; vertical-align: middle;">
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
        </div>
        <h1>TablóStúdió</h1>
        <h2>Üdvözlünk!</h2>
    </div>

    <div class="content">
        <p>Kedves {{ $user->name }}!</p>

        <p>Örülünk, hogy csatlakoztál a TablóStúdió közösséghez!</p>

        <p>A fiókod sikeresen létrejött és most már elkezdheted használni szolgáltatásainkat.</p>

        <a href="{{ config('app.frontend_tablo_url') }}" class="button">
            Bejelentkezés
        </a>

        <div class="feature-list">
            <strong>Mit tehetsz a TablóStúdióval?</strong>
            <ul>
                <li>Tablófotók kezelése és szerkesztése</li>
                <li>Közösségi szavazások létrehozása</li>
                <li>Fotók megosztása osztálytársakkal</li>
                <li>Egyszerű kommunikáció az ügyintézokkel</li>
            </ul>
        </div>

        <p>Ha bármilyen kérdésed van, ne habozz felvenni velünk a kapcsolatot!</p>

        <div class="footer">
            <p>Üdvözlettel,<br>TablóStúdió csapat</p>
        </div>
    </div>
</body>
</html>
