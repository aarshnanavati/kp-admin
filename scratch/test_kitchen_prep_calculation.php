<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Tiffin;
use App\Models\Item;
use Carbon\Carbon;

echo "=== TESTING ENHANCED KITCHEN PREP WITH BASE TIFFIN RESOLUTION ===\n\n";

$orders = Order::whereDate('date', '>=', Carbon::now()->subMonths(1)->toDateString())
    ->where('status', '!=', 'Cancelled')
    ->get();

$itemTotals = [];

$allItems = Item::with('category')->get()->keyBy('id');
$allTiffins = Tiffin::with('category')->get()->keyBy('id');

foreach ($orders as $order) {
    $orderQty = (int)($order->quantity ?: 1);
    
    // 1. Resolve selections
    $selections = $order->selections;
    if (is_string($selections)) {
        $selections = json_decode($selections, true);
    }
    
    $resolvedItems = [];
    
    if (is_array($selections) && !empty($selections['choices'])) {
        foreach ($selections['choices'] as $choice) {
            $name = trim($choice['chosen'] ?? $choice['name'] ?? '');
            $component = trim($choice['component'] ?? '');
            if (!$name) continue;
            $resolvedItems[] = [
                'name' => $name,
                'component' => $component,
                'qty' => 1,
            ];
        }
    } elseif (is_array($selections) && !empty($selections['custom_items'])) {
        foreach ($selections['custom_items'] as $cItem) {
            $name = trim($cItem['name'] ?? '');
            if (!$name) continue;
            $resolvedItems[] = [
                'name' => $name,
                'component' => 'Custom Choice',
                'qty' => (int)($cItem['qty'] ?? 1),
            ];
        }
    } else {
        // Fallback to base tiffin components / items
        $tiffin = $allTiffins->get($order->tiffin_id);
        if (!$tiffin && $order->tiffin) {
            $tiffin = $allTiffins->firstWhere('name', $order->tiffin);
        }
        
        if ($tiffin) {
            if (!empty($tiffin->components)) {
                foreach ($tiffin->components as $comp) {
                    $def = null;
                    foreach ($comp['options'] as $opt) {
                        if (!empty($opt['default'])) {
                            $def = $opt;
                            break;
                        }
                    }
                    if (!$def && !empty($comp['options'])) {
                        $def = $comp['options'][0];
                    }
                    if ($def && !empty($def['name'])) {
                        $resolvedItems[] = [
                            'name' => $def['name'],
                            'component' => $comp['label'],
                            'qty' => 1,
                        ];
                    }
                }
            } elseif (!empty($tiffin->items)) {
                $rawItems = is_array($tiffin->items) ? $tiffin->items : json_decode($tiffin->items, true);
                if (is_array($rawItems)) {
                    $itemList = isset($rawItems['basic']) ? $rawItems['basic'] : (isset($rawItems[0]) ? $rawItems : []);
                    foreach ($itemList as $rawIt) {
                        $itName = is_numeric($rawIt) ? optional($allItems->get($rawIt))->name : (string)$rawIt;
                        if ($itName) {
                            $resolvedItems[] = [
                                'name' => $itName,
                                'component' => 'Included Item',
                                'qty' => 1,
                            ];
                        }
                    }
                }
            }
        }
    }
    
    // Process resolved items from tiffin
    foreach ($resolvedItems as $rItem) {
        $name = $rItem['name'];
        $component = $rItem['component'];
        $baseQty = $rItem['qty'];
        
        $isBread = (stripos($name, 'roti') !== false || stripos($name, 'thepla') !== false || 
                    stripos($name, 'bhakhri') !== false || stripos($name, 'bhakri') !== false || 
                    stripos($name, 'paratha') !== false || stripos($name, 'naan') !== false || 
                    stripos($name, 'puri') !== false || stripos($component, 'bread') !== false);
        
        $multiplier = $isBread ? 4 : 1;
        if (preg_match('/\((\d+)\s*pcs?\)/i', $name, $m)) {
            $multiplier = (int)$m[1];
        }
        
        $itemTotalUnits = $orderQty * $baseQty * $multiplier;
        $unit = $isBread ? 'Rotis' : (preg_match('/\((\d+)\s*pcs?\)/i', $name) ? 'Pieces' : 'Portions');
        
        // Category determination
        $cat = 'Curries & Mains';
        if ($isBread) {
            $cat = 'Breads / Rotis';
        } elseif (stripos($name, 'salad') !== false) {
            $cat = 'Salads & Sides';
        } elseif (stripos($name, 'rice') !== false || stripos($name, 'biryani') !== false || stripos($name, 'pulaw') !== false) {
            $cat = 'Rice Dishes';
        } elseif (stripos($name, 'jamun') !== false || stripos($name, 'halwa') !== false || stripos($name, 'malai') !== false || stripos($name, 'kheer') !== false || stripos($name, 'jalebi') !== false || stripos($name, 'kulfi') !== false) {
            $cat = 'Desserts & Sweets';
        } elseif (stripos($name, 'lassi') !== false || stripos($name, 'tea') !== false || stripos($name, 'buttermilk') !== false || stripos($name, 'soda') !== false || stripos($name, 'shake') !== false || stripos($name, 'coca') !== false) {
            $cat = 'Beverages';
        }
        
        if (!isset($itemTotals[$name])) {
            $itemTotals[$name] = [
                'name' => $name,
                'category' => $cat,
                'unit' => $unit,
                'total_qty' => 0,
                'tiffin_qty' => 0,
                'addon_qty' => 0,
                'orders_count' => 0,
            ];
        }
        $itemTotals[$name]['total_qty'] += $itemTotalUnits;
        $itemTotals[$name]['tiffin_qty'] += $itemTotalUnits;
        $itemTotals[$name]['orders_count']++;
    }
    
    // 2. Process Add-ons
    if ($order->add_ons) {
        $addons = is_array($order->add_ons) ? $order->add_ons : json_decode($order->add_ons, true);
        if (is_array($addons)) {
            foreach ($addons as $addon) {
                $name = trim($addon['name'] ?? '');
                if (!$name) continue;
                $aQty = (int)($addon['qty'] ?? 1) * $orderQty;
                
                $isBread = (stripos($name, 'roti') !== false || stripos($name, 'thepla') !== false || 
                            stripos($name, 'bhakhri') !== false || stripos($name, 'bhakri') !== false || 
                            stripos($name, 'paratha') !== false || stripos($name, 'naan') !== false);
                
                $unit = $isBread ? 'Rotis' : (preg_match('/\((\d+)\s*pcs?\)/i', $name) ? 'Pieces' : 'Portions');
                
                $cat = 'Curries & Mains';
                if ($isBread) {
                    $cat = 'Breads / Rotis';
                } elseif (stripos($name, 'salad') !== false) {
                    $cat = 'Salads & Sides';
                } elseif (stripos($name, 'rice') !== false || stripos($name, 'biryani') !== false) {
                    $cat = 'Rice Dishes';
                } elseif (stripos($name, 'jamun') !== false || stripos($name, 'halwa') !== false) {
                    $cat = 'Desserts & Sweets';
                } elseif (stripos($name, 'lassi') !== false || stripos($name, 'tea') !== false || stripos($name, 'soda') !== false) {
                    $cat = 'Beverages';
                }
                
                if (!isset($itemTotals[$name])) {
                    $itemTotals[$name] = [
                        'name' => $name,
                        'category' => $cat,
                        'unit' => $unit,
                        'total_qty' => 0,
                        'tiffin_qty' => 0,
                        'addon_qty' => 0,
                        'orders_count' => 0,
                    ];
                }
                $itemTotals[$name]['total_qty'] += $aQty;
                $itemTotals[$name]['addon_qty'] += $aQty;
                $itemTotals[$name]['orders_count']++;
            }
        }
    }
}

echo "Enhanced Food Items Preparation Estimates:\n";
foreach ($itemTotals as $name => $info) {
    echo " - {$name} [{$info['category']}]: {$info['total_qty']} {$info['unit']} (From Tiffins: {$info['tiffin_qty']}, Addons: {$info['addon_qty']}, In {$info['orders_count']} orders)\n";
}

echo "\nEnhanced Test Complete!\n";
