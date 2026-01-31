<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jelszó visszaállítás</title>
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
            background-color: #3b82f6;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Photo Stack</h1>
        <h2>Jelszó visszaállítás</h2>
    </div>
    
    <div class="content">
        <p>Kedves {{ $user->name }}!</p>
        
        <p>Kaptunk egy jelszó visszaállítási kérést az Ön fiókjához.</p>
        
        <p>A jelszó visszaállításához kattintson az alábbi gombra:</p>
        
        <a href="{{ url('/auth/reset-password?token=' . $token . '&email=' . urlencode($email)) }}" class="button">
            Jelszó visszaállítása
        </a>
        
        <p>Ha nem Ön kérte a jelszó visszaállítást, kérjük, hagyja figyelmen kívül ezt az emailt.</p>
        
        <p>A link 60 percig érvényes.</p>
        
        <div class="footer">
            <p>Üdvözlettel,<br>Photo Stack csapat</p>
        </div>
    </div>
</body>
</html>
