@php
    $branding = app(\App\Services\BrandingService::class);
    $partnerName = $branding->getName();
    $logoUrl = $branding->getLogoUrl();
    $website = $branding->getWebsite();
    $email = $branding->getEmail();
    $phone = $branding->getPhone();
    $address = $branding->getAddress();
@endphp
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belépés - {{ $projectName }}</title>
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
        .logo img {
            max-height: 60px;
            width: auto;
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
        .content h1 {
            color: #1f2933;
            margin-top: 0;
            margin-bottom: 16px;
            font-size: 24px;
        }
        .content p {
            margin: 0 0 16px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 16px 0 24px 0;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
        .info-box {
            background-color: #f1f5f9;
            border-radius: 6px;
            padding: 16px;
            margin: 16px 0;
        }
        .info-box strong {
            color: #1f2933;
        }
        .warning {
            font-size: 14px;
            color: #64748b;
            margin-top: 16px;
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
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="logo">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $partnerName }}">
            @else
                <span>{{ $partnerName }}</span>
            @endif
        </div>
        <div class="content">
            <h1>Szia {{ $guestName }}!</h1>

            <p>Kaptunk egy kérést a belépési linked újraküldésére.</p>

            <div class="info-box">
                <strong>Projekt:</strong> {{ $projectName }}
                @if($schoolName)
                    <br><strong>Iskola:</strong> {{ $schoolName }}
                @endif
            </div>

            <p>Kattints az alábbi gombra a belépéshez:</p>

            <div style="text-align: center;">
                <a href="{{ $link }}" class="btn-primary">Belépés</a>
            </div>

            <p class="warning">
                A link 24 óráig érvényes. Ha nem te kérted ezt az emailt, nyugodtan hagyd figyelmen kívül.
            </p>
        </div>
        <div class="footer">
            <p>
                {{ $partnerName }}
                @if($website)
                    &bull; <a href="{{ $website }}">{{ $website }}</a>
                @endif
            </p>
            @if($email || $phone)
                <p>
                    @if($email)
                        <a href="mailto:{{ $email }}">{{ $email }}</a>
                    @endif
                    @if($email && $phone)
                        &bull;
                    @endif
                    @if($phone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>
                    @endif
                </p>
            @endif
            @if($address)
                <p>{{ $address }}</p>
            @endif
        </div>
    </div>
</div>
</body>
</html>
