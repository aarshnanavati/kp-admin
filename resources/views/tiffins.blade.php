@extends('layouts.app')

@section('title', 'Manage Tiffins')
@section('current_page', 'tiffins')
@section('page_title', 'Manage Tiffins')
@section('page_subtitle', 'Add, update and remove tiffin plans.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="tiffinsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Manage Tiffins</h2>
      <p class="kp_kitchen_admin_panel_section_text">Add, update or remove tiffin plans.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addTiffinButton">+ Add Tiffin</button>
  </div>

  <div class="kp_kitchen_admin_panel_tiffin_grid" id="tiffinsGrid">
    @forelse ($tiffins as $tiffin)
      <article class="kp_kitchen_admin_panel_tiffin_card">
        <div class="kp_kitchen_admin_panel_tiffin_image">
          @if ($tiffin->image)
            <img class="kp_kitchen_admin_panel_tiffin_photo" src="{{ asset($tiffin->image) }}" alt="{{ $tiffin->name }}">
          @else
            <span class="kp_kitchen_admin_panel_tiffin_emoji">🍱</span>
          @endif
          <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $tiffin->status)) }}">{{ $tiffin->status }}</span>
        </div>
        <div class="kp_kitchen_admin_panel_tiffin_content">
          <span class="kp_kitchen_admin_panel_tiffin_type">{{ $tiffin->prep_time }} min prep</span>
          <h3 class="kp_kitchen_admin_panel_tiffin_name">{{ $tiffin->name }}</h3>
          <p class="kp_kitchen_admin_panel_tiffin_description" style="font-size:0.85rem; opacity:0.8; margin-bottom: 8px;">{{ $tiffin->description ?? 'No description.' }}</p>
          
          <div style="font-size: 0.75rem; font-weight:600; color: var(--primary-color); margin: 8px 0 4px 0;">Included Items:</div>
          <div class="kp_kitchen_admin_panel_tiffin_item_chips" style="margin-bottom: 12px;">
            @php
              $tiffinItems = is_array($tiffin->items) ? $tiffin->items : (json_decode($tiffin->items, true) ?: []);
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
            @forelse ($basicItems as $name)
              <span class="kp_kitchen_admin_panel_tiffin_item_chip">{{ $name }}</span>
            @empty
              <span style="opacity:0.5; font-size:0.75rem;">None</span>
            @endforelse
          </div>

          <div class="kp_kitchen_admin_panel_tiffin_footer">
            <strong class="kp_kitchen_admin_panel_tiffin_price">${{ number_format($tiffin->price, 2) }}</strong>
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
      <div class="kp_kitchen_admin_panel_empty_state" style="grid-column: 1 / -1;">No tiffin plans found. Click "+ Add Tiffin" to create one.</div>
    @endforelse
  </div>
</section>
@endsection
