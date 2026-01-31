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
        <h1>Photo Stack</h1>
        <h2>Email cím megerosítés</h2>
    </div>

    <div class="content">
        <p>Kedves {{ $user->name }}!</p>

        <p>Köszönjük, hogy regisztráltál a Photo Stack rendszerbe!</p>

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
            <p>Üdvözlettel,<br>Photo Stack csapat</p>
        </div>
    </div>
</body>
</html>
