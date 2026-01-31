<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tabló Megrendelés - {{ $data['schoolName'] ?? 'Előnézet' }}</title>
    <style>
        /* Reset és alapok */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* DomPDF margó megoldás: @page margin 0, body padding-gel */
        @page {
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1F2937;
            background: #FFFFFF;
            /* 1.25cm = 12.5mm margó minden oldalon */
            padding: 12mm;
            margin: 0;
        }

        /* Prevent empty page at end */
        .container {
            page-break-after: avoid;
        }

        /* Header */
        .header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3B82F6;
        }

        .header-content {
            width: 100%;
        }

        .header-left {
            float: left;
        }

        .header-right {
            float: right;
            text-align: right;
        }

        .header::after {
            content: "";
            display: table;
            clear: both;
        }

        .logo {
            font-size: 18pt;
            font-weight: bold;
            color: #3B82F6;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .logo-subtitle {
            font-size: 8pt;
            color: #6B7280;
        }

        /* Footer - fix az oldal alján */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 12mm;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            font-size: 8pt;
            color: #6B7280;
            background: #FFFFFF;
        }

        /* Content wrapper - hagyjon helyet a footernek */
        .content {
            padding-bottom: 40px;
        }

        .header-title {
            font-size: 20pt;
            font-weight: bold;
            color: #1E40AF;
            margin-bottom: 3px;
        }

        .header-date {
            font-size: 9pt;
            color: #6B7280;
        }

        /* Szakaszok */
        .section {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        /* Névsor szekció - ha nem fér ki, törhet */
        .section-roster {
            page-break-inside: auto;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1E40AF;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #3B82F6;
            /* Megakadályozza, hogy a címsor árván maradjon az oldal alján */
            page-break-after: avoid;
        }


        /* Mezők */
        .field {
            margin-bottom: 8px;
        }

        .field-row {
            width: 100%;
        }

        .field-label {
            font-weight: bold;
            color: #374151;
            display: inline-block;
            width: 100px;
            vertical-align: top;
        }

        .field-value {
            color: #1F2937;
            display: inline-block;
        }

        /* Idézet */
        .quote-value {
            font-style: italic;
            color: #4B5563;
        }

        /* Szín előnézet */
        .color-preview {
            display: inline-block;
        }

        .color-swatch {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #D1D5DB;
            border-radius: 3px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .color-code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9pt;
            vertical-align: middle;
        }

        /* Megjegyzés box */
        .notes-box {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 4px;
            padding: 12px;
            margin-top: 8px;
            font-size: 9pt;
            line-height: 1.6;
        }

        .notes-box p {
            margin-bottom: 8px;
        }

        .notes-box p:last-child {
            margin-bottom: 0;
        }

        /* Névsor */
        .name-list {
            margin-top: 12px;
        }

        .name-list-title {
            font-size: 10pt;
            font-weight: bold;
            color: #374151;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #E5E7EB;
        }

        .name-list ol,
        .name-list ul {
            padding-left: 20px;
            margin-bottom: 12px;
        }

        .name-list li {
            margin-bottom: 4px;
            color: #1F2937;
        }

        /* Névsor táblázat - több oszlopos elrendezés */
        .names-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .names-table td {
            vertical-align: top;
            padding: 2px 8px;
            font-size: 9pt;
            width: 33.33%;
        }

        .names-table-2col td {
            width: 50%;
        }

        .footer-brand {
            font-weight: bold;
            color: #3B82F6;
            margin-bottom: 3px;
        }

        .footer-disclaimer {
            font-style: italic;
            color: #9CA3AF;
            font-size: 7pt;
        }

        /* Sorrend típus badge */
        .sort-badge {
            display: inline-block;
            background: #EFF6FF;
            color: #1E40AF;
            font-size: 8pt;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
<div class="content">
    {{-- Header --}}
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="logo">Tabló&nbsp;Király</div>
                <div class="logo-subtitle">Professzionális tablóképek</div>
            </div>
            <div class="header-right">
                <div class="header-title">Megrendelés Előnézet</div>
                <div class="header-date">Generálva: {{ now()->format('Y. m. d. H:i') }}</div>
            </div>
        </div>
    </div>

    {{-- 1. Kapcsolattartó --}}
    <div class="section">
        <h2 class="section-title">
                        Kapcsolattartó
        </h2>
        <div class="field">
            <span class="field-label">Név:</span>
            <span class="field-value">{{ $data['name'] ?? '-' }}</span>
        </div>
        <div class="field">
            <span class="field-label">Email:</span>
            <span class="field-value">{{ $data['contactEmail'] ?? '-' }}</span>
        </div>
        <div class="field">
            <span class="field-label">Telefon:</span>
            <span class="field-value">{{ $data['contactPhone'] ?? '-' }}</span>
        </div>
    </div>

    {{-- 2. Tabló Alapadatok --}}
    <div class="section">
        <h2 class="section-title">
                        Tabló Alapadatok
        </h2>
        <div class="field">
            <span class="field-label">Iskola neve:</span>
            <span class="field-value">{{ $data['schoolName'] ?? '-' }}</span>
        </div>
        <div class="field">
            <span class="field-label">Város:</span>
            <span class="field-value">{{ $data['schoolCity'] ?? '-' }}</span>
        </div>
        <div class="field">
            <span class="field-label">Osztály:</span>
            <span class="field-value">{{ $data['className'] ?? '-' }}</span>
        </div>
        <div class="field">
            <span class="field-label">Évfolyam:</span>
            <span class="field-value">{{ $data['classYear'] ?? '-' }}</span>
        </div>
        @if(!empty($data['quote']))
        <div class="field">
            <span class="field-label">Idézet:</span>
            <span class="field-value quote-value">"{{ $data['quote'] }}"</span>
        </div>
        @endif
    </div>

    {{-- 3. Design Beállítások --}}
    <div class="section">
        <h2 class="section-title">
                        Design Beállítások
        </h2>
        <div class="field">
            <span class="field-label">Betűtípus:</span>
            <span class="field-value">{{ $data['fontFamily'] ?? '-' }}</span>
        </div>
        <div class="field">
            <span class="field-label">Betűszín:</span>
            <div class="color-preview">
                <span class="color-swatch" style="background-color: {{ $data['color'] ?? '#000000' }};"></span>
                <span class="color-code">{{ $data['color'] ?? '#000000' }}</span>
            </div>
        </div>
        @if(!empty($data['description']))
        <div class="field">
            <span class="field-label">Megjegyzés:</span>
            <div class="notes-box">
                {!! $data['description'] !!}
            </div>
        </div>
        @endif
    </div>

    @php
        $students = !empty($data['studentDescription'])
            ? array_values(array_filter(array_map('trim', explode("\n", $data['studentDescription']))))
            : [];
        $teachers = !empty($data['teacherDescription'])
            ? array_values(array_filter(array_map('trim', explode("\n", $data['teacherDescription']))))
            : [];

        // Diákok 3 oszlopba rendezése
        $studentColumns = 3;
        $studentRows = count($students) > 0 ? ceil(count($students) / $studentColumns) : 0;

        // Tanárok 2 oszlopba rendezése
        $teacherColumns = 2;
        $teacherRows = count($teachers) > 0 ? ceil(count($teachers) / $teacherColumns) : 0;

        // Sorrend típus szöveg
        $sortTypeText = '';
        if (!empty($data['sortType'])) {
            switch($data['sortType']) {
                case 'abc': $sortTypeText = 'ABC sorrend'; break;
                case 'kozepre': $sortTypeText = 'Középre'; break;
                case 'megjegyzesben': $sortTypeText = 'Megjegyzésben'; break;
                case 'mindegy': $sortTypeText = 'Mindegy'; break;
                default: $sortTypeText = $data['sortType'];
            }
        }
    @endphp

    {{-- 4. Diákok névsor --}}
    <div class="section section-roster">
        <h2 class="section-title">
                        Diákok ({{ count($students) }} fő)
            @if($sortTypeText)
            <span class="sort-badge">{{ $sortTypeText }}</span>
            @endif
        </h2>
        @if(count($students) > 0)
        <table class="names-table">
            @for($row = 0; $row < $studentRows; $row++)
            <tr>
                @for($col = 0; $col < $studentColumns; $col++)
                    @php
                        $index = $row + ($col * $studentRows);
                    @endphp
                    <td>
                        @if(isset($students[$index]))
                            {{ $index + 1 }}. {{ $students[$index] }}
                        @endif
                    </td>
                @endfor
            </tr>
            @endfor
        </table>
        @else
        <p style="color: #9CA3AF; font-style: italic;">Nincs megadva</p>
        @endif
    </div>

    {{-- 5. Tanárok névsor --}}
    <div class="section section-roster">
        <h2 class="section-title">
                        Tanárok ({{ count($teachers) }} fő)
        </h2>
        @if(count($teachers) > 0)
        <table class="names-table names-table-2col">
            @for($row = 0; $row < $teacherRows; $row++)
            <tr>
                @for($col = 0; $col < $teacherColumns; $col++)
                    @php
                        $index = $row + ($col * $teacherRows);
                    @endphp
                    <td>
                        @if(isset($teachers[$index]))
                            {{ $index + 1 }}. {{ $teachers[$index] }}
                        @endif
                    </td>
                @endfor
            </tr>
            @endfor
        </table>
        @else
        <p style="color: #9CA3AF; font-style: italic;">Nincs megadva</p>
        @endif
    </div>
</div>

    {{-- Footer - minden oldalon megjelenik --}}
    <div class="footer">
        <div class="footer-brand">Tabló&nbsp;Király | www.tablokiraly.hu | info@tablokiraly.hu</div>
        <div class="footer-disclaimer">Ez egy automatikusan generált előnézeti dokumentum. A végleges tabló design ettől eltérhet.</div>
    </div>
</body>
</html>
