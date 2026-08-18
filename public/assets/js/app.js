(function () {
  const getElement = id => document.getElementById(id);
  
  // Admin credentials from global Blade variable
  const currentAdmin = window.currentAdmin || null;
  if (!currentAdmin && window.location.pathname.indexOf('/login') === -1 && window.location.pathname.indexOf('/register') === -1) { 
    window.location.href = './login'; 
    return; 
  }

  // App State Data
  let drivers = [];
  let tiffins = [];
  let orders = [];
  let payments = [];
  let notifications = [];
  let categories = [];
  let items = [];
  let customers = [];
  let coupons = [];
  let invoices = [];
  let users = [];
  let trips = [];

  // Chart instances
  let ordersChartInstance = null;
  let itemsChartInstance = null;

  const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const getBaseUrl = () => (window.AppConfig && window.AppConfig.baseUrl) ? window.AppConfig.baseUrl : '';

  const getTiffinBasicItems = tiffin => {
    if (!tiffin || !tiffin.items) return [];
    if (Array.isArray(tiffin.items)) {
      return tiffin.items.map(val => {
        if (!isNaN(val)) {
          const mi = items.find(item => item.id === Number(val));
          return mi ? mi.name : '';
        }
        return val;
      }).filter(Boolean);
    }
    if (tiffin.items.basic && Array.isArray(tiffin.items.basic)) {
      return tiffin.items.basic;
    }
    return [];
  };

  const getTiffinAddonIds = tiffin => {
    if (!tiffin || !tiffin.items) return [];
    if (Array.isArray(tiffin.items)) {
      return [];
    }
    if (tiffin.items.addons && Array.isArray(tiffin.items.addons)) {
      return tiffin.items.addons.map(id => Number(id));
    }
    return [];
  };

  // Generic secure API request handler
  const apiRequest = async (url, method = 'GET', body = null) => {
    const cleanUrl = url.startsWith('/') ? url : '/' + url;
    const absoluteUrl = getBaseUrl() + cleanUrl;
    const options = {
      method,
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
      }
    };
    if (body) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    const response = await fetch(absoluteUrl, options);
    if (!response.ok) {
      const errData = await response.json();
      throw new Error(errData.message || 'API request failed.');
    }
    return response.json();
  };

  async function refreshData() {
    try {
      const data = await apiRequest('api/data');
      drivers = data.drivers || [];
      tiffins = data.tiffins || [];
      orders = data.orders || [];
      payments = data.payments || [];
      notifications = data.notifications || [];
      categories = data.categories || [];
      items = data.items || [];
      customers = data.customers || [];
      coupons = data.coupons || [];
      invoices = data.invoices || [];
      users = data.users || [];
      trips = data.trips || [];
      
      renderAll();
      renderCharts();
    } catch (e) {
      console.error("Error loading data from API:", e);
      showToast("Error loading data from server.");
    }
  }

  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  const formatCurrency = value => `$${Number(value).toFixed(2)}`;
  const statusClass = status => `kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_${String(status).toLowerCase().replaceAll(' ', '_')}`;
  const areaKey = area => String(area || '').trim().toLowerCase();

  // Populate search input from URL on load
  const getSearchQuery = () => {
    const params = new URLSearchParams(window.location.search);
    const searchVal = params.get('search') || '';
    if (searchVal && getElement('globalSearch')) {
      getElement('globalSearch').value = searchVal;
    }
    return searchVal.toLowerCase().trim();
  };

  // Color themes
  const applyTheme = theme => {
    document.documentElement.setAttribute('data-kp-theme', theme);
    localStorage.setItem('kpKitchenTheme', theme);
    const button = getElement('themeToggle');
    if (button) {
      button.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
    }
    // Re-draw charts with updated theme colors if on dashboard
    renderCharts();
  };
  
  applyTheme(localStorage.getItem('kpKitchenTheme') || 'light');
  if (getElement('themeToggle')) {
    getElement('themeToggle').addEventListener('click', () => {
      applyTheme(document.documentElement.getAttribute('data-kp-theme') === 'dark' ? 'light' : 'dark');
    });
  }

  // Sidebar toggle for smaller screens
  const sidebar = getElement('sidebar');
  const overlay = getElement('sidebarOverlay');
  const closeSidebar = () => { 
    sidebar.classList.remove('kp_kitchen_admin_panel_sidebar_open'); 
    overlay.classList.remove('kp_kitchen_admin_panel_sidebar_overlay_visible'); 
  };
  
  if (getElement('sidebarToggle')) getElement('sidebarToggle').addEventListener('click', () => { 
    sidebar.classList.add('kp_kitchen_admin_panel_sidebar_open'); 
    overlay.classList.add('kp_kitchen_admin_panel_sidebar_overlay_visible'); 
  });
  if (getElement('sidebarClose')) getElement('sidebarClose').addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  function showToast(message) {
    const toast = getElement('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('kp_kitchen_admin_panel_toast_visible');
    setTimeout(() => toast.classList.remove('kp_kitchen_admin_panel_toast_visible'), 2400);
  }

  function updateBadges() {
    const unread = notifications.filter(item => !item.read).length;
    const pending = orders.filter(item => item.status === 'Pending').length;
    if (getElement('notificationBadge')) getElement('notificationBadge').textContent = unread;
    if (getElement('headerNotificationCount')) getElement('headerNotificationCount').textContent = unread;
    if (getElement('orderBadge')) getElement('orderBadge').textContent = pending;
  }

  // --- Dynamic Dashboard overview ---
  function renderDashboard() {
    if (!getElement('statOrders')) return;
    getElement('statOrders').textContent = orders.length;
    getElement('statDrivers').textContent = drivers.filter(item => item.status === 'Active').length;
    getElement('statTiffins').textContent = tiffins.filter(item => item.status === 'Active').length;
    getElement('statRevenue').textContent = formatCurrency(payments.filter(item => item.status === 'Successful').reduce((sum, item) => sum + Number(item.amount), 0));
    
    getElement('dashboardOrdersBody').innerHTML = orders.slice(0, 5).map(order => 
      `<tr class="kp_kitchen_admin_panel_table_row">
        <td class="kp_kitchen_admin_panel_table_cell"><strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.customer)}</td>
        <td class="kp_kitchen_admin_panel_table_cell">${formatCurrency(order.amount)}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(order.status)}">${escapeHtml(order.status)}</span></td>
      </tr>`
    ).join('');
    
    const statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered'];
    getElement('deliverySummary').innerHTML = statuses.map(status => { 
      const count = orders.filter(item => item.status === status).length; 
      const percent = orders.length ? Math.round(count / orders.length * 100) : 0; 
      return `<div class="kp_kitchen_admin_panel_progress_item">
                <div class="kp_kitchen_admin_panel_progress_header">
                  <span class="kp_kitchen_admin_panel_progress_label">${status}</span>
                  <strong class="kp_kitchen_admin_panel_progress_value">${count}</strong>
                </div>
                <div class="kp_kitchen_admin_panel_progress_track">
                  <span class="kp_kitchen_admin_panel_progress_fill" style="width:${percent}%"></span>
                </div>
              </div>`; 
    }).join('');
  }

  // Draw Dashboard charts
  async function renderCharts() {
    if (!getElement('ordersChartCanvas') || !getElement('itemsChartCanvas')) return;
    
    try {
      const response = await apiRequest('api/dashboard-charts');
      const isDark = document.documentElement.getAttribute('data-kp-theme') === 'dark';
      const textColor = isDark ? '#94A3B8' : '#64748B';
      const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

      // Destroy previous chart instances to prevent flicker
      if (ordersChartInstance) ordersChartInstance.destroy();
      if (itemsChartInstance) itemsChartInstance.destroy();

      // Draw Orders Line Chart
      const ordersCtx = getElement('ordersChartCanvas').getContext('2d');
      const oGrad = ordersCtx.createLinearGradient(0, 0, 0, 300);
      oGrad.addColorStop(0, 'rgba(255, 107, 107, 0.4)');
      oGrad.addColorStop(1, 'rgba(255, 107, 107, 0)');

      ordersChartInstance = new Chart(ordersCtx, {
        type: 'line',
        data: {
          labels: response.ordersChart.labels,
          datasets: [{
            label: 'Orders Received',
            data: response.ordersChart.data,
            borderColor: '#FF6B6B',
            borderWidth: 3,
            backgroundColor: oGrad,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#FF8E53',
            pointHoverRadius: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: gridColor }, ticks: { color: textColor } },
            y: { grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } }
          }
        }
      });

      // Draw Popular Items Bar Chart (Vertical)
      const itemsCtx = getElement('itemsChartCanvas').getContext('2d');
      const iGrad = itemsCtx.createLinearGradient(0, 0, 0, 300);
      iGrad.addColorStop(0, '#FF6B6B');
      iGrad.addColorStop(1, '#FF8E53');

      itemsChartInstance = new Chart(itemsCtx, {
        type: 'bar',
        data: {
          labels: response.itemsChart.labels,
          datasets: [{
            data: response.itemsChart.data,
            backgroundColor: iGrad,
            borderRadius: 6,
            barThickness: 24
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { color: textColor } },
            y: { grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } }
          }
        }
      });

    } catch (e) {
      console.error("Failed to load dashboard chart analytics:", e);
    }
  }

  // --- Render Categories view ---
  function renderCategories() {
    if (!getElement('categoriesTableBody')) return;
    const search = getSearchQuery();
    const filtered = categories.filter(c => 
      !search || 
      c.name.toLowerCase().includes(search) || 
      (c.description && c.description.toLowerCase().includes(search))
    );

    getElement('categoriesTableBody').innerHTML = filtered.map(c => 
      `<tr class="kp_kitchen_admin_panel_table_row">
        <td class="kp_kitchen_admin_panel_table_cell"><strong>#CAT${c.id}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(c.name)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(c.description || 'No description.')}</td>
        <td class="kp_kitchen_admin_panel_table_cell">
          <div class="kp_kitchen_admin_panel_card_actions">
            <button class="kp_kitchen_admin_panel_small_button" data-view-category-items="${c.id}" style="background-color: var(--info-color); color: white;">View Items</button>
            <button class="kp_kitchen_admin_panel_small_button" data-edit-category="${c.id}">Edit</button>
            <button class="kp_kitchen_admin_panel_danger_button" data-delete-category="${c.id}">Delete</button>
          </div>
        </td>
      </tr>`
    ).join('') || '<tr><td colspan="4" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No categories found.</div></td></tr>';
  }

  // --- Render Menu Items view ---
  function renderItems() {
    if (!getElement('itemsGrid')) return;
    const search = getSearchQuery();
    const filtered = items.filter(item => 
      !search || 
      item.name.toLowerCase().includes(search) || 
      (item.category && item.category.name.toLowerCase().includes(search)) ||
      (item.description && item.description.toLowerCase().includes(search))
    );

    getElement('itemsGrid').innerHTML = filtered.map(item => {
      const image = item.image ? `<img class="kp_kitchen_admin_panel_tiffin_photo" src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}">` : '<span class="kp_kitchen_admin_panel_tiffin_emoji">🍛</span>';
      const categoryName = item.category ? item.category.name : 'Unassigned';
      return `<article class="kp_kitchen_admin_panel_tiffin_card">
                <div class="kp_kitchen_admin_panel_tiffin_image">
                  ${image}
                  <span class="${statusClass(item.status)}">${escapeHtml(item.status)}</span>
                </div>
                <div class="kp_kitchen_admin_panel_tiffin_content">
                  <span class="kp_kitchen_admin_panel_tiffin_type">${escapeHtml(categoryName)}</span>
                  <h3 class="kp_kitchen_admin_panel_tiffin_name">${escapeHtml(item.name)}</h3>
                  <p class="kp_kitchen_admin_panel_tiffin_description">${escapeHtml(item.description || 'No description.')}</p>
                  <div class="kp_kitchen_admin_panel_tiffin_footer">
                    <strong class="kp_kitchen_admin_panel_tiffin_price">${formatCurrency(item.price)}</strong>
                    <div class="kp_kitchen_admin_panel_card_actions">
                      <button class="kp_kitchen_admin_panel_small_button" data-edit-item="${item.id}">Edit</button>
                      <button class="kp_kitchen_admin_panel_danger_button" data-delete-item="${item.id}">Delete</button>
                    </div>
                  </div>
                </div>
              </article>`;
    }).join('') || '<div class="kp_kitchen_admin_panel_empty_state">No menu items found.</div>';
  }

  // --- Render Tiffin Plans view ---
  function renderTiffins() {
    if (!getElement('tiffinsGrid')) return;
    const search = getSearchQuery();
    const filtered = tiffins.filter(tiffin => {
      if (!search) return true;
      const nameMatch = tiffin.name.toLowerCase().includes(search);
      const descMatch = (tiffin.description && tiffin.description.toLowerCase().includes(search));
      
      let itemsMatch = false;
      const basicItems = getTiffinBasicItems(tiffin);
      if (basicItems.length > 0) {
        itemsMatch = basicItems.some(name => name.toLowerCase().includes(search));
      }
      return nameMatch || descMatch || itemsMatch;
    });

    getElement('tiffinsGrid').innerHTML = filtered.map(tiffin => {
      const image = tiffin.image ? `<img class="kp_kitchen_admin_panel_tiffin_photo" src="${escapeHtml(tiffin.image)}" alt="${escapeHtml(tiffin.name)}">` : '<span class="kp_kitchen_admin_panel_tiffin_emoji">🍱</span>';
      
      let chipsHtml = '';
      const basicItems = getTiffinBasicItems(tiffin);
      if (basicItems.length > 0) {
        chipsHtml = basicItems.map(name => `<span class="kp_kitchen_admin_panel_tiffin_item_chip">${escapeHtml(name)}</span>`).join('');
      }

      return `<article class="kp_kitchen_admin_panel_tiffin_card">
                <div class="kp_kitchen_admin_panel_tiffin_image">
                  ${image}
                  <span class="${statusClass(tiffin.status)}">${escapeHtml(tiffin.status)}</span>
                </div>
                <div class="kp_kitchen_admin_panel_tiffin_content">
                  <span class="kp_kitchen_admin_panel_tiffin_type">${escapeHtml(tiffin.prep_time)} min prep</span>
                  <h3 class="kp_kitchen_admin_panel_tiffin_name">${escapeHtml(tiffin.name)}</h3>
                  <p class="kp_kitchen_admin_panel_tiffin_description" style="font-size:0.85rem; opacity:0.8; margin-bottom: 8px;">${escapeHtml(tiffin.description || 'No description.')}</p>
                  <div style="font-size: 0.75rem; font-weight:600; color: var(--primary-color); margin: 8px 0 4px 0;">Included Items:</div>
                  <div class="kp_kitchen_admin_panel_tiffin_item_chips" style="margin-bottom: 12px;">
                    ${chipsHtml || '<span style="opacity:0.5; font-size:0.75rem;">None</span>'}
                  </div>
                  <div class="kp_kitchen_admin_panel_tiffin_footer">
                    <strong class="kp_kitchen_admin_panel_tiffin_price">${formatCurrency(tiffin.price)}</strong>
                    <div class="kp_kitchen_admin_panel_card_actions">
                      <button class="kp_kitchen_admin_panel_small_button" data-edit-tiffin="${tiffin.id}">Edit</button>
                      <button class="kp_kitchen_admin_panel_danger_button" data-delete-tiffin="${tiffin.id}">Delete</button>
                    </div>
                  </div>
                </div>
              </article>`;
    }).join('') || '<div class="kp_kitchen_admin_panel_empty_state">No tiffins found.</div>';
  }

  // --- Render Drivers list view ---
  function renderDrivers() {
    if (!getElement('driversGrid')) return;
    const search = getSearchQuery();
    const filtered = drivers.filter(driver => 
      !search || 
      driver.name.toLowerCase().includes(search) || 
      driver.phone.toLowerCase().includes(search) || 
      driver.area.toLowerCase().includes(search)
    );

    getElement('driversGrid').innerHTML = filtered.map(driver => {
      const activeDeliveries = orders.filter(order => order.driver === driver.name && order.status !== 'Delivered' && order.status !== 'Cancelled').length;
      
      const frontDoc = driver.license_copy_front 
        ? `<div class="kp_kitchen_admin_panel_doc_image_wrap"><img src="${escapeHtml(driver.license_copy_front)}" alt="Front"></div>` 
        : `<div class="kp_kitchen_admin_panel_doc_image_wrap"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>`;
        
      const backDoc = driver.license_copy_back 
        ? `<div class="kp_kitchen_admin_panel_doc_image_wrap"><img src="${escapeHtml(driver.license_copy_back)}" alt="Back"></div>` 
        : `<div class="kp_kitchen_admin_panel_doc_image_wrap"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>`;

      return `<article class="kp_kitchen_admin_panel_driver_card">
                <div class="kp_kitchen_admin_panel_driver_top">
                  <div class="kp_kitchen_admin_panel_driver_avatar">${escapeHtml(driver.name.charAt(0))}</div>
                  <span class="${statusClass(driver.status)}">${escapeHtml(driver.status)}</span>
                </div>
                <h3 class="kp_kitchen_admin_panel_driver_name">${escapeHtml(driver.name)}</h3>
                <p class="kp_kitchen_admin_panel_driver_meta">📞 ${escapeHtml(driver.phone)}</p>
                <p class="kp_kitchen_admin_panel_driver_meta">✉️ ${escapeHtml(driver.email || 'No email')}</p>
                <p class="kp_kitchen_admin_panel_driver_meta">📍 ${escapeHtml(driver.address || 'No address')}</p>
                <p class="kp_kitchen_admin_panel_driver_meta">🚙 Reg: <strong>${escapeHtml(driver.vehicle_reg_no || 'N/A')}</strong></p>
                <p class="kp_kitchen_admin_panel_driver_meta">📮 Postcode: <strong>${escapeHtml(driver.assigned_zip || 'N/A')}</strong></p>
                <p class="kp_kitchen_admin_panel_driver_meta">🛡️ License: <strong>${escapeHtml(driver.license_no || 'N/A')}</strong> (Exp: ${driver.license_expiry || 'N/A'})</p>
                <p class="kp_kitchen_admin_panel_driver_meta">📦 Active shipments: <strong>${activeDeliveries}</strong></p>
                
                <div class="kp_kitchen_admin_panel_doc_upload_grid">
                  <div class="kp_kitchen_admin_panel_doc_preview_card">
                    <span>License Front</span>
                    ${frontDoc}
                  </div>
                  <div class="kp_kitchen_admin_panel_doc_preview_card">
                    <span>License Back</span>
                    ${backDoc}
                  </div>
                </div>

                <div class="kp_kitchen_admin_panel_card_actions">
                  <button class="kp_kitchen_admin_panel_small_button" data-edit-driver="${driver.id}">Edit</button>
                  <button class="kp_kitchen_admin_panel_secondary_button" data-view-trips="${driver.id}">View Trips</button>
                  <button class="kp_kitchen_admin_panel_danger_button" data-delete-driver="${driver.id}">Delete</button>
                </div>
              </article>`;
    }).join('') || '<div class="kp_kitchen_admin_panel_empty_state">No drivers found.</div>';
  }

  // --- Render Customer Directory view ---
  function renderCustomers() {
    if (!getElement('customersTableBody')) return;
    const search = getSearchQuery();
    const filtered = customers.filter(c => 
      !search || 
      c.name.toLowerCase().includes(search) || 
      c.phone.toLowerCase().includes(search) || 
      c.email.toLowerCase().includes(search) || 
      c.pincode.toLowerCase().includes(search)
    );

    getElement('customersTableBody').innerHTML = filtered.map(c => 
      `<tr class="kp_kitchen_admin_panel_table_row">
        <td class="kp_kitchen_admin_panel_table_cell">#CUST${c.id}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(c.name)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(c.phone)}</td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(c.email)}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(c.pincode)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(c.address)}</td>
        <td class="kp_kitchen_admin_panel_table_cell">
          <div class="kp_kitchen_admin_panel_card_actions">
            <button class="kp_kitchen_admin_panel_small_button" data-view-customer="${c.id}">Customer Card</button>
            <button class="kp_kitchen_admin_panel_secondary_button" data-edit-customer="${c.id}">Edit</button>
            <button class="kp_kitchen_admin_panel_danger_button" data-delete-customer="${c.id}">Delete</button>
          </div>
        </td>
      </tr>`
    ).join('') || '<tr><td colspan="7" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No customers found.</div></td></tr>';
  }

  function renderOrders(filter = 'all') {
    if (!getElement('ordersTableBody')) return;
    const areaFilter = getElement('orderAreaFilter')?.value || 'all';
    const search = getSearchQuery();

    const startDate = getElement('orderStartDateFilter')?.value || '';
    const endDate = getElement('orderEndDateFilter')?.value || '';

    const filtered = orders.filter(order => {
      if (startDate && order.date < startDate) return false;
      if (endDate && order.date > endDate) return false;
      return (filter === 'all' || order.status === filter) && 
             (areaFilter === 'all' || order.area === areaFilter) &&
             (!search || 
               order.id.toLowerCase().includes(search) || 
               order.customer.toLowerCase().includes(search) || 
               order.tiffin.toLowerCase().includes(search) || 
               order.area.toLowerCase().includes(search)
             );
    });
    const statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];
    
    getElement('ordersTableBody').innerHTML = filtered.map(order => {
      // Find active drivers matching the order postcode
      const matchingDrivers = drivers.filter(driver => driver.status === 'Active' && areaKey(driver.area) === areaKey(order.area));
      const selectedDriverValid = matchingDrivers.some(driver => driver.name === order.driver);
      const driverOptions = matchingDrivers.map(driver => `<option value="${escapeHtml(driver.name)}" ${driver.name === order.driver ? 'selected' : ''}>${escapeHtml(driver.name)} — Postcode ${escapeHtml(driver.area)}</option>`).join('');
      const noDriver = matchingDrivers.length === 0;

      let addonsStr = '';
      if (order.add_ons) {
        try {
          const addons = JSON.parse(order.add_ons);
          addonsStr = addons.map(a => `<span class="kp_kitchen_admin_panel_tiffin_item_chip">${escapeHtml(a.name)} (x${a.qty})</span>`).join(' ');
        } catch(e) {}
      }

      return `<tr class="kp_kitchen_admin_panel_table_row">
                <td class="kp_kitchen_admin_panel_table_cell">
                  <strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong>
                  <span class="kp_kitchen_admin_panel_table_secondary">${escapeHtml(order.date)}</span>
                </td>
                <td class="kp_kitchen_admin_panel_table_cell">
                  <strong>${escapeHtml(order.customer)}</strong>
                </td>
                <td class="kp_kitchen_admin_panel_table_cell">
                  <strong>${escapeHtml(order.tiffin)}</strong>
                  <div class="inline-badge-list">${addonsStr}</div>
                </td>
                <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(order.area)}</strong></td>
                <td class="kp_kitchen_admin_panel_table_cell" style="width:250px;">
                  <select class="kp_kitchen_admin_panel_inline_select ${noDriver ? 'kp_kitchen_admin_panel_inline_select_warning' : ''}" data-order-driver="${order.id}" ${noDriver ? 'disabled' : ''}>
                    <option value="Unassigned" ${!selectedDriverValid ? 'selected' : ''}>${noDriver ? `No active driver for postcode ${escapeHtml(order.area)}` : 'Unassigned'}</option>
                    ${driverOptions}
                  </select>
                  <span class="kp_kitchen_admin_panel_assignment_hint">${noDriver ? 'Assign this postcode area to a driver first.' : `Postcode-matched drivers only`}</span>
                </td>
                <td class="kp_kitchen_admin_panel_table_cell"><strong>${formatCurrency(order.amount)}</strong></td>
                <td class="kp_kitchen_admin_panel_table_cell">
                  <select class="kp_kitchen_admin_panel_inline_select" data-order-status="${order.id}">
                    ${statuses.map(status => `<option ${status === order.status ? 'selected' : ''}>${status}</option>`).join('')}
                  </select>
                </td>
              </tr>`;
    }).join('') || '<tr><td colspan="7" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No orders found matching filters.</div></td></tr>';
  }

  // --- Render Invoices list view ---
  function renderInvoices() {
    if (!getElement('invoicesTableBody')) return;
    const search = getSearchQuery();
    const statusFilter = getElement('invoiceStatusFilter')?.value || 'all';
    const startDate = getElement('invoiceStartDateFilter')?.value || '';
    const endDate = getElement('invoiceEndDateFilter')?.value || '';

    const filtered = invoices.filter(inv => {
      // Status filter
      if (statusFilter !== 'all' && inv.status !== statusFilter) return false;

      // Date range filter
      if (startDate && inv.due_date < startDate) return false;
      if (endDate && inv.due_date > endDate) return false;

      return !search || 
             inv.id.toLowerCase().includes(search) || 
             (inv.customer && inv.customer.name.toLowerCase().includes(search)) || 
             (inv.order_id && inv.order_id.toLowerCase().includes(search));
    });

    getElement('invoicesTableBody').innerHTML = filtered.map(inv => {
      const custName = inv.customer ? inv.customer.name : 'Unknown';
      const isPaid = inv.status === 'Paid';
      const actionButton = isPaid 
        ? `<button class="kp_kitchen_admin_panel_small_button" data-print-invoice="${inv.id}">Print / PDF</button>`
        : `<button class="kp_kitchen_admin_panel_small_button" data-mark-paid="${inv.id}">Mark Paid</button> <button class="kp_kitchen_admin_panel_secondary_button" data-print-invoice="${inv.id}">View</button>`;

      return `<tr class="kp_kitchen_admin_panel_table_row">
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(inv.id)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(custName)}</td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(inv.order_id || 'N/A')}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${formatCurrency(inv.amount)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(inv.due_date)}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(inv.status)}">${escapeHtml(inv.status)}</span></td>
        <td class="kp_kitchen_admin_panel_table_cell">
          <div class="kp_kitchen_admin_panel_card_actions">
            ${actionButton}
          </div>
        </td>
      </tr>`;
    }).join('') || '<tr><td colspan="7" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No invoices found.</div></td></tr>';
  }

  // --- Render Payments view ---
  function renderPayments() {
    if (!getElement('paymentsTableBody')) return;
    const search = getSearchQuery();
    const filtered = payments.filter(payment => 
      !search || 
      payment.id.toLowerCase().includes(search) || 
      payment.customer.toLowerCase().includes(search) || 
      payment.plan.toLowerCase().includes(search)
    );

    getElement('paymentsTableBody').innerHTML = filtered.map(payment => 
      `<tr class="kp_kitchen_admin_panel_table_row">
        <td class="kp_kitchen_admin_panel_table_cell"><strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(payment.id)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(payment.customer)}</td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(payment.plan)}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${formatCurrency(payment.amount)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(payment.date)}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(payment.status)}">${escapeHtml(payment.status)}</span></td>
      </tr>`
    ).join('') || '<tr><td colspan="6" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No transactions found.</div></td></tr>';
    
    if (getElement('paymentTotal')) getElement('paymentTotal').textContent = formatCurrency(payments.filter(item => item.status === 'Successful').reduce((sum, item) => sum + Number(item.amount), 0));
    if (getElement('paymentSuccessful')) getElement('paymentSuccessful').textContent = payments.filter(item => item.status === 'Successful').length;
    if (getElement('paymentFailed')) getElement('paymentFailed').textContent = payments.filter(item => item.status === 'Failed').length;
  }

  // --- Render Coupons list view ---
  function renderCoupons() {
    if (!getElement('couponsTableBody')) return;
    const search = getSearchQuery();
    const filtered = coupons.filter(c => 
      !search || 
      c.code.toLowerCase().includes(search) || 
      c.type.toLowerCase().includes(search)
    );

    getElement('couponsTableBody').innerHTML = filtered.map(c => {
      const discountText = c.type === 'Percentage' ? `${Number(c.value).toFixed(0)}%` : `$${Number(c.value).toFixed(2)}`;
      return `<tr class="kp_kitchen_admin_panel_table_row">
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(c.code)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(c.type)}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${discountText}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(c.expiry_date)}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(c.status)}">${escapeHtml(c.status)}</span></td>
        <td class="kp_kitchen_admin_panel_table_cell">
          <div class="kp_kitchen_admin_panel_card_actions">
            <button class="kp_kitchen_admin_panel_small_button" data-edit-coupon="${c.id}">Edit</button>
            <button class="kp_kitchen_admin_panel_danger_button" data-delete-coupon="${c.id}">Delete</button>
          </div>
        </td>
      </tr>`;
    }).join('') || '<tr><td colspan="6" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No coupons found.</div></td></tr>';
  }

  // --- Render Administrators users view ---
  function renderUsers() {
    if (!getElement('usersTableBody')) return;
    const search = getSearchQuery();
    const filtered = users.filter(u => 
      !search || 
      u.name.toLowerCase().includes(search) || 
      u.email.toLowerCase().includes(search)
    );

    getElement('usersTableBody').innerHTML = filtered.map(u => 
      `<tr class="kp_kitchen_admin_panel_table_row">
        <td class="kp_kitchen_admin_panel_table_cell">#ADM${u.id}</td>
        <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(u.name)}</strong></td>
        <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(u.email)}</td>
        <td class="kp_kitchen_admin_panel_table_cell">${new Date(u.created_at).toLocaleDateString('en-AU')}</td>
        <td class="kp_kitchen_admin_panel_table_cell">
          <div class="kp_kitchen_admin_panel_card_actions">
            <button class="kp_kitchen_admin_panel_small_button" data-edit-user="${u.id}">Edit</button>
            <button class="kp_kitchen_admin_panel_danger_button" data-delete-user="${u.id}">Delete</button>
          </div>
        </td>
      </tr>`
    ).join('') || '<tr><td colspan="5" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No administrators found.</div></td></tr>';
  }

  // --- Render Notifications logs ---
  function renderNotifications() {
    if (!getElement('notificationsList')) return;
    getElement('notificationsList').innerHTML = notifications.length ? notifications.map(item => 
      `<article class="kp_kitchen_admin_panel_notification_item ${item.read ? '' : 'kp_kitchen_admin_panel_notification_item_unread'}">
        <div class="kp_kitchen_admin_panel_notification_icon">${item.title.includes('Payment') ? '$' : '🔔'}</div>
        <div class="kp_kitchen_admin_panel_notification_content">
          <div class="kp_kitchen_admin_panel_notification_heading">
            <strong class="kp_kitchen_admin_panel_notification_title">${escapeHtml(item.title)}</strong>
            <span class="kp_kitchen_admin_panel_notification_time">${escapeHtml(item.time)}</span>
          </div>
          <p class="kp_kitchen_admin_panel_notification_message">${escapeHtml(item.message)}</p>
        </div>
        <button class="kp_kitchen_admin_panel_notification_read_button" data-read-notification="${item.id}">${item.read ? 'Read' : 'Mark read'}</button>
      </article>`
    ).join('') : '<div class="kp_kitchen_admin_panel_empty_state">No notifications available.</div>';
  }

  // --- Modal Forms builder ---
  const modal = getElement('modal');
  const modalForm = getElement('modalForm');
  function closeModal() { modal.classList.remove('kp_kitchen_admin_panel_modal_visible'); modalForm.innerHTML = ''; }
  
  function openModal(title, fields, submitText, onSubmit, afterOpen) {
    getElement('modalTitle').textContent = title;
    modalForm.innerHTML = fields + `<div class="kp_kitchen_admin_panel_modal_actions"><button type="button" id="modalCancel" class="kp_kitchen_admin_panel_secondary_button">Cancel</button><button type="submit" class="kp_kitchen_admin_panel_primary_button">${submitText}</button></div>`;
    modal.classList.add('kp_kitchen_admin_panel_modal_visible');
    getElement('modalCancel').addEventListener('click', closeModal);
    modalForm.onsubmit = event => { event.preventDefault(); onSubmit(new FormData(modalForm)); };
    if (afterOpen) afterOpen();
  }

  if (modal) {
    getElement('modalClose').addEventListener('click', closeModal);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
  }

  // --- View Details Modals logic ---
  const detailsModal = getElement('detailsModal');
  const detailsModalContent = getElement('detailsModalContent');
  const closeDetailsModal = () => { detailsModal.classList.remove('kp_kitchen_admin_panel_modal_visible'); detailsModalContent.innerHTML = ''; };
  if (detailsModal) {
    getElement('detailsModalClose').addEventListener('click', closeDetailsModal);
    detailsModal.addEventListener('click', event => { if (event.target === detailsModal) closeDetailsModal(); });
  }

  function openDetailsModal(title, bodyHtml) {
    getElement('detailsModalTitle').textContent = title;
    detailsModalContent.innerHTML = bodyHtml;
    detailsModal.classList.add('kp_kitchen_admin_panel_modal_visible');
  }

  // Field configurations for forms
  const categoryFields = c => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Category Name</span>
      <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.name || '')}" required placeholder="Everyday Meals, Drinks, Desserts">
    </label>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Description</span>
      <textarea name="description" class="kp_kitchen_admin_panel_form_textarea" placeholder="Provide category descriptions">${escapeHtml(c?.description || '')}</textarea>
    </label>
  `;

  const itemFields = item => {
    const catOptions = categories.map(c => `<option value="${c.id}" ${c.id === item?.category_id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('');
    return `
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Item Image</span>
        <input id="itemImageInput" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
        <input id="itemImageData" name="image" type="hidden" value="${escapeHtml(item?.image || '')}">
        <div id="itemImagePreview" class="kp_kitchen_admin_panel_image_preview">${item?.image ? `<img src="${escapeHtml(item.image)}" alt="Preview">` : '<span>Image preview</span>'}</div>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Item Name</span>
        <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(item?.name || '')}" required placeholder="Garlic Bread, Butter Chicken">
      </label>
      <div class="kp_kitchen_admin_panel_form_grid">
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Category</span>
          <select name="category_id" class="kp_kitchen_admin_panel_form_select" required>
            <option value="">Select Category</option>
            ${catOptions}
          </select>
        </label>
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Price ($ AUD)</span>
          <input name="price" type="number" step="0.01" class="kp_kitchen_admin_panel_form_input" value="${item?.price || ''}" required placeholder="12.50">
        </label>
      </div>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Description</span>
        <textarea name="description" class="kp_kitchen_admin_panel_form_textarea" placeholder="Describe the menu item">${escapeHtml(item?.description || '')}</textarea>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Status</span>
        <select name="status" class="kp_kitchen_admin_panel_form_select">
          <option ${item?.status === 'Active' ? 'selected' : ''}>Active</option>
          <option ${item?.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
        </select>
      </label>
    `;
  };

  const tiffinFields = tiffin => {
    const basicItems = getTiffinBasicItems(tiffin);
    const addonIds = getTiffinAddonIds(tiffin);

    // Group active items by Category for the Add-ons checkboxes
    const renderCheckboxGroup = (itemsToRender) => {
      if (itemsToRender.length === 0) {
        return `<div style="opacity:0.6; font-size:0.8rem; padding: 4px 0; color: var(--text-secondary);">None</div>`;
      }
      
      // Group by Category name
      const grouped = {};
      categories.forEach(cat => {
        grouped[cat.name] = itemsToRender.filter(i => i.category_id === cat.id);
      });

      return Object.entries(grouped)
        .map(([catName, catItems]) => {
          if (catItems.length === 0) return '';
          const checkboxes = catItems.map(item => {
            const isChecked = addonIds.includes(item.id) ? 'checked' : '';
            return `
              <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-primary); cursor: pointer; margin-bottom: 6px;">
                <input type="checkbox" name="tiffin_addons" value="${item.id}" data-price="${item.price}" ${isChecked} style="width: auto; margin: 0;">
                <span>${escapeHtml(item.name)} <span style="font-size:0.75rem; font-weight:600; color: var(--text-secondary);">(+$${Number(item.price).toFixed(2)})</span></span>
              </label>
            `;
          }).join('');

          return `
            <div style="margin-bottom: 12px;">
              <strong style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--primary-color); margin-bottom: 4px; border-bottom: 1px solid var(--panel-border); padding-bottom: 2px;">${escapeHtml(catName)}</strong>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px;">
                ${checkboxes}
              </div>
            </div>
          `;
        }).filter(Boolean).join('');
    };

    const addOnHtml = renderCheckboxGroup(items.filter(item => item.status === 'Active'));

    const catOptions = categories.map(c => `<option value="${c.id}" ${c.id === tiffin?.category_id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('');
    
    return `
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Tiffin Plan Image</span>
        <input id="tiffinImageInput" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
        <input id="tiffinImageData" name="image" type="hidden" value="${escapeHtml(tiffin?.image || '')}">
        <div id="tiffinImagePreview" class="kp_kitchen_admin_panel_image_preview">${tiffin?.image ? `<img src="${escapeHtml(tiffin.image)}" alt="Preview">` : '<span>Image preview</span>'}</div>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Tiffin Plan Name</span>
        <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(tiffin?.name || '')}" required placeholder="Premium Feast Tiffin">
      </label>
      <div class="kp_kitchen_admin_panel_form_grid">
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Plan Category (optional)</span>
          <select name="category_id" class="kp_kitchen_admin_panel_form_select">
            <option value="">Select Category</option>
            ${catOptions}
          </select>
        </label>
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Base Price ($ AUD)</span>
          <input name="price" type="number" step="0.01" class="kp_kitchen_admin_panel_form_input" value="${tiffin?.price || ''}" required placeholder="19.90">
        </label>
      </div>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Prep Time (mins)</span>
        <input name="prepTime" type="number" class="kp_kitchen_admin_panel_form_input" value="${tiffin?.prep_time || 30}" required>
      </label>
      <div class="kp_kitchen_admin_panel_form_group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span class="kp_kitchen_admin_panel_form_label" style="margin-bottom: 0;">Included Items (Basic Menu)</span>
          <button type="button" id="addBasicMenuItemBtn" class="kp_kitchen_admin_panel_small_button" style="padding: 4px 10px; font-size: 0.75rem;">+ Add Item</button>
        </div>
        <div id="basicMenuItemsInputsContainer" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          ${(() => {
            const defaultPlaceholders = ["enter the subzi", "enter the rice", "enter the breads", "enter the dal"];
            const count = Math.max(4, basicItems.length);
            let html = '';
            for (let i = 0; i < count; i++) {
              const val = basicItems[i] || '';
              const ph = defaultPlaceholders[i] || "enter new item";
              html += `<input type="text" name="basic_menu_items[]" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(val)}" placeholder="${ph}" style="margin-bottom:0;">`;
            }
            return html;
          })()}
        </div>
      </div>
      <div class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label" style="margin-bottom: 6px; display: block;">Extra Add-Ons (Increases Plan Price)</span>
        <div class="tiffin-items-checkboxes-container" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 8px; padding: 12px 16px; max-height: 180px; overflow-y: auto;">
          ${addOnHtml}
        </div>
      </div>
      <div class="tiffin-modal-total-price-bar" style="background: var(--primary-color-light, rgba(255, 107, 107, 0.1)); border: 1px solid var(--primary-color); border-radius: 8px; padding: 12px 16px; margin: 16px 0; display: flex; justify-content: space-between; align-items: center;">
        <strong style="color: var(--text-primary); font-size: 0.9rem;">Total Plan Price (Base + Options):</strong>
        <strong id="tiffinModalTotalPrice" style="font-size: 1.25rem; color: var(--primary-color); font-weight:800;">$0.00</strong>
      </div>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Description</span>
        <textarea name="description" class="kp_kitchen_admin_panel_form_textarea" placeholder="Describe the plan">${escapeHtml(tiffin?.description || '')}</textarea>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Status</span>
        <select name="status" class="kp_kitchen_admin_panel_form_select">
          <option ${tiffin?.status === 'Active' ? 'selected' : ''}>Active</option>
          <option ${tiffin?.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
        </select>
      </label>
    `;
  };

  const driverFields = d => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Driver Full Name</span>
      <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.name || '')}" required placeholder="Jack Thompson">
    </label>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Phone</span>
        <input name="phone" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.phone || '')}" required placeholder="0412 345 678">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Email</span>
        <input name="email" type="email" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.email || '')}" placeholder="driver@email.com">
      </label>
    </div>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Address</span>
      <input name="address" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.address || '')}" placeholder="45 Elizabeth St, Melbourne VIC">
    </label>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">License Number</span>
        <input name="license_no" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.license_no || '')}" placeholder="VIC8891029">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">License Expiry</span>
        <input name="license_expiry" type="date" class="kp_kitchen_admin_panel_form_input" value="${d?.license_expiry || ''}">
      </label>
    </div>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Vehicle Reg Number</span>
        <input name="vehicle_reg_no" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.vehicle_reg_no || '')}" placeholder="1AB-2CD">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Assigned Postcode</span>
        <input name="assigned_zip" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.assigned_zip || '')}" placeholder="3000">
      </label>
    </div>
    
    <!-- License Front copy image -->
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">License Copy (Front side)</span>
      <input id="driverLicenseFrontInput" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
      <input id="driverLicenseFrontData" name="license_copy_front" type="hidden" value="${escapeHtml(d?.license_copy_front || '')}">
      <div id="driverLicenseFrontPreview" class="kp_kitchen_admin_panel_image_preview">${d?.license_copy_front ? `<img src="${escapeHtml(d.license_copy_front)}" alt="Front">` : '<span>Image preview</span>'}</div>
    </label>

    <!-- License Back copy image -->
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">License Copy (Back side)</span>
      <input id="driverLicenseBackInput" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
      <input id="driverLicenseBackData" name="license_copy_back" type="hidden" value="${escapeHtml(d?.license_copy_back || '')}">
      <div id="driverLicenseBackPreview" class="kp_kitchen_admin_panel_image_preview">${d?.license_copy_back ? `<img src="${escapeHtml(d.license_copy_back)}" alt="Back">` : '<span>Image preview</span>'}</div>
    </label>

    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Status</span>
      <select name="status" class="kp_kitchen_admin_panel_form_select">
        <option ${d?.status === 'Active' ? 'selected' : ''}>Active</option>
        <option ${d?.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
      </select>
    </label>
  `;

  const customerFields = c => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Customer Name</span>
      <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.name || '')}" required placeholder="William Morton">
    </label>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Phone</span>
        <input name="phone" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.phone || '')}" required placeholder="0412 345 678">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Email</span>
        <input name="email" type="email" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.email || '')}" required placeholder="william@email.com.au">
      </label>
    </div>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Postcode (Australia 4-digit)</span>
        <input name="pincode" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.pincode || '')}" required placeholder="3000">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Address</span>
        <input name="address" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.address || '')}" required placeholder="45 Elizabeth St, Melbourne VIC">
      </label>
    </div>
  `;

  const couponFields = c => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Coupon Code</span>
      <input name="code" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.code || '')}" required placeholder="WELCOME10">
    </label>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Type</span>
        <select name="type" class="kp_kitchen_admin_panel_form_select">
          <option ${c?.type === 'Percentage' ? 'selected' : ''}>Percentage</option>
          <option ${c?.type === 'Flat' ? 'selected' : ''}>Flat</option>
        </select>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Value</span>
        <input name="value" type="number" step="0.01" class="kp_kitchen_admin_panel_form_input" value="${c?.value || ''}" required placeholder="10.00">
      </label>
    </div>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Expiry Date</span>
      <input name="expiry_date" type="date" class="kp_kitchen_admin_panel_form_input" value="${c?.expiry_date || ''}" required>
    </label>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Status</span>
      <select name="status" class="kp_kitchen_admin_panel_form_select">
        <option ${c?.status === 'Active' ? 'selected' : ''}>Active</option>
        <option ${c?.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
      </select>
    </label>
  `;

  const userFields = u => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Admin Username</span>
      <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(u?.name || '')}" required placeholder="Kitchen Manager">
    </label>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Email</span>
      <input name="email" type="email" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(u?.email || '')}" required placeholder="manager@kpkitchen.com">
    </label>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Password ${u ? '(leave blank to keep current)' : ''}</span>
      <input name="password" type="password" class="kp_kitchen_admin_panel_form_input" ${u ? '' : 'required'} placeholder="password">
    </label>
  `;

  // File uploading preview listeners helper
  function setupImagePreview(inputId, hiddenId, previewId) {
    const input = getElement(inputId);
    if (!input) return;
    input.addEventListener('change', () => {
      const file = input.files[0]; if (!file) return;
      if (file.size > 1.5 * 1024 * 1024) { input.value = ''; showToast('Please select an image smaller than 1.5 MB.'); return; }
      const reader = new FileReader();
      reader.onload = () => { 
        getElement(hiddenId).value = reader.result; 
        getElement(previewId).innerHTML = `<img src="${reader.result}" alt="Preview">`; 
      };
      reader.readAsDataURL(file);
    });
  }

  function setupItemImagePreview() { setupImagePreview('itemImageInput', 'itemImageData', 'itemImagePreview'); }
  function setupTiffinImagePreview() { setupImagePreview('tiffinImageInput', 'tiffinImageData', 'tiffinImagePreview'); }
  
  function setupTiffinModalListeners(tiffin) {
    setupTiffinImagePreview();
    const form = getElement('modalForm');
    if (!form) return;

    // Dynamically append basic menu item inputs
    const addBtn = getElement('addBasicMenuItemBtn');
    const inputsContainer = getElement('basicMenuItemsInputsContainer');
    if (addBtn && inputsContainer) {
      addBtn.addEventListener('click', () => {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'basic_menu_items[]';
        input.className = 'kp_kitchen_admin_panel_form_input';
        input.placeholder = 'enter new item';
        input.style.marginBottom = '0';
        inputsContainer.appendChild(input);
      });
    }

    const basePriceInput = form.querySelector('input[name="price"]');
    const checkboxes = form.querySelectorAll('input[name="tiffin_addons"]');
    const totalDisplay = getElement('tiffinModalTotalPrice');
    const updateCalculatedTotal = () => {
      if (!totalDisplay) return;
      const basePrice = Number(basePriceInput?.value || 0);
      let selectedSum = 0;
      checkboxes.forEach(cb => {
        if (cb.checked) {
          selectedSum += Number(cb.dataset.price || 0);
        }
      });
      totalDisplay.textContent = `$${(basePrice + selectedSum).toFixed(2)}`;
    };
    if (basePriceInput) basePriceInput.addEventListener('input', updateCalculatedTotal);
    checkboxes.forEach(cb => cb.addEventListener('change', updateCalculatedTotal));
    updateCalculatedTotal();
  }
  
  function setupDriverImagePreviews() {
    setupImagePreview('driverLicenseFrontInput', 'driverLicenseFrontData', 'driverLicenseFrontPreview');
    setupImagePreview('driverLicenseBackInput', 'driverLicenseBackData', 'driverLicenseBackPreview');
  }

  // --- Add Category ---
  if (getElement('addCategoryButton')) {
    getElement('addCategoryButton').addEventListener('click', () => {
      openModal('Add Menu Category', categoryFields(), 'Add Category', async data => {
        try {
          const result = await apiRequest('api/categories', 'POST', {
            action: 'create',
            name: data.get('name').trim(),
            description: data.get('description').trim()
          });
          if (result.success) { closeModal(); showToast('Category created successfully.'); refreshData(); }
        } catch (e) { showToast('Failed to create category.'); }
      });
    });
  }

  // --- Add Menu Item ---
  if (getElement('addItemButton')) {
    getElement('addItemButton').addEventListener('click', () => {
      openModal('Add Menu Item', itemFields(), 'Add Item', async data => {
        try {
          const result = await apiRequest('api/items', 'POST', {
            action: 'create',
            name: data.get('name').trim(),
            price: Number(data.get('price')),
            category_id: Number(data.get('category_id')),
            description: data.get('description').trim(),
            status: data.get('status'),
            image: data.get('image')
          });
          if (result.success) { closeModal(); showToast('Menu item added successfully.'); refreshData(); }
        } catch (e) { showToast('Failed to add item.'); }
      }, setupItemImagePreview);
    });
  }

  // --- Add Tiffin ---
  if (getElement('addTiffinButton')) {
    getElement('addTiffinButton').addEventListener('click', () => {
      openModal('Add Complete Tiffin Plan', tiffinFields(), 'Add Tiffin', async data => {
        try {
          const basicMenuItems = Array.from(document.querySelectorAll('input[name="basic_menu_items[]"]'))
            .map(input => input.value.trim())
            .filter(Boolean);
          const addonIds = Array.from(document.querySelectorAll('input[name="tiffin_addons"]:checked'))
            .map(cb => Number(cb.value));

          const result = await apiRequest('api/tiffins', 'POST', {
            action: 'create',
            name: data.get('name').trim(),
            category_id: data.get('category_id') ? Number(data.get('category_id')) : null,
            price: Number(data.get('price')),
            items: {
              basic: basicMenuItems,
              addons: addonIds
            },
            description: data.get('description').trim(),
            prepTime: Number(data.get('prepTime')),
            status: data.get('status'),
            image: data.get('image')
          });
          if (result.success) { closeModal(); showToast('Tiffin added successfully.'); refreshData(); }
        } catch (e) { showToast('Failed to add tiffin.'); }
      }, () => setupTiffinModalListeners(null));
    });
  }

  // --- Add Driver ---
  if (getElement('addDriverButton')) {
    getElement('addDriverButton').addEventListener('click', () => {
      openModal('Add Driver & Assign Postcode', driverFields(), 'Add Driver', async data => {
        try {
          const result = await apiRequest('api/drivers', 'POST', {
            action: 'create',
            name: data.get('name').trim(),
            phone: data.get('phone').trim(),
            email: data.get('email').trim(),
            address: data.get('address').trim(),
            license_no: data.get('license_no').trim(),
            license_expiry: data.get('license_expiry'),
            vehicle_reg_no: data.get('vehicle_reg_no').trim(),
            assigned_zip: data.get('assigned_zip').trim(),
            status: data.get('status'),
            license_copy_front: data.get('license_copy_front'),
            license_copy_back: data.get('license_copy_back')
          });
          if (result.success) { closeModal(); showToast('Driver added and postcode assigned.'); refreshData(); }
        } catch (e) { showToast('Failed to add driver.'); }
      }, setupDriverImagePreviews);
    });
  }

  // --- Add Customer ---
  if (getElement('addCustomerButton')) {
    getElement('addCustomerButton').addEventListener('click', () => {
      openModal('Add Customer Profile', customerFields(), 'Add Customer', async data => {
        try {
          const result = await apiRequest('api/customers', 'POST', {
            action: 'create',
            name: data.get('name').trim(),
            phone: data.get('phone').trim(),
            email: data.get('email').trim(),
            pincode: data.get('pincode').trim(),
            address: data.get('address').trim()
          });
          if (result.success) { closeModal(); showToast('Customer created successfully.'); refreshData(); }
        } catch (e) { showToast('Failed to create customer.'); }
      });
    });
  }

  // --- Add Coupon ---
  if (getElement('addCouponButton')) {
    getElement('addCouponButton').addEventListener('click', () => {
      openModal('Add Promo Coupon', couponFields(), 'Add Coupon', async data => {
        try {
          const result = await apiRequest('api/coupons', 'POST', {
            action: 'create',
            code: data.get('code').trim(),
            type: data.get('type'),
            value: Number(data.get('value')),
            expiry_date: data.get('expiry_date'),
            status: data.get('status')
          });
          if (result.success) { closeModal(); showToast('Coupon created successfully.'); refreshData(); }
        } catch (e) { showToast('Failed to create coupon.'); }
      });
    });
  }

  // --- Add Administrator user ---
  if (getElement('addUserButton')) {
    getElement('addUserButton').addEventListener('click', () => {
      openModal('Add Administrator User', userFields(), 'Add Administrator', async data => {
        try {
          const result = await apiRequest('api/users', 'POST', {
            action: 'create',
            name: data.get('name').trim(),
            email: data.get('email').trim(),
            password: data.get('password')
          });
          if (result.success) { closeModal(); showToast('Administrator created.'); refreshData(); }
        } catch (e) { showToast('Failed to create admin.'); }
      });
    });
  }

  // --- Click Event Delegations (Edit, Delete, Print views, Customer details popup) ---
  document.addEventListener('click', async event => {
    const target = event.target;
    
    // Category edit/delete
    if (target.dataset.editCategory) {
      const c = categories.find(item => item.id === Number(target.dataset.editCategory));
      if (!c) return;
      openModal('Edit Category Details', categoryFields(c), 'Save Changes', async data => {
        try {
          const result = await apiRequest('api/categories', 'POST', {
            action: 'update',
            id: c.id,
            name: data.get('name').trim(),
            description: data.get('description').trim()
          });
          if (result.success) { closeModal(); showToast('Category updated.'); refreshData(); }
        } catch (e) { showToast('Failed to update category.'); }
      });
    }
    if (target.dataset.deleteCategory && confirm('Delete this category? Associated items will be deleted.')) {
      try {
        const result = await apiRequest('api/categories', 'POST', { action: 'delete', id: Number(target.dataset.deleteCategory) });
        if (result.success) { showToast('Category deleted.'); refreshData(); }
      } catch (e) { showToast('Failed to delete category.'); }
    }

    // Category view items (add-ons)
    if (target.dataset.viewCategoryItems) {
      const c = categories.find(item => item.id === Number(target.dataset.viewCategoryItems));
      if (!c) return;
      
      const catItems = items.filter(item => item.category_id === c.id);
      
      let bodyHtml = '';
      if (catItems.length === 0) {
        bodyHtml = `<div style="text-align:center; padding: 24px; opacity:0.6; color: var(--text-secondary);">No items found in this category.</div>`;
      } else {
        const rows = catItems.map(item => {
          const image = item.image ? `<img src="${escapeHtml(item.image)}" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 1px solid var(--panel-border);">` : '<span style="font-size: 1.5rem;">🍲</span>';
          const statusBadge = `<span class="${statusClass(item.status)}" style="padding: 2px 8px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; display: inline-block;">${escapeHtml(item.status)}</span>`;
          return `
            <tr class="kp_kitchen_admin_panel_table_row" style="border-bottom: 1px solid var(--panel-border);">
              <td class="kp_kitchen_admin_panel_table_cell" style="padding: 12px 16px; vertical-align: middle;">${image}</td>
              <td class="kp_kitchen_admin_panel_table_cell" style="padding: 12px 16px; vertical-align: middle;"><strong>${escapeHtml(item.name)}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell" style="padding: 12px 16px; vertical-align: middle; color: var(--primary-color); font-weight: 700; font-family: var(--font-title);">$${Number(item.price).toFixed(2)}</td>
              <td class="kp_kitchen_admin_panel_table_cell" style="padding: 12px 16px; vertical-align: middle;">${statusBadge}</td>
            </tr>
          `;
        }).join('');

        bodyHtml = `
          <div class="kp_kitchen_admin_panel_table_wrap" style="margin-top: 10px; max-height: 400px; overflow-y: auto;">
            <table class="kp_kitchen_admin_panel_table" style="width: 100%; border-collapse: collapse;">
              <thead class="kp_kitchen_admin_panel_table_head" style="position: sticky; top: 0; background: var(--panel-bg); z-index: 1;">
                <tr class="kp_kitchen_admin_panel_table_row" style="border-bottom: 2px solid var(--panel-border);">
                  <th class="kp_kitchen_admin_panel_table_heading" style="padding: 12px 16px; text-align: left; font-weight:700;">Image</th>
                  <th class="kp_kitchen_admin_panel_table_heading" style="padding: 12px 16px; text-align: left; font-weight:700;">Name</th>
                  <th class="kp_kitchen_admin_panel_table_heading" style="padding: 12px 16px; text-align: left; font-weight:700;">Price</th>
                  <th class="kp_kitchen_admin_panel_table_heading" style="padding: 12px 16px; text-align: left; font-weight:700;">Status</th>
                </tr>
              </thead>
              <tbody class="kp_kitchen_admin_panel_table_body">
                ${rows}
              </tbody>
            </table>
          </div>
        `;
      }
      
      openDetailsModal(`Items in Category: ${c.name}`, bodyHtml);
    }

    // Menu Item edit/delete
    if (target.dataset.editItem) {
      const item = items.find(i => i.id === Number(target.dataset.editItem));
      if (!item) return;
      openModal('Edit Menu Item Details', itemFields(item), 'Save Changes', async data => {
        try {
          const result = await apiRequest('api/items', 'POST', {
            action: 'update',
            id: item.id,
            name: data.get('name').trim(),
            price: Number(data.get('price')),
            category_id: Number(data.get('category_id')),
            description: data.get('description').trim(),
            status: data.get('status'),
            image: data.get('image')
          });
          if (result.success) { closeModal(); showToast('Menu item updated.'); refreshData(); }
        } catch (e) { showToast('Failed to update item.'); }
      }, setupItemImagePreview);
    }
    if (target.dataset.deleteItem && confirm('Delete this menu item?')) {
      try {
        const result = await apiRequest('api/items', 'POST', { action: 'delete', id: Number(target.dataset.deleteItem) });
        if (result.success) { showToast('Menu item deleted.'); refreshData(); }
      } catch (e) { showToast('Failed to delete item.'); }
    }

    // Tiffin edit/delete
    if (target.dataset.editTiffin) {
      const tiffin = tiffins.find(t => t.id === Number(target.dataset.editTiffin));
      if (!tiffin) return;
      openModal('Edit Tiffin Plan Details', tiffinFields(tiffin), 'Save Changes', async data => {
        try {
          const basicMenuItems = Array.from(document.querySelectorAll('input[name="basic_menu_items[]"]'))
            .map(input => input.value.trim())
            .filter(Boolean);
          const addonIds = Array.from(document.querySelectorAll('input[name="tiffin_addons"]:checked'))
            .map(cb => Number(cb.value));

          const result = await apiRequest('api/tiffins', 'POST', {
            action: 'update',
            id: tiffin.id,
            category_id: data.get('category_id') ? Number(data.get('category_id')) : null,
            name: data.get('name').trim(),
            price: Number(data.get('price')),
            items: {
              basic: basicMenuItems,
              addons: addonIds
            },
            description: data.get('description').trim(),
            prepTime: Number(data.get('prepTime')),
            status: data.get('status'),
            image: data.get('image')
          });
          if (result.success) { closeModal(); showToast('Tiffin plan updated.'); refreshData(); }
        } catch (e) { showToast('Failed to update tiffin plan.'); }
      }, () => setupTiffinModalListeners(tiffin));
    }
    if (target.dataset.deleteTiffin && confirm('Delete this tiffin plan?')) {
      try {
        const result = await apiRequest('api/tiffins', 'POST', { action: 'delete', id: Number(target.dataset.deleteTiffin) });
        if (result.success) { showToast('Tiffin plan deleted.'); refreshData(); }
      } catch (e) { showToast('Failed to delete tiffin plan.'); }
    }

    // Driver edit/delete/view trips
    if (target.dataset.editDriver) {
      const d = drivers.find(item => item.id === Number(target.dataset.editDriver));
      if (!d) return;
      openModal('Edit Driver Profile & Assigned Postcode', driverFields(d), 'Save Changes', async data => {
        try {
          const result = await apiRequest('api/drivers', 'POST', {
            action: 'update',
            id: d.id,
            name: data.get('name').trim(),
            phone: data.get('phone').trim(),
            email: data.get('email').trim(),
            address: data.get('address').trim(),
            license_no: data.get('license_no').trim(),
            license_expiry: data.get('license_expiry'),
            vehicle_reg_no: data.get('vehicle_reg_no').trim(),
            assigned_zip: data.get('assigned_zip').trim(),
            status: data.get('status'),
            license_copy_front: data.get('license_copy_front'),
            license_copy_back: data.get('license_copy_back')
          });
          if (result.success) { closeModal(); showToast('Driver profile updated.'); refreshData(); }
        } catch (e) { showToast('Failed to update driver profile.'); }
      }, setupDriverImagePreviews);
    }
    if (target.dataset.deleteDriver && confirm('Delete this driver? Assigned deliveries will become unassigned.')) {
      try {
        const result = await apiRequest('api/drivers', 'POST', { action: 'delete', id: Number(target.dataset.deleteDriver) });
        if (result.success) { showToast('Driver profile deleted.'); refreshData(); }
      } catch (e) { showToast('Failed to delete driver.'); }
    }
    if (target.dataset.viewTrips) {
      const d = drivers.find(item => item.id === Number(target.dataset.viewTrips));
      if (!d) return;
      
      const driverTrips = trips.filter(t => t.driver_id === d.id);
      const rows = driverTrips.map(t => `
        <tr class="kp_kitchen_admin_panel_table_row">
          <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(t.order_id)}</strong></td>
          <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(t.status)}">${escapeHtml(t.status)}</span></td>
          <td class="kp_kitchen_admin_panel_table_cell">${t.started_at ? new Date(t.started_at).toLocaleTimeString('en-AU') : 'Not started'}</td>
          <td class="kp_kitchen_admin_panel_table_cell">${t.completed_at ? new Date(t.completed_at).toLocaleTimeString('en-AU') : 'In progress'}</td>
        </tr>
      `).join('');

      const tableHtml = `
        <div class="kp_kitchen_admin_panel_table_wrap">
          <table class="kp_kitchen_admin_panel_table">
            <thead class="kp_kitchen_admin_panel_table_head">
              <tr class="kp_kitchen_admin_panel_table_row">
                <th class="kp_kitchen_admin_panel_table_heading">Order ID</th>
                <th class="kp_kitchen_admin_panel_table_heading">Trip Status</th>
                <th class="kp_kitchen_admin_panel_table_heading">Started</th>
                <th class="kp_kitchen_admin_panel_table_heading">Completed</th>
              </tr>
            </thead>
            <tbody class="kp_kitchen_admin_panel_table_body">${rows || '<tr><td colspan="4" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No trips recorded.</div></td></tr>'}</tbody>
          </table>
        </div>
      `;
      openDetailsModal(`Trips Log: ${d.name}`, tableHtml);
    }

    // Customer card view details popup
    if (target.dataset.viewCustomer) {
      const c = customers.find(item => item.id === Number(target.dataset.viewCustomer));
      if (!c) return;

      const secondaryAddresses = (c.addresses || []).filter(a => !a.is_default);
      const addressesList = secondaryAddresses.map(a => `<div class="customer-detail-row"><span>Unit/St:</span><span>${escapeHtml(a.address_line)} (Postcode ${escapeHtml(a.pincode)})</span></div>`).join('');

      const lastOrder = c.orders && c.orders.length ? c.orders[0] : null;
      const totalSpend = c.orders ? c.orders.filter(o => o.status === 'Delivered').reduce((sum, o) => sum + Number(o.amount), 0) : 0;

      const ordersRows = (c.orders || []).slice(0, 5).map(o => `
        <tr class="kp_kitchen_admin_panel_table_row">
          <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(o.id)}</strong></td>
          <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(o.tiffin)}</td>
          <td class="kp_kitchen_admin_panel_table_cell"><strong>${formatCurrency(o.amount)}</strong></td>
          <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(o.status)}">${escapeHtml(o.status)}</span></td>
        </tr>
      `).join('');

      const invoicesRows = (c.invoices || []).slice(0, 5).map(inv => `
        <tr class="kp_kitchen_admin_panel_table_row">
          <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(inv.id)}</strong></td>
          <td class="kp_kitchen_admin_panel_table_cell"><strong>${formatCurrency(inv.amount)}</strong></td>
          <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(inv.status)}">${escapeHtml(inv.status)}</span></td>
        </tr>
      `).join('');

      const paymentRows = (c.payments || []).slice(0, 5).map(pay => `
        <tr class="kp_kitchen_admin_panel_table_row">
          <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(pay.id)}</strong></td>
          <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(pay.plan)}</td>
          <td class="kp_kitchen_admin_panel_table_cell"><strong>${formatCurrency(pay.amount)}</strong></td>
          <td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(pay.status)}">${escapeHtml(pay.status)}</span></td>
        </tr>
      `).join('');

      const customerCardHtml = `
        <div class="customer-card-wrap">
          <div class="customer-detail-section">
            <div class="customer-detail-block">
              <div class="customer-detail-block-title">Customer Details</div>
              <div class="customer-detail-row"><span>Name:</span><span>${escapeHtml(c.name)}</span></div>
              <div class="customer-detail-row"><span>Phone:</span><span>${escapeHtml(c.phone)}</span></div>
              <div class="customer-detail-row"><span>Email:</span><span>${escapeHtml(c.email)}</span></div>
              <div class="customer-detail-row"><span>Postcode:</span><span>${escapeHtml(c.pincode)}</span></div>
              <div class="customer-detail-row"><span>Default Address:</span><span>${escapeHtml(c.address)}</span></div>
            </div>
            
            <div class="customer-detail-block">
              <div class="customer-detail-block-title">Account History</div>
              <div class="customer-detail-row"><span>Last Order ID:</span><span>${lastOrder ? lastOrder.id : 'None'}</span></div>
              <div class="customer-detail-row"><span>Last Order Date:</span><span>${lastOrder ? lastOrder.date : 'N/A'}</span></div>
              <div class="customer-detail-row"><span>Total Orders:</span><span>${c.orders ? c.orders.length : 0}</span></div>
              <div class="customer-detail-row"><span>Total Spent:</span><span><strong>${formatCurrency(totalSpend)}</strong></span></div>
              <div class="customer-detail-block-title" style="margin-top:12px;">Secondary Addresses</div>
              ${addressesList || '<div class="customer-detail-row" style="opacity:0.6;">No secondary addresses.</div>'}
            </div>
          </div>

          <div class="customer-detail-block" style="width:100%;">
            <div class="customer-detail-block-title">Recent Orders (Max 5)</div>
            <div class="kp_kitchen_admin_panel_table_wrap">
              <table class="kp_kitchen_admin_panel_table">
                <thead class="kp_kitchen_admin_panel_table_head">
                  <tr class="kp_kitchen_admin_panel_table_row">
                    <th class="kp_kitchen_admin_panel_table_heading">Order ID</th>
                    <th class="kp_kitchen_admin_panel_table_heading">Tiffin Plan</th>
                    <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
                    <th class="kp_kitchen_admin_panel_table_heading">Status</th>
                  </tr>
                </thead>
                <tbody class="kp_kitchen_admin_panel_table_body">${ordersRows || '<tr><td colspan="4" class="kp_kitchen_admin_panel_table_cell" style="text-align:center;">No orders placed.</td></tr>'}</tbody>
              </table>
            </div>
          </div>

          <div class="customer-detail-section">
            <div class="customer-detail-block">
              <div class="customer-detail-block-title">Recent Invoices</div>
              <div class="kp_kitchen_admin_panel_table_wrap">
                <table class="kp_kitchen_admin_panel_table">
                  <thead class="kp_kitchen_admin_panel_table_head">
                    <tr class="kp_kitchen_admin_panel_table_row">
                      <th class="kp_kitchen_admin_panel_table_heading">Invoice ID</th>
                      <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
                      <th class="kp_kitchen_admin_panel_table_heading">Status</th>
                    </tr>
                  </thead>
                  <tbody class="kp_kitchen_admin_panel_table_body">${invoicesRows || '<tr><td colspan="3" class="kp_kitchen_admin_panel_table_cell" style="text-align:center;">No invoices.</td></tr>'}</tbody>
                </table>
              </div>
            </div>

            <div class="customer-detail-block">
              <div class="customer-detail-block-title">Deduction / Payment History</div>
              <div class="kp_kitchen_admin_panel_table_wrap">
                <table class="kp_kitchen_admin_panel_table">
                  <thead class="kp_kitchen_admin_panel_table_head">
                    <tr class="kp_kitchen_admin_panel_table_row">
                      <th class="kp_kitchen_admin_panel_table_heading">TXN ID</th>
                      <th class="kp_kitchen_admin_panel_table_heading">Plan</th>
                      <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
                      <th class="kp_kitchen_admin_panel_table_heading">Status</th>
                    </tr>
                  </thead>
                  <tbody class="kp_kitchen_admin_panel_table_body">${paymentRows || '<tr><td colspan="4" class="kp_kitchen_admin_panel_table_cell" style="text-align:center;">No payments.</td></tr>'}</tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      `;
      openDetailsModal(`Customer Card: ${c.name}`, customerCardHtml);
    }
    if (target.dataset.editCustomer) {
      const c = customers.find(item => item.id === Number(target.dataset.editCustomer));
      if (!c) return;
      openModal('Edit Customer Profile', customerFields(c), 'Save Changes', async data => {
        try {
          const result = await apiRequest('api/customers', 'POST', {
            action: 'update',
            id: c.id,
            name: data.get('name').trim(),
            phone: data.get('phone').trim(),
            email: data.get('email').trim(),
            pincode: data.get('pincode').trim(),
            address: data.get('address').trim()
          });
          if (result.success) { closeModal(); showToast('Customer profile updated.'); refreshData(); }
        } catch (e) { showToast('Failed to update customer.'); }
      });
    }
    if (target.dataset.deleteCustomer && confirm('Delete this customer? All invoices and orders will be deleted.')) {
      try {
        const result = await apiRequest('api/customers', 'POST', { action: 'delete', id: Number(target.dataset.deleteCustomer) });
        if (result.success) { showToast('Customer deleted.'); refreshData(); }
      } catch (e) { showToast('Failed to delete customer.'); }
    }

    // Coupon edit/delete
    if (target.dataset.editCoupon) {
      const c = coupons.find(item => item.id === Number(target.dataset.editCoupon));
      if (!c) return;
      openModal('Edit Promo Coupon', couponFields(c), 'Save Changes', async data => {
        try {
          const result = await apiRequest('api/coupons', 'POST', {
            action: 'update',
            id: c.id,
            code: data.get('code').trim(),
            type: data.get('type'),
            value: Number(data.get('value')),
            expiry_date: data.get('expiry_date'),
            status: data.get('status')
          });
          if (result.success) { closeModal(); showToast('Coupon details updated.'); refreshData(); }
        } catch (e) { showToast('Failed to update coupon.'); }
      });
    }
    if (target.dataset.deleteCoupon && confirm('Delete this promo code?')) {
      try {
        const result = await apiRequest('api/coupons', 'POST', { action: 'delete', id: Number(target.dataset.deleteCoupon) });
        if (result.success) { showToast('Coupon deleted.'); refreshData(); }
      } catch (e) { showToast('Failed to delete coupon.'); }
    }

    // User edit/delete
    if (target.dataset.editUser) {
      const u = users.find(item => item.id === Number(target.dataset.editUser));
      if (!u) return;
      openModal('Edit Administrator User', userFields(u), 'Save Changes', async data => {
        try {
          const result = await apiRequest('api/users', 'POST', {
            action: 'update',
            id: u.id,
            name: data.get('name').trim(),
            email: data.get('email').trim(),
            password: data.get('password')
          });
          if (result.success) { closeModal(); showToast('Administrator details updated.'); refreshData(); }
        } catch (e) { showToast('Failed to update administrator.'); }
      });
    }
    if (target.dataset.deleteUser && confirm('Delete this administrator?')) {
      try {
        const result = await apiRequest('api/users', 'POST', { action: 'delete', id: Number(target.dataset.deleteUser) });
        if (result.success) { showToast('Administrator deleted.'); refreshData(); }
      } catch (e) { showToast('Failed to delete administrator: ' + e.message); }
    }

    // Invoice Mark Paid / View invoice sheet
    if (target.dataset.markPaid) {
      try {
        const result = await apiRequest('api/invoices', 'POST', { action: 'update_status', id: target.dataset.markPaid, status: 'Paid' });
        if (result.success) { showToast('Invoice marked as Paid.'); refreshData(); }
      } catch (e) { showToast('Failed to mark invoice as Paid.'); }
    }
    if (target.dataset.printInvoice) {
      const inv = invoices.find(i => i.id === target.dataset.printInvoice);
      if (!inv) return;
      const cust = inv.customer || { name: 'Demo Customer', email: 'demo@email.com', phone: '0412 345 678', address: 'Unknown' };

      const invoiceSheetHtml = `
        <div style="font-family: var(--font-family); color: #1E293B; padding: 20px; line-height:1.6;">
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #E2E8F0; padding-bottom: 20px; margin-bottom: 20px;">
            <div>
              <h2 style="font-family: var(--font-title); font-size:1.8rem; font-weight:800; color:#FF6B6B; margin:0;">KP KITCHEN</h2>
              <span style="font-size:0.75rem; color:#64748B; text-transform:uppercase; letter-spacing:1px;">Fulfillment Services Australia</span>
            </div>
            <div style="text-align:right;">
              <h3 style="font-family:var(--font-title); font-size:1.2rem; font-weight:700; margin:0;">TAX INVOICE</h3>
              <span style="font-size:0.85rem; font-weight:600; color:#64748B;">Invoice: ${escapeHtml(inv.id)}</span>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom: 30px; font-size:0.85rem;">
            <div>
              <strong style="display:block; text-transform:uppercase; font-size:0.75rem; color:#64748B; margin-bottom:4px;">Billed To:</strong>
              <strong style="font-size:1rem; display:block;">${escapeHtml(cust.name)}</strong>
              <span>Phone: ${escapeHtml(cust.phone)}</span><br>
              <span>Email: ${escapeHtml(cust.email)}</span><br>
              <span>Address: ${escapeHtml(cust.address)}</span>
            </div>
            <div style="text-align:right;">
              <strong style="display:block; text-transform:uppercase; font-size:0.75rem; color:#64748B; margin-bottom:4px;">Billing Details:</strong>
              <span>Date Created: ${new Date(inv.created_at).toLocaleDateString('en-AU')}</span><br>
              <span>Payment Due: ${new Date(inv.due_date).toLocaleDateString('en-AU')}</span><br>
              <span>Status: <strong style="color:${inv.status === 'Paid' ? '#2ECC71' : '#F1C40F'}; text-transform:uppercase;">${escapeHtml(inv.status)}</strong></span>
            </div>
          </div>

          <table style="width:100%; border-collapse:collapse; margin-bottom:30px; font-size:0.85rem;">
            <thead>
              <tr style="background:#F1F5F9; border-bottom: 2px solid #CBD5E1;">
                <th style="padding:10px; text-align:left; font-weight:600;">Description</th>
                <th style="padding:10px; text-align:right; font-weight:600;">Rate</th>
                <th style="padding:10px; text-align:right; font-weight:600;">Qty</th>
                <th style="padding:10px; text-align:right; font-weight:600;">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid #E2E8F0;">
                <td style="padding:10px;">
                  <strong>KP Kitchen Meal Service</strong><br>
                  <span style="font-size:0.75rem; color:#64748B;">Order Ref: ${escapeHtml(inv.order_id || 'N/A')}</span>
                </td>
                <td style="padding:10px; text-align:right;">${formatCurrency(inv.amount)}</td>
                <td style="padding:10px; text-align:right;">1</td>
                <td style="padding:10px; text-align:right;"><strong>${formatCurrency(inv.amount)}</strong></td>
              </tr>
            </tbody>
          </table>

          <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div style="font-size:0.75rem; color:#64748B; width:50%;">
              <strong>Payment Terms:</strong><br>
              All payments are processed via automatic recurring credit card deductions. If a deduction fails, please log in to the Customer App to verify details.
            </div>
            <div style="text-align:right; width:40%; font-size:0.85rem;">
              <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>Subtotal:</span><span>${formatCurrency(inv.amount)}</span></div>
              <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>GST (10%):</span><span>Included</span></div>
              <div style="display:flex; justify-content:space-between; border-top:2px solid #E2E8F0; padding-top:6px; margin-top:6px; font-weight:700; font-size:1.05rem; color:#FF6B6B;"><span>Total AUD:</span><span>${formatCurrency(inv.amount)}</span></div>
            </div>
          </div>

          <div style="text-align:center; border-top: 1px solid #E2E8F0; padding-top:20px; margin-top:40px; font-size:0.75rem; color:#94A3B8;">
            Thank you for dining with KP Kitchen Australia!
          </div>
        </div>
      `;
      openDetailsModal(`Invoice: ${inv.id}`, invoiceSheetHtml);
    }

    // Notifications mark read
    if (target.dataset.readNotification) {
      try {
        const result = await apiRequest('api/notifications/read', 'POST', {
          action: 'mark_read',
          id: Number(target.dataset.readNotification)
        });
        if (result.success) refreshData();
      } catch (e) { showToast('Failed to update notification.'); }
    }
  });

  // --- Select Change Delegations (Order Driver / Status) ---
  document.addEventListener('change', async event => {
    const target = event.target;
    
    // Update Order Status
    if (target.dataset.orderStatus) {
      try {
        const result = await apiRequest('api/orders/update', 'POST', { id: target.dataset.orderStatus, status: target.value });
        if (result.success) { showToast('Order status updated.'); refreshData(); }
      } catch (e) { showToast('Failed to update order status.'); }
    }
    
    // Assign Order Driver
    if (target.dataset.orderDriver) {
      try {
        const result = await apiRequest('api/orders/update', 'POST', { id: target.dataset.orderDriver, driver: target.value });
        if (result.success) { showToast(`Order assigned according to postcode.`); refreshData(); }
        else { showToast(result.message || 'Driver assignment rejected.'); refreshData(); }
      } catch (e) { showToast('Failed to assign driver.'); }
    }
  });

  // --- Filter Event Listeners ---
  const populateAreaFilter = () => { 
    const select = getElement('orderAreaFilter'); 
    if (!select) return; 
    const selected = select.value; 
    const areas = [...new Set(orders.map(order => order.area))].sort(); 
    select.innerHTML = '<option value="all">All areas</option>' + areas.map(area => `<option value="${escapeHtml(area)}">Postcode ${escapeHtml(area)}</option>`).join(''); 
    select.value = areas.includes(selected) ? selected : 'all'; 
  };
  
  if (getElement('orderStatusFilter')) getElement('orderStatusFilter').addEventListener('change', () => renderOrders(getElement('orderStatusFilter').value));
  if (getElement('orderAreaFilter')) getElement('orderAreaFilter').addEventListener('change', () => renderOrders(getElement('orderStatusFilter')?.value || 'all'));
  if (getElement('orderStartDateFilter')) getElement('orderStartDateFilter').addEventListener('change', () => renderOrders(getElement('orderStatusFilter')?.value || 'all'));
  if (getElement('orderEndDateFilter')) getElement('orderEndDateFilter').addEventListener('change', () => renderOrders(getElement('orderStatusFilter')?.value || 'all'));

  if (getElement('invoiceStatusFilter')) getElement('invoiceStatusFilter').addEventListener('change', () => renderInvoices());
  if (getElement('invoiceStartDateFilter')) getElement('invoiceStartDateFilter').addEventListener('change', () => renderInvoices());
  if (getElement('invoiceEndDateFilter')) getElement('invoiceEndDateFilter').addEventListener('change', () => renderInvoices());
  
  if (getElement('markAllReadButton')) {
    getElement('markAllReadButton').addEventListener('click', async () => {
      try {
        const result = await apiRequest('api/notifications/read', 'POST', { action: 'mark_all_read' });
        if (result.success) { showToast('All notifications marked as read.'); refreshData(); }
      } catch (e) { showToast('Failed to mark all notifications as read.'); }
    });
  }
  
  if (getElement('runDeductionButton')) {
    getElement('runDeductionButton').addEventListener('click', async () => {
      try {
        const result = await apiRequest('api/payments/deduct', 'POST');
        if (result.success) { showToast('Automatic payment deduction simulated successfully.'); refreshData(); }
      } catch (e) { showToast('Failed to run deduction simulator.'); }
    });
  }

  // Global search enter handler (searches across pages and redirects)
  if (getElement('globalSearch')) {
    getElement('globalSearch').addEventListener('keydown', event => { 
      if (event.key !== 'Enter') return; 
      const term = event.target.value.trim().toLowerCase(); 
      if (!term) return; 

      if (orders.some(item => Object.values(item).some(value => String(value).toLowerCase().includes(term)))) {
        window.location.href = `./orders?search=${encodeURIComponent(term)}`; 
      } else if (drivers.some(item => Object.values(item).some(value => String(value).toLowerCase().includes(term)))) {
        window.location.href = `./drivers?search=${encodeURIComponent(term)}`; 
      } else if (tiffins.some(item => Object.values(item).some(value => String(value).toLowerCase().includes(term)))) {
        window.location.href = `./tiffins?search=${encodeURIComponent(term)}`; 
      } else if (customers.some(item => Object.values(item).some(value => String(value).toLowerCase().includes(term)))) {
        window.location.href = `./customers?search=${encodeURIComponent(term)}`;
      } else {
        showToast('No matching records found.'); 
      }
    });
  }
  
  // Logout Trigger
  if (getElement('logoutButton')) {
    getElement('logoutButton').addEventListener('click', async () => { 
      try {
        const result = await apiRequest('api/auth/logout', 'POST');
        if (result.success) { window.location.href = './login'; }
      } catch (e) { showToast('Failed to log out.'); }
    });
  }

  function renderAll() { 
    populateAreaFilter(); 
    renderDashboard(); 
    renderCategories();
    renderItems();
    renderTiffins(); 
    renderDrivers(); 
    renderCustomers();
    renderOrders(getElement('orderStatusFilter')?.value || 'all'); 
    renderPayments(); 
    renderCoupons();
    renderUsers();
    renderInvoices();
    renderNotifications(); 
    updateBadges(); 
  }

  // Collapsible Sidebar Accordion Logic
  function initCollapsibleSidebar() {
    const activeSubItem = document.querySelector('.kp_kitchen_admin_panel_nav_sub_item_active');
    if (activeSubItem) {
      const parentGroup = activeSubItem.closest('.kp_kitchen_admin_panel_nav_group');
      if (parentGroup) {
        parentGroup.classList.add('kp_kitchen_admin_panel_nav_group_active');
      }
    }

    document.querySelectorAll('.kp_kitchen_admin_panel_nav_group_header').forEach(header => {
      header.addEventListener('click', (e) => {
        e.preventDefault();
        const parent = header.closest('.kp_kitchen_admin_panel_nav_group');
        const isActive = parent.classList.contains('kp_kitchen_admin_panel_nav_group_active');
        
        document.querySelectorAll('.kp_kitchen_admin_panel_nav_group').forEach(g => {
          g.classList.remove('kp_kitchen_admin_panel_nav_group_active');
        });
        
        if (!isActive) {
          parent.classList.add('kp_kitchen_admin_panel_nav_group_active');
        }
      });
    });
  }
  
  // Initial page load data fetch
  if (window.location.pathname.indexOf('/login') === -1 && window.location.pathname.indexOf('/register') === -1) {
    refreshData();
    initCollapsibleSidebar();
  }
})();
