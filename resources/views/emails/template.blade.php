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
    <title>{{ $partnerName }}</title>
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
        .content h1,
        .content h2,
        .content h3 {
            color: #1f2933;
            margin-top: 32px;
        }
        .content h1:first-child,
        .content h2:first-child,
        .content h3:first-child {
            margin-top: 0;
        }
        .content ul,
        .content ol {
            padding-left: 24px;
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
            {!! $body !!}
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

