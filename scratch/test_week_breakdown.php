<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Tiffin;
use App\Models\Item;
use Carbon\Carbon;

echo "=== TESTING MONTH & WEEK-BY-WEEK BREAKDOWN ===\n\n";

$selectedMonth = '2026-09';
$startOfMonth = Carbon::parse($selectedMonth . '-01')->startOfMonth();
$endOfMonth = $startOfMonth->copy()->endOfMonth();
$daysInMonth = $startOfMonth->daysInMonth;

// Compute 4-5 weeks in this month
$weeks = [];
$weekStart = $startOfMonth->copy();
$weekIndex = 1;

while ($weekStart->lte($endOfMonth)) {
    $weekEnd = $weekStart->copy()->addDays(6);
    if ($weekEnd->gt($endOfMonth)) {
        $weekEnd = $endOfMonth->copy();
    }
    
    $weeks[$weekIndex] = [
        'week_number' => $weekIndex,
        'label' => "Week {$weekIndex}",
        'date_range_label' => $weekStart->format('d M') . ' - ' . $weekEnd->format('d M Y'),
        'start_date' => $weekStart->toDateString(),
        'end_date' => $weekEnd->toDateString(),
        'total_orders' => 0,
        'total_tiffins' => 0,
        'total_rotis' => 0,
        'total_curries' => 0,
        'total_rice' => 0,
        'total_salads' => 0,
        'total_desserts' => 0,
        'item_quantities' => [],
    ];
    
    $weekStart = $weekEnd->copy()->addDay();
    $weekIndex++;
}

echo "Weeks configured for {$selectedMonth}:\n";
foreach ($weeks as $w) {
    echo "  {$w['label']} ({$w['date_range_label']}): {$w['start_date']} to {$w['end_date']}\n";
}

echo "\nTest complete.\n";
