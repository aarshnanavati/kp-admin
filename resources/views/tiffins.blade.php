@extends('layouts.app')

@section('title', 'Manage Tiffins')
@section('current_page', 'tiffins')
@section('page_title', 'Manage Tiffins')
@section('page_subtitle', 'Add, update and remove tiffin plans.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="tiffinsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar" style="flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Manage Tiffins</h2>
      <p class="kp_kitchen_admin_panel_section_text">Manage fixed ready-made meal plans and customizable build-your-own tiffins.</p>
    </div>

    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <form method="GET" action="{{ route('tiffins') }}" style="display: flex; gap: 8px; align-items: center; margin: 0;">
        <input type="hidden" name="tab" value="{{ $tab ?? 'fixed' }}">
        <div style="position: relative; display: flex; align-items: center; margin-bottom: 0;">
          <span style="position: absolute; left: 10px; color: var(--text-secondary); opacity: 0.7; pointer-events: none; display: flex; align-items: center; justify-content: center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </span>
          <input type="text" name="search" value="{{ request('search') }}" class="kp_kitchen_admin_panel_form_input" placeholder="Search tiffins..." style="padding: 6px 12px 6px 32px; font-size: 0.85rem; min-width: 190px; max-height: 38px; margin-bottom: 0;">
        </div>
        <button type="submit" class="kp_kitchen_admin_panel_secondary_button" style="padding: 7px 12px; font-size: 0.85rem; height: 38px;">Search</button>
      </form>
      <button class="kp_kitchen_admin_panel_primary_button" id="addTiffinButton" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">+ Add Tiffin</button>
    </div>
  </div>

  {{-- Navigation Tabs matching Mobile App structure --}}
  <div class="kp_kitchen_admin_panel_tabs" style="display: flex; gap: 8px; margin: 16px 0 24px 0; border-bottom: 1px solid var(--panel-border); padding-bottom: 12px; align-items: center; flex-wrap: wrap;">
    <a href="{{ route('tiffins', array_merge(request()->query(), ['tab' => 'fixed'])) }}"
       class="{{ $tab === 'fixed' ? 'kp_kitchen_admin_panel_primary_button' : 'kp_kitchen_admin_panel_secondary_button' }}"
       style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
      🍱 Fixed Tiffins
      <span style="background: {{ $tab === 'fixed' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.08)' }}; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold;">
        {{ $fixedCount }}
      </span>
    </a>

    <a href="{{ route('tiffins', array_merge(request()->query(), ['tab' => 'custom'])) }}"
       class="{{ $tab === 'custom' ? 'kp_kitchen_admin_panel_primary_button' : 'kp_kitchen_admin_panel_secondary_button' }}"
       style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
      ✨ Customise Your Own
      <span style="background: {{ $tab === 'custom' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.08)' }}; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold;">
        {{ $customCount }}
      </span>
    </a>

    <a href="{{ route('tiffins', array_merge(request()->query(), ['tab' => 'all'])) }}"
       class="{{ $tab === 'all' ? 'kp_kitchen_admin_panel_primary_button' : 'kp_kitchen_admin_panel_secondary_button' }}"
       style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
      📋 All Plans
      <span style="background: {{ $tab === 'all' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.08)' }}; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold;">
        {{ $allCount }}
      </span>
    </a>
  </div>

  {{-- Customise Your Own Informational Banner --}}
  @if ($tab === 'custom')
    <div style="background: linear-gradient(135deg, rgba(255, 107, 107, 0.08), rgba(255, 142, 83, 0.08)); border: 1px solid var(--primary-color); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px;">
      <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
        <span style="font-size: 1.5rem;">✨</span>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; color: var(--primary-color); font-weight: 700;">Customise Your Own Tiffin Tab</h3>
          <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">
            In the mobile app, this plan renders on the dedicated <strong>&ldquo;Customise Your Own&rdquo;</strong> tab. Customers pick their own items dynamically from the daily menu pool below.
          </p>
        </div>
      </div>
    </div>
  @endif

  {{-- Tiffins Grid --}}
  <div class="kp_kitchen_admin_panel_tiffin_grid" id="tiffinsGrid">
    @forelse ($tiffins as $tiffin)
      <article class="kp_kitchen_admin_panel_tiffin_card" style="{{ $tiffin->is_customizable ? 'border: 1.5px solid var(--primary-color); box-shadow: 0 4px 14px rgba(255, 107, 107, 0.12);' : '' }}">
        <div class="kp_kitchen_admin_panel_tiffin_image">
          @if ($tiffin->image)
            <img class="kp_kitchen_admin_panel_tiffin_photo" src="{{ asset($tiffin->image) }}" alt="{{ $tiffin->name }}">
          @else
            <span class="kp_kitchen_admin_panel_tiffin_emoji">{{ $tiffin->is_customizable ? '✨' : '🍱' }}</span>
          @endif
          <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $tiffin->status)) }}">{{ $tiffin->status }}</span>
        </div>
        <div class="kp_kitchen_admin_panel_tiffin_content">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span class="kp_kitchen_admin_panel_tiffin_type">{{ $tiffin->prep_time }} min prep</span>
            @if ($tiffin->is_customizable)
              <span style="background: rgba(255, 107, 107, 0.15); color: var(--primary-color); font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; text-transform: uppercase;">
                Customizable
              </span>
            @else
              <span style="background: rgba(52, 152, 219, 0.12); color: #2980b9; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; text-transform: uppercase;">
                Fixed Plan
              </span>
            @endif
          </div>

          <h3 class="kp_kitchen_admin_panel_tiffin_name">{{ $tiffin->name }}</h3>
          <p class="kp_kitchen_admin_panel_tiffin_description" style="font-size:0.85rem; opacity:0.8; margin-bottom: 8px;">{{ $tiffin->description ?? 'No description.' }}</p>

          @if ($tiffin->is_customizable)
            <div style="background:rgba(255,107,107,0.08); border:1px dashed var(--primary-color); border-radius:8px; padding:8px 10px; font-size:0.78rem; margin-bottom:12px;">
              <strong style="color:var(--primary-color);">Dynamic Pricing:</strong>
              Sum of items selected by the customer from today's menu pool.
            </div>
          @endif

          <div style="font-size: 0.75rem; font-weight:600; color: var(--primary-color); margin: 8px 0 4px 0;">
            {{ $tiffin->is_customizable ? 'Components / Options:' : 'Included Items:' }}
          </div>
          @php
            $tiffinItems = is_array($tiffin->items) ? $tiffin->items : (json_decode($tiffin->items, true) ?: []);
            $components = $tiffin->components;
            $basicItems = [];
            if (isset($tiffinItems['basic']) && is_array($tiffinItems['basic'])) {
                $basicItems = $tiffinItems['basic'];
            } else {
                foreach ($tiffinItems as $val) {
                    if (is_numeric($val)) {
                        if (isset($itemsMap[$val])) {
                            $basicItems[] = $itemsMap[$val];
                        }
                    } elseif (is_string($val)) {
                        $basicItems[] = $val;
                    }
                }
            }
          @endphp

          @if (!empty($components))
            <div class="kp_kitchen_admin_panel_tiffin_item_chips" style="margin-bottom: 12px; display:flex; flex-wrap:wrap; gap:6px;">
              @foreach ($components as $comp)
                @if ($comp['type'] === 'fixed')
                  <span class="kp_kitchen_admin_panel_tiffin_item_chip">{{ $comp['options'][0]['name'] ?? '' }}</span>
                @else
                  <span class="kp_kitchen_admin_panel_tiffin_item_chip" style="border:1px dashed var(--primary-color); background:transparent;">
                    @foreach ($comp['options'] as $i => $opt)@if ($i > 0) <em style="opacity:0.7; font-style:normal; color:var(--primary-color);">or</em> @endif{{ $opt['name'] }}@if (($opt['price_delta'] ?? 0) > 0) <small style="opacity:0.7;">(+${{ number_format($opt['price_delta'], 2) }})</small>@endif @endforeach
                  </span>
                @endif
              @endforeach
            </div>
          @else
            <div class="kp_kitchen_admin_panel_tiffin_item_chips" style="margin-bottom: 12px;">
              @forelse ($basicItems as $name)
                <span class="kp_kitchen_admin_panel_tiffin_item_chip">{{ $name }}</span>
              @empty
                <span style="opacity:0.5; font-size:0.75rem;">None</span>
              @endforelse
            </div>
          @endif

          <div class="kp_kitchen_admin_panel_tiffin_footer">
            <strong class="kp_kitchen_admin_panel_tiffin_price">
              @if ($tiffin->is_customizable)
                <span style="font-size: 0.9rem; font-weight: normal; color: var(--text-secondary);">Calculated at checkout</span>
              @else
                ${{ number_format($tiffin->price, 2) }}
              @endif
            </strong>
            <div class="kp_kitchen_admin_panel_card_actions">
              <button class="kp_kitchen_admin_panel_small_button edit-tiffin-btn"
                data-id="{{ $tiffin->id }}"
                data-name="{{ $tiffin->name }}"
                data-price="{{ $tiffin->price }}"
                data-category_id="{{ $tiffin->category_id }}"
                data-prep_time="{{ $tiffin->prep_time }}"
                data-status="{{ $tiffin->status }}"
                data-description="{{ $tiffin->description }}"
                data-image="{{ $tiffin->image }}"
                data-is_customizable="{{ $tiffin->is_customizable ? '1' : '' }}"
                data-items="{{ json_encode($tiffinItems) }}">
                Edit
              </button>
              <form method="POST" action="{{ route('tiffins.delete', $tiffin->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this tiffin plan?');">
                @csrf
                <button type="submit" class="kp_kitchen_admin_panel_danger_button">Delete</button>
              </form>
            </div>
          </div>
        </div>
      </article>
    @empty
      <div class="kp_kitchen_admin_panel_empty_state" style="grid-column: 1 / -1; padding: 40px 20px; text-align: center;">
        <div style="font-size: 2rem; margin-bottom: 10px;">🍱</div>
        <p style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-primary);">
          No {{ $tab === 'custom' ? 'customizable' : ($tab === 'fixed' ? 'fixed' : '') }} tiffin plans found.
        </p>
        <p style="margin: 6px 0 16px 0; font-size: 0.85rem; color: var(--text-secondary);">
          Click "+ Add Tiffin" to create a new tiffin plan.
        </p>
      </div>
    @endforelse
  </div>

  {{-- Customization Pool Breakdown (Visible when on Customise tab) --}}
  @if ($tab === 'custom' && !empty($customItemPool))
    <div style="margin-top: 40px; border-top: 1px solid var(--panel-border); padding-top: 24px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
          <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">
            🥣 Customization Dynamic Menu Pool
          </h3>
          <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">
            These items are available for customer selection in the &ldquo;Customise Your Own&rdquo; tab of the app:
          </p>
        </div>
        <span style="font-size: 0.8rem; background: var(--card-bg); border: 1px solid var(--panel-border); padding: 4px 10px; border-radius: 20px; font-weight: 600;">
          {{ count($customItemPool) }} Available Items
        </span>
      </div>

      @php
        $groupedPool = [];
        foreach ($customItemPool as $entry) {
            $cat = $entry['category'] ?? 'Other';
            $groupedPool[$cat][] = $entry;
        }
      @endphp

      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
        @foreach ($groupedPool as $catName => $catItems)
          <div style="background: var(--card-bg); border: 1px solid var(--panel-border); border-radius: 10px; padding: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--panel-border); padding-bottom: 8px; margin-bottom: 10px;">
              <strong style="color: var(--primary-color); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">{{ $catName }}</strong>
              <span style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">{{ count($catItems) }} items</span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              @foreach ($catItems as $item)
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; padding: 4px 0;">
                  <span style="color: var(--text-primary); font-weight: 500;">{{ $item['name'] }}</span>
                  <strong style="color: var(--primary-color); font-size: 0.85rem;">
                    @if ($item['price'] > 0)
                      ${{ number_format($item['price'], 2) }}
                    @else
                      <span style="color: var(--text-secondary); font-size: 0.75rem; font-weight: normal;">Included</span>
                    @endif
                  </strong>
                </div>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

</section>
@endsection
