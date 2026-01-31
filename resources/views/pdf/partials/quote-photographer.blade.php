{{-- Fotós árajánlat - Árlista és kedvezmények --}}

{{-- Bevezető --}}
<div class="intro">
    @if($quote->intro_text)
        {{ $quote->replacePlaceholders($quote->intro_text) }}
    @else
        Köszönöm érdeklődését! Az alábbiakban küldöm az aktuális tablóárakról szóló tájékoztatót.
    @endif
</div>

{{-- Árlista táblázat --}}
@if($quote->price_list_items && count($quote->price_list_items) > 0)
<div class="section-header">TABLÓMÉRETEK ÉS ÁRAK</div>

<table class="price-table">
    <thead>
        <tr>
            <th>Méret</th>
            <th>Leírás</th>
            <th style="text-align: right;">Ár</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quote->price_list_items as $item)
        <tr>
            <td><strong>{{ $item['size'] }}</strong></td>
            <td>{{ $item['label'] ?? $item['size'] . ' cm' }}</td>
            <td class="price-cell">{{ $item['price'] ? number_format((int) $item['price'] * 1000, 0, ',', '.') . ' Ft' : '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Mennyiségi kedvezmények --}}
@if($quote->volume_discounts && count($quote->volume_discounts) > 0)
<div class="section-header" style="margin-top: 8mm;">MENNYISÉGI KEDVEZMÉNYEK</div>

<table class="discount-table">
    <thead>
        <tr>
            <th>Minimum darabszám</th>
            <th>Kedvezmény</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quote->volume_discounts as $discount)
        <tr>
            <td>{{ $discount['label'] ?: ($discount['minQty'] . '+ db') }}</td>
            <td><span class="discount-badge">-{{ $discount['percentOff'] }}%</span></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Megjegyzések --}}
<div class="notes" style="margin-top: 6mm;">
    <div class="note-item">**Az árak tartalmazzák az ÁFA értékét</div>
    @if($quote->notes)
        <div class="note-item">**{{ $quote->notes }}</div>
    @endif
</div>

{{-- Záró szöveg --}}
<div class="closing">
    Kérdés esetén bátran keressen - több mint 1000 osztállyal dolgoztam már együtt!
</div>
