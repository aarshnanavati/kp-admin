<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tiffin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'items',
        'description',
        'prep_time',
        'status',
        'image',
        'category_id',
        'is_customizable',
    ];

    protected $casts = [
        'items' => 'array',
        'is_customizable' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Normalised list of "slot" components for this plan.
     *
     * Each component is:
     *   [
     *     'key'      => 'bread-2',            // stable id the client sends back
     *     'label'    => 'Bread',
     *     'type'     => 'fixed'|'single_choice',
     *     'required' => bool,
     *     'options'  => [
     *        ['item_id' => 5|null, 'name' => 'Bhakhari', 'price_delta' => 0.0, 'default' => true],
     *        ...
     *     ],
     *   ]
     *
     * Falls back to the legacy `items.basic` string list (each becomes a fixed
     * single-option component) when no explicit components are defined.
     */
    public function getComponentsAttribute(): array
    {
        $items = $this->items;
        $rawComponents = (is_array($items) && isset($items['components']) && is_array($items['components']))
            ? array_values($items['components'])
            : [];

        // Legacy fallback: turn the old free-text "basic" list into fixed slots.
        if (empty($rawComponents) && is_array($items) && !empty($items['basic']) && is_array($items['basic'])) {
            foreach ($items['basic'] as $basic) {
                $name = is_numeric($basic) ? optional(Item::find($basic))->name : (string) $basic;
                if ($name) {
                    $rawComponents[] = [
                        'label' => $name,
                        'type' => 'fixed',
                        'options' => [['name' => $name, 'price_delta' => 0, 'default' => true]],
                    ];
                }
            }
        }

        $out = [];

        foreach ($rawComponents as $idx => $comp) {
            if (!is_array($comp)) {
                continue;
            }

            $label = trim((string) ($comp['label'] ?? 'Item'));
            $rawOptions = (isset($comp['options']) && is_array($comp['options'])) ? array_values($comp['options']) : [];
            $options = [];

            foreach ($rawOptions as $opt) {
                if (!is_array($opt)) {
                    continue;
                }

                $itemId = (isset($opt['item_id']) && $opt['item_id'] !== '' && $opt['item_id'] !== null)
                    ? (int) $opt['item_id']
                    : null;
                $name = trim((string) ($opt['name'] ?? ''));

                if ($name === '' && $itemId) {
                    $name = (string) optional(Item::find($itemId))->name;
                }
                if ($name === '') {
                    continue;
                }

                $options[] = [
                    'item_id' => $itemId,
                    'name' => $name,
                    'price_delta' => round((float) ($opt['price_delta'] ?? 0), 2),
                    'default' => (bool) ($opt['default'] ?? false),
                ];
            }

            if (empty($options)) {
                continue;
            }

            // Guarantee exactly one default option.
            $seenDefault = false;
            foreach ($options as &$o) {
                if ($o['default'] && !$seenDefault) {
                    $seenDefault = true;
                } else {
                    $o['default'] = false;
                }
            }
            unset($o);
            if (!$seenDefault) {
                $options[0]['default'] = true;
            }

            $type = (($comp['type'] ?? null) === 'fixed' || count($options) < 2) ? 'fixed' : 'single_choice';
            $slug = Str::slug($label) ?: 'item';

            $out[] = [
                'key' => $slug . '-' . ($idx + 1),
                'label' => $label,
                'type' => $type,
                'required' => $type === 'fixed' ? true : (bool) ($comp['required'] ?? true),
                'options' => $options,
            ];
        }

        return $out;
    }

    /**
     * Map of componentKey => default option name.
     */
    public function defaultSelectionMap(): array
    {
        $map = [];
        foreach ($this->components as $comp) {
            foreach ($comp['options'] as $opt) {
                if ($opt['default']) {
                    $map[$comp['key']] = $opt['name'];
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Resolve a raw selection payload against this plan's components.
     *
     * Accepts either:
     *   ['bread-2' => 'Methi Thepla', ...]                       (key => chosen name)
     *   [['component' => 'bread-2', 'option' => 'Methi Thepla']] (list of pairs)
     *   a JSON string of either of the above
     *
     * Returns:
     *   [
     *     'choices' => [
     *        ['component' => 'Bread', 'key' => 'bread-2', 'chosen' => 'Methi Thepla',
     *         'item_id' => null, 'price_delta' => 2.0],
     *        ...
     *     ],
     *     'delta'   => 2.0,                 // total price delta of all chosen options
     *     'summary' => 'Bread: Methi Thepla',
     *     'errors'  => ['Please choose an option for "Bread".'],
     *   ]
     */
    public function resolveSelections($input): array
    {
        $components = $this->components;

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($input)) {
            $input = [];
        }

        // Flatten to key => name.
        $wanted = [];
        foreach ($input as $k => $v) {
            if (is_array($v) && isset($v['component'])) {
                $wanted[(string) $v['component']] = (string) ($v['option'] ?? $v['chosen'] ?? $v['name'] ?? '');
            } else {
                $wanted[(string) $k] = is_array($v) ? (string) ($v['name'] ?? $v['chosen'] ?? '') : (string) $v;
            }
        }

        $choices = [];
        $summaryParts = [];
        $errors = [];
        $delta = 0.0;

        foreach ($components as $comp) {
            $requested = $wanted[$comp['key']] ?? null;
            $chosen = null;

            if ($requested !== null && $requested !== '') {
                foreach ($comp['options'] as $opt) {
                    if (mb_strtolower($opt['name']) === mb_strtolower(trim($requested))) {
                        $chosen = $opt;
                        break;
                    }
                }
                if (!$chosen) {
                    $errors[] = "\"{$requested}\" is not a valid choice for \"{$comp['label']}\".";
                }
            } elseif ($comp['type'] === 'single_choice' && $comp['required']) {
                $errors[] = "Please choose an option for \"{$comp['label']}\".";
            }

            if (!$chosen) {
                foreach ($comp['options'] as $opt) {
                    if ($opt['default']) {
                        $chosen = $opt;
                        break;
                    }
                }
            }
            if (!$chosen) {
                continue;
            }

            $delta += $chosen['price_delta'];
            $choices[] = [
                'component' => $comp['label'],
                'key' => $comp['key'],
                'chosen' => $chosen['name'],
                'item_id' => $chosen['item_id'],
                'price_delta' => $chosen['price_delta'],
            ];

            if ($comp['type'] === 'single_choice') {
                $summaryParts[] = $comp['label'] . ': ' . $chosen['name'];
            }
        }

        return [
            'choices' => $choices,
            'delta' => round($delta, 2),
            'summary' => implode(', ', $summaryParts),
            'errors' => $errors,
        ];
    }

    /**
     * Unit price of the plan for a given selection payload (base + chosen deltas).
     */
    public function unitPriceFor($input = null): float
    {
        $resolved = $this->resolveSelections($input ?? []);

        return round((float) $this->price + $resolved['delta'], 2);
    }

    /* -----------------------------------------------------------------
     |  Customizable ("Build Your Own") tiffin
     | ----------------------------------------------------------------- */

    /**
     * The pool of items a customer may pick for a Build-Your-Own tiffin.
     *
     * Built dynamically from every Active, non-customizable tiffin plan, so it
     * always reflects whatever the admin has in "today's menu". Each entry:
     *   ['id' => 'i:12'|'n:jeera-rice', 'item_id' => 12|null,
     *    'name' => 'Jeera Rice', 'price' => 0.0, 'category' => 'Rice']
     */
    public static function customizableItemPool(): array
    {
        $pool = [];

        try {
            $query = static::where('status', 'Active');
            if (\Illuminate\Support\Facades\Schema::hasTable('tiffins') && \Illuminate\Support\Facades\Schema::hasColumn('tiffins', 'is_customizable')) {
                $query->where(function ($q) {
                    $q->where('is_customizable', false)->orWhereNull('is_customizable');
                });
            }
            $sources = $query->get();
        } catch (\Throwable $e) {
            $sources = collect();
        }

        foreach ($sources as $tiffin) {
            try {
                foreach ($tiffin->components as $component) {
                    if (isset($component['options']) && is_array($component['options'])) {
                        foreach ($component['options'] as $option) {
                            static::pushPoolEntry($pool, $option['item_id'] ?? null, $option['name'] ?? '', $option['price_delta'] ?? 0);
                        }
                    }
                }

                $addons = (is_array($tiffin->items) && is_array($tiffin->items['addons'] ?? null))
                    ? $tiffin->items['addons']
                    : [];
                foreach ($addons as $addonId) {
                    $item = Item::with('category')->find($addonId);
                    if ($item) {
                        static::pushPoolEntry($pool, $item->id, $item->name, $item->price);
                    }
                }
            } catch (\Throwable $e) {
                // Skip problematic tiffin entries
            }
        }

        // If pool is empty, populate from all active menu items
        if (empty($pool)) {
            try {
                $allItems = Item::with('category')->where('status', 'Active')->get();
                foreach ($allItems as $item) {
                    static::pushPoolEntry($pool, $item->id, $item->name, $item->price);
                }
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        // Stable ordering: by category then name.
        $entries = array_values($pool);
        usort($entries, function ($a, $b) {
            return [($a['category'] ?? 'Other'), ($a['name'] ?? '')] <=> [($b['category'] ?? 'Other'), ($b['name'] ?? '')];
        });

        return $entries;
    }

    private static function pushPoolEntry(array &$pool, $itemId, $name, $priceHint): void
    {
        $name = trim((string) $name);
        $itemId = ($itemId !== '' && $itemId !== null) ? (int) $itemId : null;

        if ($name === '' && ! $itemId) {
            return;
        }

        $price = (float) $priceHint;
        $category = 'Other';

        if ($itemId) {
            $item = Item::with('category')->find($itemId);
            if ($item) {
                $name = $item->name;
                $price = (float) $item->price;
                $category = $item->category->name ?? 'Other';
            }
        }

        $key = $itemId ? 'i:' . $itemId : 'n:' . Str::slug($name);
        if (isset($pool[$key])) {
            return;
        }

        $pool[$key] = [
            'id' => $key,
            'item_id' => $itemId,
            'name' => $name,
            'price' => round($price, 2),
            'category' => $category,
        ];
    }

    /**
     * Validate & price a Build-Your-Own selection against today's pool.
     *
     * Accepts an array of pool ids ('i:12'), catalog item ids (12), item names,
     * or objects carrying any of those - or a JSON string of the same.
     * Returns ['items' => [...], 'count' => n, 'items_total' => 0.0,
     *          'unit_price' => 0.0, 'summary' => 'A, B, C', 'errors' => []]
     */
    public function resolveCustomItems($picked): array
    {
        if (is_string($picked)) {
            $decoded = json_decode($picked, true);
            $picked = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($picked)) {
            $picked = [];
        }

        $pool = static::customizableItemPool();
        $byId = [];
        $byItemId = [];
        $byName = [];
        foreach ($pool as $entry) {
            $byId[$entry['id']] = $entry;
            if ($entry['item_id']) {
                $byItemId[(int) $entry['item_id']] = $entry;
            }
            $byName[mb_strtolower($entry['name'])] = $entry;
        }

        $chosen = [];
        $errors = [];
        $itemsTotal = 0.0;

        foreach ($picked as $raw) {
            $token = is_array($raw)
                ? ($raw['id'] ?? $raw['item_id'] ?? $raw['name'] ?? null)
                : $raw;
            if ($token === null || $token === '') {
                continue;
            }

            $entry = $byId[(string) $token]
                ?? ($byItemId[(int) $token] ?? null)
                ?? ($byName[mb_strtolower(trim((string) $token))] ?? null);

            if (! $entry) {
                $errors[] = "\"{$token}\" is not on today's menu.";
                continue;
            }
            if (isset($chosen[$entry['id']])) {
                continue;
            }

            $chosen[$entry['id']] = $entry;
            $itemsTotal += $entry['price'];
        }

        $chosen = array_values($chosen);
        $count = count($chosen);

        if ($count === 0) {
            $errors[] = 'Please pick at least one item for your custom tiffin.';
        }

        // Price is simply the sum of the picked items' prices (no base price).
        $unitPrice = round($itemsTotal, 2);

        return [
            'items' => $chosen,
            'count' => $count,
            'items_total' => round($itemsTotal, 2),
            'unit_price' => $unitPrice,
            'summary' => implode(', ', array_column($chosen, 'name')),
            'errors' => $errors,
        ];
    }
}
