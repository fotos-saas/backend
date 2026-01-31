<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Árajánlat - {{ $quote->quote_number }}</title>
    <style>
        /* === RESET === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 0;
            size: A4 portrait;
        }

        /* === BODY === */
        body {
            font-family: 'NotoSans', 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.3;
            color: #374151;
            background: #FFFFFF;
            margin: 0;
            padding: 0;
        }

        /* === PAGE CONTAINER === */
        .page {
            position: relative;
            width: 100%;
            min-height: 100%;
        }

        /* === TOP HEADER BAR (white) === */
        .top-bar {
            background: #FFFFFF;
            padding: 8mm 15mm 6mm 18mm;
            position: relative;
            border-bottom: 2px solid #E5E7EB;
        }

        .top-bar::after {
            content: "";
            display: table;
            clear: both;
        }

        .header-left {
            float: left;
        }

        .logo-row {
            margin-bottom: 2mm;
        }

        .logo-icon {
            display: inline-block;
            width: 10mm;
            height: 10mm;
            background: #2563EB;
            margin-right: 3mm;
            vertical-align: middle;
        }

        .logo-text {
            display: inline-block;
            font-size: 26pt;
            font-weight: bold;
            color: #2563EB;
            vertical-align: middle;
            letter-spacing: -0.5pt;
        }

        .meta-title {
            font-size: 16pt;
            font-weight: normal;
            color: #6B7280;
        }

        .meta-date {
            font-size: 10pt;
            color: #6B7280;
        }

        .header-right {
            float: right;
            text-align: right;
            color: #374151;
            line-height: 1.3;
        }

        .meta-size {
            font-size: 22pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            color: #2563EB;
        }

        /* === MAIN CONTENT === */
        .main-content {
            padding: 10mm 15mm 35mm 18mm;
        }

        /* === GREETING & INTRO === */
        .greeting {
            font-size: 12pt;
            color: #374151;
            margin-bottom: 3mm;
        }

        .intro {
            font-size: 11pt;
            line-height: 1.35;
            color: #374151;
            text-align: left;
            margin-bottom: 5mm;
        }

        /* === SECTION HEADER === */
        .section-header {
            font-size: 13pt;
            font-weight: bold;
            color: #2563EB;
            border-bottom: 2px solid #2563EB;
            padding-bottom: 2mm;
            margin-bottom: 4mm;
            margin-top: 5mm;
        }

        /* === CONTENT LIST === */
        .content-list {
            margin-bottom: 4mm;
        }

        .content-item {
            margin-bottom: 3mm;
            font-size: 11pt;
            line-height: 1.35;
        }

        .item-title {
            font-weight: bold;
            color: #2563EB;
        }

        .item-text {
            color: #374151;
        }

        .item-details {
            margin-top: 2mm;
            font-size: 10pt;
            color: #374151;
        }

        .item-price-row {
            margin: 1mm 0;
        }

        .item-price {
            color: #374151;
            font-weight: normal;
        }

        /* === PRICE SECTION === */
        .price-section {
            margin: 24mm 0 8mm 0;
            position: relative;
        }

        .price-section::after {
            content: "";
            display: table;
            clear: both;
        }

        .price-label {
            float: left;
            font-size: 11pt;
            color: #374151;
            padding-top: 6mm;
            border-top: 2px solid #2563EB;
            width: 50%;
        }

        .price-amount {
            float: right;
            font-size: 32pt;
            font-weight: bold;
            color: #2563EB;
            text-align: right;
        }

        /* === NOTES === */
        .notes {
            margin-top: 2mm;
            font-size: 9pt;
            color: #374151;
        }

        .note-item {
            margin-bottom: 0.5mm;
        }

        /* === CLOSING TEXT === */
        .closing {
            margin-top: 3mm;
            font-size: 10pt;
            line-height: 1.4;
            color: #374151;
        }

        /* === FOOTER === */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #2563EB;
            padding: 5mm 15mm 5mm 18mm;
            color: #FFFFFF;
        }

        .footer::after {
            content: "";
            display: table;
            clear: both;
        }

        .footer-left {
            float: left;
            width: 60%;
        }

        .footer-right {
            float: right;
            text-align: right;
            width: 35%;
            padding-top: 2mm;
        }

        .footer-name {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 1mm;
        }

        .footer-contact {
            font-size: 10pt;
            line-height: 1.6;
        }

        .footer-website {
            font-size: 11pt;
            font-weight: bold;
        }

        /* === PRICE TABLE (Photographer) === */
        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4mm 0;
        }

        .price-table th,
        .price-table td {
            padding: 2mm 4mm;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }

        .price-table th {
            background: #F3F4F6;
            font-weight: bold;
            color: #374151;
            font-size: 10pt;
        }

        .price-table td {
            font-size: 11pt;
        }

        .price-table .price-cell {
            text-align: right;
            font-weight: bold;
            color: #2563EB;
        }

        /* === DISCOUNT TABLE === */
        .discount-table {
            width: 60%;
            border-collapse: collapse;
            margin: 3mm 0;
        }

        .discount-table th,
        .discount-table td {
            padding: 1.5mm 3mm;
            text-align: center;
            border: 1px solid #E5E7EB;
            font-size: 10pt;
        }

        .discount-table th {
            background: #EFF6FF;
            color: #1E40AF;
            font-weight: bold;
        }

        .discount-badge {
            background: #DCFCE7;
            color: #166534;
            padding: 1mm 2mm;
            border-radius: 2mm;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Top Header Bar -->
        <div class="top-bar">
            <div class="header-left">
                <div class="logo-row">
                    <span class="logo-icon"></span>
                    <span class="logo-text">Tablókirály</span>
                </div>
                <div class="meta-title">Árajánlat</div>
                <div class="meta-date">{{ $quote->quote_number }} | {{ $quote->quote_date->format('Y.m.d.') }}</div>
            </div>
            <div class="header-right">
                <div class="meta-size">{{ $quote->size ?: $quote->getQuoteTypeLabel() }}</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Greeting -->
            <div class="greeting">
                {{ $quote->customer_title ? preg_replace('/^(Tisztelt)([^\s])/', '$1 $2', $quote->customer_title) : 'Tisztelt ' . $quote->customer_name . '!' }}
            </div>

            @if($quote->isPhotographerQuote())
                {{-- === FOTÓS ÁRAJÁNLAT === --}}
                @include('pdf.partials.quote-photographer')
            @else
                {{-- === EGYEDI ÁRAJÁNLAT === --}}
                <div class="intro">
                    @if($quote->intro_text)
                        {{ $quote->replacePlaceholders($quote->intro_text) }}
                    @else
                        Köszönöm érdeklődését! 2012 óta készítek tablókat - az alábbiakban részletezem a {{ $quote->size ?: '' }} tabló árajánlatot.
                    @endif
                </div>

                <!-- Content Section -->
                <div class="section-header">MIT TARTALMAZ AZ ÁR?</div>

                <div class="content-list">
                    @if($quote->content_items && count($quote->content_items) > 0)
                        @foreach($quote->content_items as $item)
                            <div class="content-item">
                                <span class="item-title">{{ $item['title'] }}:</span>
                                @if(!empty($item['description']))
                                    <span class="item-text">{{ $item['description'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    @else
                        {{-- Default content based on quote type --}}
                        @if(!$quote->has_production)
                            {{-- Egyedi grafika csak ha NEM csak kivitelezés --}}
                            <div class="content-item">
                                <span class="item-title">Egyedi grafikai tervezés:</span>
                                <span class="item-text">Az osztály stílusához igazodó design, a véglegesítésig egyeztetett módosításokkal.</span>
                            </div>
                        @endif

                        @if($quote->quote_type !== 'digital')
                            <div class="content-item">
                                <span class="item-title">Prémium nyomtatás:</span>
                                <span class="item-text">UV-álló, élénk színek, amelyek évek múlva is ugyanolyan szépek maradnak.</span>
                            </div>

                            <div class="content-item">
                                <span class="item-title">Üvegezett fa keret:</span>
                                <span class="item-text">Többféle stílus közül választható a tablóhoz.</span>
                            </div>
                        @endif

                        @if($quote->has_small_tablo)
                            <div class="content-item">
                                <span class="item-title">Kistabló:</span>
                                <span class="item-text">Személyes emlék minden diáknak, ugyanazzal a minőséggel.</span>
                                <div class="item-details">
                                    <div class="item-price-row"><span class="item-price">650 Ft/db</span> - 15x21 cm</div>
                                    <div class="item-price-row"><span class="item-price">750 Ft/db</span> - 18x24 cm</div>
                                    <div class="item-price-row"><span class="item-price">950 Ft/db</span> - A4</div>
                                </div>
                            </div>
                        @endif

                        @if($quote->has_shipping)
                            <div class="content-item">
                                <span class="item-title">Személyes kiszállítás:</span>
                                <span class="item-text">Az ország bármely pontjára, előre egyeztetett időpontban. A szállítás díját külön egyeztetjük.</span>
                            </div>
                        @elseif($quote->quote_type === 'digital')
                            <div class="content-item">
                                <span class="item-title">Átadás:</span>
                                <span class="item-text">Megbeszélés szerint (az ár nem tartalmazza a szállítást).</span>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Price Section -->
                <div class="price-section">
                    <div class="price-label">
                        @if($quote->quote_type === 'digital')
                            Tervezés díja:
                        @elseif($quote->has_production)
                            Kivitelezés díja:
                        @else
                            Tervezés és kivitelezés díja:
                        @endif
                    </div>
                    <div class="price-amount">{{ number_format($quote->calculateTotalPrice(), 0, ',', '.') }},-Ft</div>
                </div>

                <!-- Notes -->
                <div class="notes">
                    <div class="note-item">**Az árak tartalmazzák az ÁFA értékét</div>
                    @if($quote->has_shipping && $quote->shipping_price > 0)
                        <div class="note-item">**Az ár tartalmazza a házhozszállítás díját: {{ number_format($quote->shipping_price, 0, ',', '.') }} Ft</div>
                    @else
                        <div class="note-item">**Házhozszállítás díja előzetes egyeztetés alapján</div>
                    @endif
                    @if($quote->discount_price > 0 && $quote->discount_text)
                        <div class="note-item">**{{ $quote->discount_text }}</div>
                    @endif
                </div>

                <!-- Closing Text -->
                @if($quote->notes)
                    <div class="closing">
                        {{ $quote->notes }}
                    </div>
                @else
                    <div class="closing">
                        Kérdés esetén bátran keressen - több mint 1000 osztállyal dolgoztam már együtt!
                    </div>
                @endif
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-left">
                <div class="footer-name">Nové Ferenc</div>
                <div class="footer-contact">
                    <div>info@tablokiraly.hu</div>
                    <div>0670/632 - 81 - 31</div>
                </div>
            </div>
            <div class="footer-right">
                <div class="footer-website">tablokiraly.hu</div>
            </div>
        </div>
    </div>
</body>
</html>
