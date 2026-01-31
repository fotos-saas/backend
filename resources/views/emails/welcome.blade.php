<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Üdvözlünk a Photo Stack-ben!</title>
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
        <h1>Photo Stack</h1>
        <h2>Üdvözlünk!</h2>
    </div>

    <div class="content">
        <p>Kedves {{ $user->name }}!</p>

        <p>Örülünk, hogy csatlakoztál a Photo Stack közösséghez!</p>

        <p>A fiókod sikeresen létrejött és most már elkezdheted használni szolgáltatásainkat.</p>

        <a href="{{ config('app.frontend_tablo_url') }}" class="button">
            Bejelentkezés
        </a>

        <div class="feature-list">
            <strong>Mit tehetsz a Photo Stack-kel?</strong>
            <ul>
                <li>Tablófotók kezelése és szerkesztése</li>
                <li>Közösségi szavazások létrehozása</li>
                <li>Fotók megosztása osztálytársakkal</li>
                <li>Egyszerű kommunikáció az ügyintézokkel</li>
            </ul>
        </div>

        <p>Ha bármilyen kérdésed van, ne habozz felvenni velünk a kapcsolatot!</p>

        <div class="footer">
            <p>Üdvözlettel,<br>Photo Stack csapat</p>
        </div>
    </div>
</body>
</html>
