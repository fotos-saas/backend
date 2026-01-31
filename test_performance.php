<?php
// Teszt a queue job teljesítményére

echo "=== PERFORMANCE BOTTLENECK MÉRÉSEK ===\n\n";

// 1. LOOP OVERHEAD - Fájlonkénti feldolgozás
echo "1. BACKEND - Queue Job Loop Overhead:\n";
$start = microtime(true);
$items = [];
for ($i = 0; $i < 1000; $i++) {
    $items[] = ['id' => $i, 'status' => 'pending'];
}

// Simulate per-file DB update
foreach ($items as $item) {
    // DB update simulation
    usleep(1000); // 1ms per file DB update
}
$elapsed = microtime(true) - $start;
echo "   1000 fájl DB update: " . round($elapsed * 1000) . " ms\n";
echo "   Átlag per fájl: " . round(($elapsed * 1000) / 1000, 2) . " ms\n\n";

// 2. MEMORY USAGE - LocalStorage simulation
echo "2. FRONTEND - LocalStorage Memory Usage:\n";
$files = [];
for ($i = 0; $i < 500; $i++) {
    $files[] = [
        'id' => $i,
        'original_name' => 'IMG_' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.HEIC',
        'folder_path' => 'folder' . ($i % 10),
        'conversion_status' => 'pending',
        'size' => rand(1000000, 5000000),
        'thumbnail' => base64_encode(random_bytes(10000)) // 10KB thumbnail simulation
    ];
}
$json = json_encode($files);
echo "   500 fájl JSON mérete: " . round(strlen($json) / 1024) . " KB\n";
echo "   LocalStorage limit: 5-10 MB\n";
echo "   Becsült max fájlok: " . round((5 * 1024) / (strlen($json) / 500)) . " fájl\n\n";

// 3. API POLLING - Network overhead
echo "3. API - Status Polling Overhead:\n";
$polling_interval = 2; // seconds
$avg_conversion_time = 120; // 2 minutes for 100 files
$polls_needed = $avg_conversion_time / $polling_interval;
echo "   Polling intervallum: {$polling_interval} sec\n";
echo "   100 fájl konverzió: ~{$avg_conversion_time} sec\n";
echo "   API hívások száma: {$polls_needed}\n";
echo "   Network overhead: " . round($polls_needed * 0.05, 2) . " sec (50ms RTT)\n\n";

// 4. ZIP GENERATION - I/O Bottleneck
echo "4. BACKEND - ZIP Generation I/O:\n";
$file_sizes = [
    10 => 10 * 3 * 1024 * 1024,  // 10 files * 3MB each
    100 => 100 * 3 * 1024 * 1024, // 100 files * 3MB each
    500 => 500 * 3 * 1024 * 1024, // 500 files * 3MB each
];
foreach ($file_sizes as $count => $size) {
    $size_mb = round($size / 1024 / 1024);
    $estimated_time = round($size / (50 * 1024 * 1024), 2); // 50 MB/s I/O speed
    echo "   {$count} fájl ({$size_mb} MB): ~{$estimated_time} sec\n";
}
echo "\n";

// 5. IMAGE CONVERSION - ImageMagick overhead
echo "5. BACKEND - Image Conversion (ImageMagick):\n";
$conversion_times = [
    'HEIC 5MB' => 1.2,
    'HEIC 10MB' => 2.5,
    'WEBP 3MB' => 0.8,
    'PNG 8MB' => 1.5,
];
foreach ($conversion_times as $type => $time) {
    echo "   {$type}: ~{$time} sec/file\n";
}
echo "   100 fájl (átlag): " . round(100 * 1.5) . " sec\n";
echo "   500 fájl (átlag): " . round(500 * 1.5) . " sec\n\n";

// ÖSSZESÍTÉS
echo "=== BOTTLENECK AZONOSÍTÁS ===\n\n";
echo "TOP 3 LEGNAGYOBB BOTTLENECK:\n\n";
echo "1. Queue Job - Fájlonkénti DB Update (CRITICAL)\n";
echo "   - 500+ fájlnál: 500+ DB query\n";
echo "   - Hatás: O(n) komplexitás\n\n";
echo "2. LocalStorage - Memory Limit (HIGH)\n";
echo "   - 500+ fájlnál: túllépi az 5MB limitet\n";
echo "   - Hatás: Böngésző crash/lassulás\n\n";
echo "3. ZIP Generation - Szekvenciális I/O (HIGH)\n";
echo "   - 500 fájl: 1.5 GB adat\n";
echo "   - Hatás: 30+ sec generálás\n";