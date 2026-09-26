<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- TIFFINS ---\n";
foreach (\App\Models\Tiffin::all() as $t) {
    echo "ID {$t->id}: {$t->name} (Price: {$t->price})\n";
    echo "  - Description: {$t->description}\n";
    echo "  - Items JSON: " . json_encode($t->items) . "\n";
    echo "  - Components: " . json_encode($t->components) . "\n\n";
}

echo "--- ITEMS ---\n";
foreach (\App\Models\Item::all() as $i) {
    echo "ID {$i->id}: {$i->name} (Category: " . ($i->category ? $i->category->name : 'N/A') . ", Price: {$i->price})\n";
}

echo "\n--- RECENT ORDERS ---\n";
foreach (\App\Models\Order::latest()->take(10)->get() as $o) {
    echo "ID {$o->id}: {$o->tiffin} (Qty: {$o->quantity}, Date: {$o->date})\n";
    echo "  - Add-ons: " . $o->add_ons . "\n";
    echo "  - Selections: " . json_encode($o->selections) . "\n\n";
}
