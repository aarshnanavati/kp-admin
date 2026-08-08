<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | KP Kitchen Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" />
    <!-- Chart.js CDN for visual business reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  </head>

  <body class="kp_kitchen_admin_panel_body" data-current-page="@yield('current_page')">
    <div class="kp_kitchen_admin_panel_app">
      <aside id="sidebar" class="kp_kitchen_admin_panel_sidebar">
        <div class="kp_kitchen_admin_panel_sidebar_brand">
          <div class="kp_kitchen_admin_panel_brand_mark">KP</div>
          <div class="kp_kitchen_admin_panel_brand_text_wrap">
            <strong class="kp_kitchen_admin_panel_brand_name">KP Kitchen</strong>
            <span class="kp_kitchen_admin_panel_brand_subtitle">Admin Panel</span>
          </div>
          <button
            id="sidebarClose"
            class="kp_kitchen_admin_panel_sidebar_close"
            aria-label="Close sidebar"
          >
            ×
          </button>
        </div>
        <nav class="kp_kitchen_admin_panel_navigation">
          <!-- Standalone link for Dashboard -->
          <a
            class="kp_kitchen_admin_panel_nav_item {{ request()->routeIs('dashboard') ? 'kp_kitchen_admin_panel_nav_item_active' : '' }}"
            href="{{ route('dashboard') }}"
          >
            <span class="kp_kitchen_admin_panel_nav_icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
            </span>
            <span class="kp_kitchen_admin_panel_nav_text">Dashboard</span>
          </a>

          <!-- Menu Management Group -->
          <div class="kp_kitchen_admin_panel_nav_group">
            <button type="button" class="kp_kitchen_admin_panel_nav_group_header">
              <span class="kp_kitchen_admin_panel_nav_icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
              </span>
              <span class="kp_kitchen_admin_panel_nav_text">Menu Management</span>
              <span class="kp_kitchen_admin_panel_nav_arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
            </button>
            <div class="kp_kitchen_admin_panel_nav_group_items">
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('categories') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('categories') }}">Categories</a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('items') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('items') }}">Menu Items</a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('tiffins') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('tiffins') }}">Tiffin Plans</a>
            </div>
          </div>

          <!-- Operations Group -->
          <div class="kp_kitchen_admin_panel_nav_group">
            <button type="button" class="kp_kitchen_admin_panel_nav_group_header">
              <span class="kp_kitchen_admin_panel_nav_icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </span>
              <span class="kp_kitchen_admin_panel_nav_text">Operations</span>
              <span class="kp_kitchen_admin_panel_nav_arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
            </button>
            <div class="kp_kitchen_admin_panel_nav_group_items">
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('orders') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('orders') }}">
                Orders <span id="orderBadge" class="kp_kitchen_admin_panel_nav_badge" style="margin-left:8px;">0</span>
              </a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('invoices') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('invoices') }}">Invoices</a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('payments') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('payments') }}">Payments</a>
            </div>
          </div>

          <!-- People Management Group -->
          <div class="kp_kitchen_admin_panel_nav_group">
            <button type="button" class="kp_kitchen_admin_panel_nav_group_header">
              <span class="kp_kitchen_admin_panel_nav_icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
              <span class="kp_kitchen_admin_panel_nav_text">People Management</span>
              <span class="kp_kitchen_admin_panel_nav_arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
            </button>
            <div class="kp_kitchen_admin_panel_nav_group_items">
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('customers') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('customers') }}">Customers</a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('drivers') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('drivers') }}">Drivers</a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('users') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('users') }}">Admin Users</a>
            </div>
          </div>

          <!-- Marketing & Settings Group -->
          <div class="kp_kitchen_admin_panel_nav_group">
            <button type="button" class="kp_kitchen_admin_panel_nav_group_header">
              <span class="kp_kitchen_admin_panel_nav_icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
              </span>
              <span class="kp_kitchen_admin_panel_nav_text">Marketing &amp; Settings</span>
              <span class="kp_kitchen_admin_panel_nav_arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
            </button>
            <div class="kp_kitchen_admin_panel_nav_group_items">
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('coupons') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('coupons') }}">Coupons</a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('reports') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('reports') }}">Reports</a>
              <a class="kp_kitchen_admin_panel_nav_sub_item {{ request()->routeIs('notifications') ? 'kp_kitchen_admin_panel_nav_sub_item_active' : '' }}" href="{{ route('notifications') }}">
                Notifications <span id="notificationBadge" class="kp_kitchen_admin_panel_nav_badge" style="margin-left:8px;">0</span>
              </a>
            </div>
          </div>
        </nav>
        <div class="kp_kitchen_admin_panel_sidebar_footer">
          <div class="kp_kitchen_admin_panel_sidebar_help">
            <strong class="kp_kitchen_admin_panel_sidebar_help_title">Need help?</strong>
            <span class="kp_kitchen_admin_panel_sidebar_help_text">Contact technical support.</span>
          </div>
          <button
            id="logoutButton"
            class="kp_kitchen_admin_panel_logout_button"
          >
            Logout
          </button>
        </div>
      </aside>
      <div
        id="sidebarOverlay"
        class="kp_kitchen_admin_panel_sidebar_overlay"
      ></div>
      <section class="kp_kitchen_admin_panel_main">
        <header class="kp_kitchen_admin_panel_header">
          <div class="kp_kitchen_admin_panel_header_left">
            <button
              id="sidebarToggle"
              class="kp_kitchen_admin_panel_menu_button"
              aria-label="Open sidebar"
            >
              ☰
            </button>
            <div class="kp_kitchen_admin_panel_header_title_wrap">
              <h1 id="pageTitle" class="kp_kitchen_admin_panel_page_title">
                @yield('page_title')
              </h1>
              <p class="kp_kitchen_admin_panel_page_subtitle">
                @yield('page_subtitle')
              </p>
            </div>
          </div>
          <div class="kp_kitchen_admin_panel_header_right">
            <label class="kp_kitchen_admin_panel_header_search">
              <span class="kp_kitchen_admin_panel_header_search_icon">⌕</span>
              <input
                id="globalSearch"
                class="kp_kitchen_admin_panel_header_search_input"
                type="search"
                placeholder="Search..."
              />
            </label>
            <button
              id="themeToggle"
              class="kp_kitchen_admin_panel_theme_button"
              type="button"
              aria-label="Toggle color theme"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="theme-icon"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <a
              class="kp_kitchen_admin_panel_header_notification_button"
              href="{{ route('notifications') }}"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              <span
                id="headerNotificationCount"
                class="kp_kitchen_admin_panel_header_notification_count"
                >0</span
              >
            </a>
            <div class="kp_kitchen_admin_panel_admin_profile">
              <div class="kp_kitchen_admin_panel_admin_avatar">{{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}</div>
              <div class="kp_kitchen_admin_panel_admin_info">
                <strong id="adminName" class="kp_kitchen_admin_panel_admin_name">
                  {{ Auth::user()->name ?? 'Admin' }}
                </strong>
                <span class="kp_kitchen_admin_panel_admin_role">Administrator</span>
              </div>
            </div>
          </div>
        </header>
        <main class="kp_kitchen_admin_panel_content">
          @yield('content')
        </main>
      </section>
    </div>
    
    <!-- Unified CRUD Modal -->
    <div id="modal" class="kp_kitchen_admin_panel_modal">
      <div class="kp_kitchen_admin_panel_modal_dialog">
        <div class="kp_kitchen_admin_panel_modal_header">
          <h3 id="modalTitle" class="kp_kitchen_admin_panel_modal_title">
            Add Element
          </h3>
          <button id="modalClose" class="kp_kitchen_admin_panel_modal_close">
            ×
          </button>
        </div>
        <form id="modalForm" class="kp_kitchen_admin_panel_modal_form"></form>
      </div>
    </div>
    
    <!-- Secondary View Details Modal -->
    <div id="detailsModal" class="kp_kitchen_admin_panel_modal">
      <div class="kp_kitchen_admin_panel_modal_dialog kp_kitchen_admin_panel_modal_large">
        <div class="kp_kitchen_admin_panel_modal_header">
          <h3 id="detailsModalTitle" class="kp_kitchen_admin_panel_modal_title">
            Details View
          </h3>
          <button id="detailsModalClose" class="kp_kitchen_admin_panel_modal_close">
            ×
          </button>
        </div>
        <div id="detailsModalContent" class="kp_kitchen_admin_panel_modal_content_body"></div>
      </div>
    </div>

    <div id="toast" class="kp_kitchen_admin_panel_toast"></div>
    
    <script>
      window.currentAdmin = {
        name: "{{ Auth::user()->name ?? 'Admin' }}",
        email: "{{ Auth::user()->email ?? 'admin@kpkitchen.com' }}"
      };
    </script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
  </body>
</html>
