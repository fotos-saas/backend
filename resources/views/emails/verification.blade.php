<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email cím megerosítés</title>
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
            background-color: #10b981;
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
        .button {
            display: inline-block;
            background-color: #10b981;
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
        .info-box {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
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
                <rect width="512" height="512" rx="96" fill="#10b981"/>
                <rect x="96" y="176" width="320" height="224" rx="32" fill="#fff" opacity="0.95"/>
                <rect x="192" y="144" width="96" height="48" rx="12" fill="#fff" opacity="0.95"/>
                <circle cx="256" cy="288" r="80" fill="#059669" opacity="0.9"/>
                <circle cx="256" cy="288" r="60" fill="#a7f3d0"/>
                <circle cx="256" cy="288" r="28" fill="#047857"/>
                <circle cx="242" cy="274" r="10" fill="#fff" opacity="0.6"/>
                <circle cx="352" cy="200" r="12" fill="#fbbf24"/>
                <rect x="296" y="152" width="40" height="16" rx="8" fill="#d1fae5"/>
            </svg>
            <!--<![endif]-->
        </div>
        <h1>TablóStúdió</h1>
        <h2>Email cím megerősítés</h2>
    </div>

    <div class="content">
        <p>Kedves {{ $user->name }}!</p>

        <p>Köszönjük, hogy regisztráltál a TablóStúdió rendszerbe!</p>

        <p>Az email címed megerosítéséhez kattints az alábbi gombra:</p>

        <a href="{{ $verificationUrl }}" class="button">
            Email cím megerosítése
        </a>

        <div class="info-box">
            <strong>Fontos:</strong> A link 24 óráig érvényes. Ha nem te regisztráltál, kérjük, hagyd figyelmen kívül ezt az emailt.
        </div>

        <p>Ha a gomb nem mukodik, másold be a következo linket a böngészodbe:</p>
        <p style="word-break: break-all; color: #6b7280; font-size: 12px;">{{ $verificationUrl }}</p>

        <div class="footer">
            <p>Üdvözlettel,<br>TablóStúdió csapat</p>
        </div>
    </div>
</body>
</html>
