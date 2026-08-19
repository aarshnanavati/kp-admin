@extends('layouts.app')

@section('title', 'Menu Items')
@section('current_page', 'items')
@section('page_title', 'Menu Items')
@section('page_subtitle', 'Manage individual items that can be ordered or added to tiffins.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="itemsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Menu Items</h2>
      <p class="kp_kitchen_admin_panel_section_text">Manage sides, extra rotis, drinks, and individual dishes.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addItemButton">+ Add Menu Item</button>
  </div>

  <div class="kp_kitchen_admin_panel_tiffin_grid" id="itemsGrid">
    @forelse ($items as $item)
      <article class="kp_kitchen_admin_panel_tiffin_card">
        <div class="kp_kitchen_admin_panel_tiffin_image">
          @if ($item->image)
            <img class="kp_kitchen_admin_panel_tiffin_photo" src="{{ asset($item->image) }}" alt="{{ $item->name }}">
          @else
            <span class="kp_kitchen_admin_panel_tiffin_emoji">🍛</span>
          @endif
          <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $item->status)) }}">{{ $item->status }}</span>
        </div>
        <div class="kp_kitchen_admin_panel_tiffin_content">
          <span class="kp_kitchen_admin_panel_tiffin_type">{{ $item->category->name ?? 'Unassigned' }}</span>
          <h3 class="kp_kitchen_admin_panel_tiffin_name">{{ $item->name }}</h3>
          <p class="kp_kitchen_admin_panel_tiffin_description">{{ $item->description ?? 'No description.' }}</p>
          <div class="kp_kitchen_admin_panel_tiffin_footer">
            <strong class="kp_kitchen_admin_panel_tiffin_price">${{ number_format($item->price, 2) }}</strong>
            <div class="kp_kitchen_admin_panel_card_actions">
              <button class="kp_kitchen_admin_panel_small_button edit-item-btn"
                data-id="{{ $item->id }}"
                data-name="{{ $item->name }}"
                data-price="{{ $item->price }}"
                data-category_id="{{ $item->category_id }}"
                data-description="{{ $item->description }}"
                data-status="{{ $item->status }}"
                data-image="{{ $item->image }}">
                Edit
              </button>
              <form method="POST" action="{{ route('items.delete', $item->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this menu item?');">
                @csrf
                <button type="submit" class="kp_kitchen_admin_panel_danger_button">Delete</button>
              </form>
            </div>
          </div>
        </div>
      </article>
    @empty
      <div class="kp_kitchen_admin_panel_empty_state" style="grid-column: 1 / -1;">No menu items found. Click "+ Add Menu Item" to create one.</div>
    @endforelse
  </div>
</section>
@endsection
