(function () {
  const getElement = id => document.getElementById(id);
  
  // App State Data (used for modal dropdown values)
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
    if (Array.isArray(tiffin.items)) return [];
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

  async function loadStateData() {
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
      
      updateBadges();
      renderCharts();
    } catch (e) {
      console.error("Error loading state data from API:", e);
    }
  }

  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  const formatCurrency = value => `$${Number(value).toFixed(2)}`;

  // Populate search input from URL on load
  const initSearchQuery = () => {
    const params = new URLSearchParams(window.location.search);
    const searchVal = params.get('search') || '';
    if (searchVal && getElement('globalSearch')) {
      getElement('globalSearch').value = searchVal;
    }
  };

  // Color themes
  const applyTheme = theme => {
    document.documentElement.setAttribute('data-kp-theme', theme);
    localStorage.setItem('kpKitchenTheme', theme);
    const button = getElement('themeToggle');
    if (button) {
      button.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
    }
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

  // Draw Dashboard charts
  async function renderCharts() {
    if (!getElement('ordersChartCanvas') || !getElement('itemsChartCanvas')) return;
    
    try {
      const response = await apiRequest('api/dashboard-charts');
      const isDark = document.documentElement.getAttribute('data-kp-theme') === 'dark';
      const textColor = isDark ? '#94A3B8' : '#64748B';
      const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

      if (ordersChartInstance) ordersChartInstance.destroy();
      if (itemsChartInstance) itemsChartInstance.destroy();

      // Orders Line Chart
      const ordersCtx = getElement('ordersChartCanvas').getContext('2d');
      ordersChartInstance = new Chart(ordersCtx, {
        type: 'line',
        data: {
          labels: response.ordersChart.labels,
          datasets: [{
            label: 'Daily Orders',
            data: response.ordersChart.data,
            borderColor: '#FF6B6B',
            backgroundColor: 'rgba(255,107,107,0.1)',
            fill: true,
            tension: 0.4
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

      // Top Items Bar Chart
      const itemsCtx = getElement('itemsChartCanvas').getContext('2d');
      itemsChartInstance = new Chart(itemsCtx, {
        type: 'bar',
        data: {
          labels: response.itemsChart.labels,
          datasets: [{
            label: 'Orders',
            data: response.itemsChart.data,
            backgroundColor: ['#FF6B6B', '#4ECDC4', '#FFE66D', '#1A535C', '#A8DADC'],
            borderRadius: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: gridColor }, ticks: { color: textColor } },
            y: { grid: { color: gridColor }, ticks: { color: textColor } }
          }
        }
      });
    } catch (e) {
      console.error("Error drawing charts:", e);
    }
  }

  // --- Modal Dialog controllers ---
  const modal = getElement('modal');
  const modalForm = getElement('modalForm');
  
  function closeModal() { 
    modal.classList.remove('kp_kitchen_admin_panel_modal_visible'); 
    modalForm.innerHTML = ''; 
  }
  
  function openModal(title, fields, submitText, actionUrl, afterOpen) {
    getElement('modalTitle').textContent = title;
    modalForm.action = actionUrl;
    modalForm.method = 'POST';
    modalForm.enctype = 'multipart/form-data';
    modalForm.innerHTML = `
      <input type="hidden" name="_token" value="${getCsrfToken()}">
      ${fields}
      <div class="kp_kitchen_admin_panel_modal_actions">
        <button type="button" id="modalCancel" class="kp_kitchen_admin_panel_secondary_button">Cancel</button>
        <button type="submit" class="kp_kitchen_admin_panel_primary_button">${submitText}</button>
      </div>
    `;
    modal.classList.add('kp_kitchen_admin_panel_modal_visible');
    getElement('modalCancel').addEventListener('click', closeModal);
    modalForm.onsubmit = null; // native HTML submission
    if (afterOpen) afterOpen();
  }

  // --- Details Modal Dialog controllers ---
  const detailsModal = getElement('detailsModal');
  const detailsModalContent = getElement('detailsModalContent');

  function closeDetailsModal() { 
    detailsModal.classList.remove('kp_kitchen_admin_panel_modal_visible'); 
    detailsModalContent.innerHTML = ''; 
  }
  
  function openDetailsModal(title, html) {
    getElement('detailsModalTitle').textContent = title;
    detailsModalContent.innerHTML = html;
    detailsModal.classList.add('kp_kitchen_admin_panel_modal_visible');
    getElement('detailsModalClose').addEventListener('click', closeDetailsModal);
  }

  function statusClass(status) {
    const s = String(status).toLowerCase();
    if (s === 'pending') return 'kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_pending';
    if (s === 'cooking' || s === 'processing') return 'kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_cooking';
    if (s === 'delivered') return 'kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_delivered';
    if (s === 'dispatched' || s === 'out_for_delivery') return 'kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_dispatched';
    if (s === 'cancelled' || s === 'failed') return 'kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_failed';
    return 'kp_kitchen_admin_panel_status';
  }

  // --- Modal Fields builders ---
  const categoryFields = c => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Category Name</span>
      <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.name || '')}" required placeholder="e.g. Rice, Sides, Breads">
    </label>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Description</span>
      <textarea name="description" class="kp_kitchen_admin_panel_form_textarea" placeholder="Brief description of the category">${escapeHtml(c?.description || '')}</textarea>
    </label>
  `;

  const itemFields = item => {
    const catOptions = categories.map(c => `<option value="${c.id}" ${c.id === item?.category_id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('');
    return `
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Item Image</span>
        <input id="itemImageInput" name="image_file" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
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

    const renderCheckboxGroup = (itemsToRender) => {
      if (itemsToRender.length === 0) {
        return `<div style="opacity:0.6; font-size:0.8rem; padding: 4px 0; color: var(--text-secondary);">None</div>`;
      }
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
                <input type="checkbox" name="tiffin_addons[]" value="${item.id}" data-price="${item.price}" ${isChecked} style="width: auto; margin: 0;">
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
        <input id="tiffinImageInput" name="image_file" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
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
        <input name="prep_time" type="number" class="kp_kitchen_admin_panel_form_input" value="${tiffin?.prep_time || 30}" required>
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
        <span class="kp_kitchen_admin_panel_form_label">Vehicle Registration No.</span>
        <input name="vehicle_reg_no" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.vehicle_reg_no || '')}" placeholder="1AB-2CD">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Assigned Delivery Postcode</span>
        <input name="assigned_zip" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(d?.assigned_zip || '')}" placeholder="3000">
      </label>
    </div>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Password ${d ? '(leave blank to keep current)' : ''}</span>
        <input name="password" type="password" class="kp_kitchen_admin_panel_form_input" placeholder="********" ${d ? '' : 'required'}>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Status</span>
        <select name="status" class="kp_kitchen_admin_panel_form_select">
          <option ${d?.status === 'Active' ? 'selected' : ''}>Active</option>
          <option ${d?.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
        </select>
      </label>
    </div>
    
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">License Copy (Front side)</span>
      <input id="driverLicenseFrontInput" name="license_copy_front_file" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
      <input id="driverLicenseFrontData" name="license_copy_front" type="hidden" value="${escapeHtml(d?.license_copy_front || '')}">
      <div id="driverLicenseFrontPreview" class="kp_kitchen_admin_panel_image_preview">${d?.license_copy_front ? `<img src="${escapeHtml(d.license_copy_front)}" alt="Front">` : '<span>Image preview</span>'}</div>
    </label>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">License Copy (Back side)</span>
      <input id="driverLicenseBackInput" name="license_copy_back_file" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
      <input id="driverLicenseBackData" name="license_copy_back" type="hidden" value="${escapeHtml(d?.license_copy_back || '')}">
      <div id="driverLicenseBackPreview" class="kp_kitchen_admin_panel_image_preview">${d?.license_copy_back ? `<img src="${escapeHtml(d.license_copy_back)}" alt="Back">` : '<span>Image preview</span>'}</div>
    </label>
  `;

  const customerFields = c => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Customer Name</span>
      <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.name || '')}" required placeholder="Sarah Collins">
    </label>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Phone</span>
        <input name="phone" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.phone || '')}" required placeholder="0412 888 999">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Email</span>
        <input name="email" type="email" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.email || '')}" required placeholder="sarah@email.com">
      </label>
    </div>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Default Delivery Postcode</span>
        <input name="pincode" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.pincode || '')}" required placeholder="3000">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Password ${c ? '(leave blank to keep current)' : ''}</span>
        <input name="password" type="password" class="kp_kitchen_admin_panel_form_input" placeholder="********" ${c ? '' : 'required'}>
      </label>
    </div>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Default Address</span>
      <input name="address" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.address || '')}" placeholder="12 Spring St, Melbourne VIC">
    </label>
  `;

  const couponFields = c => `
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Promo Code</span>
      <input name="code" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.code || '')}" required placeholder="e.g. WELCOME10, WINTER20">
    </label>
    <div class="kp_kitchen_admin_panel_form_grid">
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Discount Type</span>
        <select name="type" class="kp_kitchen_admin_panel_form_select" required>
          <option ${c?.type === 'Percentage' ? 'selected' : ''}>Percentage</option>
          <option ${c?.type === 'Fixed' ? 'selected' : ''}>Fixed</option>
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

  const invoiceFields = inv => {
    const custOptions = customers.map(c => `<option value="${c.id}" ${c.id === inv?.customer_id ? 'selected' : ''}>${escapeHtml(c.name)} (#CUST${c.id})</option>`).join('');
    return `
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Select Customer</span>
        <select name="customer_id" class="kp_kitchen_admin_panel_form_select" required>
          <option value="">Select Customer</option>
          ${custOptions}
        </select>
      </label>
      <div class="kp_kitchen_admin_panel_form_grid">
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Invoice Amount</span>
          <input name="amount" type="number" step="0.01" class="kp_kitchen_admin_panel_form_input" value="${inv?.amount || ''}" required placeholder="55.00">
        </label>
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Due Date</span>
          <input name="due_date" type="date" class="kp_kitchen_admin_panel_form_input" value="${inv?.due_date || ''}" required>
        </label>
      </div>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Status</span>
        <select name="status" class="kp_kitchen_admin_panel_form_select">
          <option ${inv?.status === 'Paid' ? 'selected' : ''}>Paid</option>
          <option ${inv?.status === 'Unpaid' ? 'selected' : ''}>Unpaid</option>
          <option ${inv?.status === 'Pending' ? 'selected' : ''}>Pending</option>
        </select>
      </label>
    `;
  };

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

  // --- Dynamic Form Preview & Total price Calculation Listeners ---
  function setupImagePreview(inputId, hiddenId, previewId) {
    const input = getElement(inputId);
    if (!input) return;
    input.addEventListener('change', () => {
      const file = input.files[0]; if (!file) return;
      if (file.size > 2 * 1024 * 1024) { input.value = ''; showToast('Please select an image smaller than 2 MB.'); return; }
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
  
  function setupTiffinFormListeners() {
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
    const checkboxes = form.querySelectorAll('input[name="tiffin_addons[]"]');
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
  
  function setupDriverFormListeners() {
    setupImagePreview('driverLicenseFrontInput', 'driverLicenseFrontData', 'driverLicenseFrontPreview');
    setupImagePreview('driverLicenseBackInput', 'driverLicenseBackData', 'driverLicenseBackPreview');
  }

  // --- Add Button Trigger Bindings ---
  if (getElement('addCategoryButton')) {
    getElement('addCategoryButton').addEventListener('click', () => {
      openModal('Add Menu Category', categoryFields(), 'Add Category', getBaseUrl() + '/categories/save');
    });
  }
  if (getElement('addItemButton')) {
    getElement('addItemButton').addEventListener('click', () => {
      openModal('Add Menu Item', itemFields(), 'Create Item', getBaseUrl() + '/items/save', setupItemImagePreview);
    });
  }
  if (getElement('addTiffinButton')) {
    getElement('addTiffinButton').addEventListener('click', () => {
      openModal('Add Tiffin Plan', tiffinFields(), 'Create Plan', getBaseUrl() + '/tiffins/save', setupTiffinFormListeners);
    });
  }
  if (getElement('addDriverButton')) {
    getElement('addDriverButton').addEventListener('click', () => {
      openModal('Add Driver', driverFields(), 'Register Driver', getBaseUrl() + '/drivers/save', setupDriverFormListeners);
    });
  }
  if (getElement('addCustomerButton')) {
    getElement('addCustomerButton').addEventListener('click', () => {
      openModal('Add Customer', customerFields(), 'Create Account', getBaseUrl() + '/customers/save');
    });
  }
  if (getElement('addCouponButton')) {
    getElement('addCouponButton').addEventListener('click', () => {
      openModal('Add Coupon', couponFields(), 'Create Coupon', getBaseUrl() + '/coupons/save');
    });
  }
  if (getElement('addUserButton')) {
    getElement('addUserButton').addEventListener('click', () => {
      openModal('Add Administrator', userFields(), 'Create Admin', getBaseUrl() + '/users/save');
    });
  }

  // --- Edit Button Event Delegation Bindings ---
  document.addEventListener('click', event => {
    // Edit Category
    const categoryBtn = event.target.closest('.edit-category-btn');
    if (categoryBtn) {
      const c = {
        id: categoryBtn.dataset.id,
        name: categoryBtn.dataset.name,
        description: categoryBtn.dataset.description
      };
      openModal('Edit Category Details', categoryFields(c) + `<input type="hidden" name="id" value="${c.id}">`, 'Save Changes', getBaseUrl() + '/categories/save');
      return;
    }

    // Edit Menu Item
    const itemBtn = event.target.closest('.edit-item-btn');
    if (itemBtn) {
      const item = {
        id: itemBtn.dataset.id,
        name: itemBtn.dataset.name,
        price: itemBtn.dataset.price,
        category_id: Number(itemBtn.dataset.category_id),
        description: itemBtn.dataset.description,
        status: itemBtn.dataset.status,
        image: itemBtn.dataset.image
      };
      openModal('Edit Menu Item', itemFields(item) + `<input type="hidden" name="id" value="${item.id}">`, 'Save Changes', getBaseUrl() + '/items/save', setupItemImagePreview);
      return;
    }

    // Edit Tiffin Plan
    const tiffinBtn = event.target.closest('.edit-tiffin-btn');
    if (tiffinBtn) {
      const tiffin = {
        id: tiffinBtn.dataset.id,
        name: tiffinBtn.dataset.name,
        price: tiffinBtn.dataset.price,
        category_id: tiffinBtn.dataset.category_id ? Number(tiffinBtn.dataset.category_id) : null,
        prep_time: Number(tiffinBtn.dataset.prep_time),
        status: tiffinBtn.dataset.status,
        description: tiffinBtn.dataset.description,
        items: JSON.parse(tiffinBtn.dataset.items || '[]')
      };
      openModal('Edit Tiffin Plan', tiffinFields(tiffin) + `<input type="hidden" name="id" value="${tiffin.id}">`, 'Save Changes', getBaseUrl() + '/tiffins/save', setupTiffinFormListeners);
      return;
    }

    // Edit Driver
    const driverBtn = event.target.closest('.edit-driver-btn');
    if (driverBtn) {
      const d = {
        id: driverBtn.dataset.id,
        name: driverBtn.dataset.name,
        phone: driverBtn.dataset.phone,
        email: driverBtn.dataset.email,
        address: driverBtn.dataset.address,
        license_no: driverBtn.dataset.license_no,
        license_expiry: driverBtn.dataset.license_expiry,
        vehicle_reg_no: driverBtn.dataset.vehicle_reg_no,
        assigned_zip: driverBtn.dataset.assigned_zip,
        status: driverBtn.dataset.status,
        license_copy_front: driverBtn.dataset.license_copy_front,
        license_copy_back: driverBtn.dataset.license_copy_back
      };
      openModal('Edit Driver Profile', driverFields(d) + `<input type="hidden" name="id" value="${d.id}">`, 'Save Changes', getBaseUrl() + '/drivers/save', setupDriverFormListeners);
      return;
    }

    // Edit Customer
    const customerBtn = event.target.closest('.edit-customer-btn');
    if (customerBtn) {
      const cust = {
        id: customerBtn.dataset.id,
        name: customerBtn.dataset.name,
        phone: customerBtn.dataset.phone,
        email: customerBtn.dataset.email,
        pincode: customerBtn.dataset.pincode,
        address: customerBtn.dataset.address
      };
      openModal('Edit Customer Profile', customerFields(cust) + `<input type="hidden" name="id" value="${cust.id}">`, 'Save Changes', getBaseUrl() + '/customers/save');
      return;
    }

    // Edit Coupon
    const couponBtn = event.target.closest('.edit-coupon-btn');
    if (couponBtn) {
      const coupon = {
        id: couponBtn.dataset.id,
        code: couponBtn.dataset.code,
        type: couponBtn.dataset.type,
        value: couponBtn.dataset.value,
        expiry_date: couponBtn.dataset.expiry_date,
        status: couponBtn.dataset.status
      };
      openModal('Edit Coupon Details', couponFields(coupon) + `<input type="hidden" name="id" value="${coupon.id}">`, 'Save Changes', getBaseUrl() + '/coupons/save');
      return;
    }

    // Edit Invoice
    const invoiceBtn = event.target.closest('.edit-invoice-btn');
    if (invoiceBtn) {
      const inv = {
        id: invoiceBtn.dataset.id,
        customer_id: Number(invoiceBtn.dataset.customer_id),
        amount: invoiceBtn.dataset.amount,
        due_date: invoiceBtn.dataset.due_date,
        status: invoiceBtn.dataset.status
      };
      openModal('Edit Invoice Details', invoiceFields(inv) + `<input type="hidden" name="id" value="${inv.id}">`, 'Save Changes', getBaseUrl() + '/invoices/save');
      return;
    }

    // Edit Administrator User
    const userBtn = event.target.closest('.edit-user-btn');
    if (userBtn) {
      const u = {
        id: userBtn.dataset.id,
        name: userBtn.dataset.name,
        email: userBtn.dataset.email
      };
      openModal('Edit Admin Credentials', userFields(u) + `<input type="hidden" name="id" value="${u.id}">`, 'Save Changes', getBaseUrl() + '/users/save');
      return;
    }

    // View Items in Category (Read-only Modal)
    const viewItemsBtn = event.target.closest('.view-items-btn');
    if (viewItemsBtn) {
      const name = viewItemsBtn.dataset.name;
      const categoryItems = JSON.parse(viewItemsBtn.dataset.items || '[]');
      
      const fields = `
        <div style="font-size:0.9rem; max-height: 350px; overflow-y: auto;">
          <h4 style="margin: 0 0 12px 0; color: var(--primary-color);">Items under "${escapeHtml(name)}"</h4>
          ${categoryItems.length === 0 ? '<p style="opacity: 0.6;">No items found in this category.</p>' : categoryItems.map(item => `
            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--panel-border);">
              <strong>${escapeHtml(item.name)}</strong>
              <span>$${Number(item.price).toFixed(2)}</span>
            </div>
          `).join('')}
        </div>
      `;
      
      openModal('Category Items', fields, 'Close', '#');
      modalForm.action = '#';
      modalForm.onsubmit = e => {
        e.preventDefault();
        closeModal();
      };
      return;
    }

    // View Customer Details Modal
    const viewCustomerBtn = event.target.closest('.view-customer-details-btn');
    if (viewCustomerBtn) {
      const name = viewCustomerBtn.dataset.name;
      const phone = viewCustomerBtn.dataset.phone;
      const email = viewCustomerBtn.dataset.email;
      const pincode = viewCustomerBtn.dataset.pincode;
      const address = viewCustomerBtn.dataset.address;
      const totalOrders = viewCustomerBtn.dataset.total_orders;
      const totalSpend = Number(viewCustomerBtn.dataset.total_spend || 0);
      const recentOrders = JSON.parse(viewCustomerBtn.dataset.orders || '[]');

      const html = `
        <div style="font-family: var(--font-family); color: var(--text-primary);">
          <!-- Top Overview Grid -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
            <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px;">
              <h4 style="margin: 0 0 12px 0; color: var(--primary-color); font-size: 0.95rem;">Contact & Profile</h4>
              <p style="margin: 6px 0; font-size: 0.85rem;">👤 Name: <strong>${escapeHtml(name)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem;">📞 Phone: <strong>${escapeHtml(phone)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem;">✉️ Email: <strong>${escapeHtml(email)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem;">📮 Postcode: <strong>${escapeHtml(pincode)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem; line-height: 1.4;">📍 Address: ${escapeHtml(address || 'No address registered')}</p>
            </div>
            <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: center; gap: 12px;">
              <div>
                <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Total Orders</span>
                <strong style="font-size: 1.8rem; color: var(--primary-color); font-family: var(--font-title);">${totalOrders}</strong>
              </div>
              <div>
                <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Total Money Spent</span>
                <strong style="font-size: 1.8rem; color: #2ECC71; font-family: var(--font-title);">$${totalSpend.toFixed(2)}</strong>
              </div>
            </div>
          </div>
          
          <!-- Recent Orders Table -->
          <h4 style="margin: 0 0 12px 0; color: var(--primary-color); font-size: 0.95rem;">Recent Orders (Last 5)</h4>
          <div class="kp_kitchen_admin_panel_table_wrap" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; overflow-x: auto;">
            <table class="kp_kitchen_admin_panel_table" style="margin: 0;">
              <thead class="kp_kitchen_admin_panel_table_head">
                <tr class="kp_kitchen_admin_panel_table_row">
                  <th class="kp_kitchen_admin_panel_table_heading">Order ID</th>
                  <th class="kp_kitchen_admin_panel_table_heading">Tiffin Plan</th>
                  <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
                  <th class="kp_kitchen_admin_panel_table_heading">Status</th>
                </tr>
              </thead>
              <tbody class="kp_kitchen_admin_panel_table_body">
                ${recentOrders.length === 0 ? `
                  <tr class="kp_kitchen_admin_panel_table_row">
                    <td colspan="4" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6;">No orders found for this customer.</td>
                  </tr>
                ` : recentOrders.map(order => `
                  <tr class="kp_kitchen_admin_panel_table_row">
                    <td class="kp_kitchen_admin_panel_table_cell">
                      <strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong>
                      <span class="kp_kitchen_admin_panel_table_secondary">${escapeHtml(order.date)}</span>
                    </td>
                    <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.tiffin)}</td>
                    <td class="kp_kitchen_admin_panel_table_cell"><strong>$${Number(order.amount).toFixed(2)}</strong></td>
                    <td class="kp_kitchen_admin_panel_table_cell">
                      <span class="${statusClass(order.status)}">${escapeHtml(order.status)}</span>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
          
          <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
            <button type="button" class="kp_kitchen_admin_panel_secondary_button" id="closeDetailsModalBtn">Close</button>
          </div>
        </div>
      `;

      openDetailsModal(`Customer Profile`, html);
      getElement('closeDetailsModalBtn').addEventListener('click', closeDetailsModal);
      return;
    }

    // View Driver Details Modal
    const viewDriverBtn = event.target.closest('.view-driver-details-btn');
    if (viewDriverBtn) {
      const name = viewDriverBtn.dataset.name;
      const phone = viewDriverBtn.dataset.phone;
      const email = viewDriverBtn.dataset.email;
      const address = viewDriverBtn.dataset.address;
      const licenseNo = viewDriverBtn.dataset.license_no;
      const licenseExpiry = viewDriverBtn.dataset.license_expiry;
      const vehicleReg = viewDriverBtn.dataset.vehicle_reg_no;
      const assignedZip = viewDriverBtn.dataset.assigned_zip;
      const status = viewDriverBtn.dataset.status;
      const licenseFront = viewDriverBtn.dataset.license_copy_front;
      const licenseBack = viewDriverBtn.dataset.license_copy_back;
      const activeShipments = viewDriverBtn.dataset.active_shipments;
      const totalOrders = viewDriverBtn.dataset.total_orders;
      const recentOrders = JSON.parse(viewDriverBtn.dataset.orders || '[]');

      const frontImgHtml = licenseFront 
        ? `<img src="${getBaseUrl()}/${licenseFront}" style="width:100%; height:120px; object-fit:cover; border-radius:8px;" alt="Front">`
        : `<div style="height:120px; display:flex; align-items:center; justify-content:center; background:var(--bg-color); border:2px dashed var(--panel-border); border-radius:8px; opacity:0.6;">No Document</div>`;

      const backImgHtml = licenseBack 
        ? `<img src="${getBaseUrl()}/${licenseBack}" style="width:100%; height:120px; object-fit:cover; border-radius:8px;" alt="Back">`
        : `<div style="height:120px; display:flex; align-items:center; justify-content:center; background:var(--bg-color); border:2px dashed var(--panel-border); border-radius:8px; opacity:0.6;">No Document</div>`;

      const html = `
        <div style="font-family: var(--font-family); color: var(--text-primary);">
          <!-- Top Grid (Contact, Documents, Performance) -->
          <div style="display: grid; grid-template-columns: 1.2fr 1.2fr 1fr; gap: 20px; margin-bottom: 24px;">
            
            <!-- Contact info -->
            <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px;">
              <h4 style="margin: 0 0 12px 0; color: var(--primary-color); font-size: 0.95rem;">Contact & Profile</h4>
              <p style="margin: 6px 0; font-size: 0.85rem;">👤 Name: <strong>${escapeHtml(name)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem;">📞 Phone: <strong>${escapeHtml(phone)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem;">✉️ Email: <strong>${escapeHtml(email)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem;">📮 Zip: <strong>${escapeHtml(assignedZip)}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem; line-height: 1.4;">📍 Address: ${escapeHtml(address || 'No address registered')}</p>
            </div>
            
            <!-- Vehicle & Documents -->
            <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px;">
              <h4 style="margin: 0 0 12px 0; color: var(--primary-color); font-size: 0.95rem;">Documentation</h4>
              <p style="margin: 6px 0; font-size: 0.85rem;">🚗 Vehicle Reg: <strong>${escapeHtml(vehicleReg || 'N/A')}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem;">🛡️ License: <strong>${escapeHtml(licenseNo || 'N/A')}</strong></p>
              <p style="margin: 6px 0; font-size: 0.85rem; margin-bottom: 12px;">📅 Expiry: <strong>${escapeHtml(licenseExpiry || 'N/A')}</strong></p>
              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <div>
                  <span style="font-size:0.7rem; color:var(--text-secondary); display:block; text-align:center; margin-bottom:4px;">License Front</span>
                  ${frontImgHtml}
                </div>
                <div>
                  <span style="font-size:0.7rem; color:var(--text-secondary); display:block; text-align:center; margin-bottom:4px;">License Back</span>
                  ${backImgHtml}
                </div>
              </div>
            </div>
            
            <!-- Stats -->
            <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: center; gap: 16px;">
              <div>
                <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Active Shipments</span>
                <strong style="font-size: 1.8rem; color: #3498DB; font-family: var(--font-title);">${activeShipments}</strong>
              </div>
              <div>
                <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Total Assigned Orders</span>
                <strong style="font-size: 1.8rem; color: var(--primary-color); font-family: var(--font-title);">${totalOrders}</strong>
              </div>
              <div>
                <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Status</span>
                <span style="display:inline-block; font-size: 0.75rem; font-weight: 700; padding: 4px 8px; border-radius: 6px; ${status === 'Active' ? 'background-color:rgba(46,204,113,0.1); color:#2ECC71;' : 'background-color:rgba(231,76,60,0.1); color:#E74C3C;'}">${status}</span>
              </div>
            </div>
          </div>
          
          <!-- Recent Orders Table -->
          <h4 style="margin: 0 0 12px 0; color: var(--primary-color); font-size: 0.95rem;">Recent Deliveries (Last 5)</h4>
          <div class="kp_kitchen_admin_panel_table_wrap" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; overflow-x: auto;">
            <table class="kp_kitchen_admin_panel_table" style="margin: 0;">
              <thead class="kp_kitchen_admin_panel_table_head">
                <tr class="kp_kitchen_admin_panel_table_row">
                  <th class="kp_kitchen_admin_panel_table_heading">Order ID</th>
                  <th class="kp_kitchen_admin_panel_table_heading">Customer</th>
                  <th class="kp_kitchen_admin_panel_table_heading">Tiffin Plan</th>
                  <th class="kp_kitchen_admin_panel_table_heading">Status</th>
                </tr>
              </thead>
              <tbody class="kp_kitchen_admin_panel_table_body">
                ${recentOrders.length === 0 ? `
                  <tr class="kp_kitchen_admin_panel_table_row">
                    <td colspan="4" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6;">No orders assigned to this driver.</td>
                  </tr>
                ` : recentOrders.map(order => `
                  <tr class="kp_kitchen_admin_panel_table_row">
                    <td class="kp_kitchen_admin_panel_table_cell">
                      <strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong>
                      <span class="kp_kitchen_admin_panel_table_secondary">${escapeHtml(order.date)}</span>
                    </td>
                    <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.customer)}</td>
                    <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.tiffin)}</td>
                    <td class="kp_kitchen_admin_panel_table_cell">
                      <span class="${statusClass(order.status)}">${escapeHtml(order.status)}</span>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
          
          <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
            <button type="button" class="kp_kitchen_admin_panel_secondary_button" id="closeDriverDetailsModalBtn">Close</button>
          </div>
        </div>
      `;

      openDetailsModal(`Driver Profile`, html);
      getElement('closeDriverDetailsModalBtn').addEventListener('click', closeDetailsModal);
      return;
    }

    // Modal Cancel/Overlay clicks
    if (event.target.id === 'modalClose' || event.target.id === 'modal') {
      closeModal();
    }
    if (event.target.id === 'detailsModalClose' || event.target.id === 'detailsModal') {
      closeDetailsModal();
    }
  });

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

  // --- Header Search Bar Listener ---
  const globalSearch = getElement('globalSearch');
  if (globalSearch) {
    globalSearch.addEventListener('keypress', event => {
      if (event.key === 'Enter') {
        const query = globalSearch.value.trim();
        const url = new URL(window.location.href);
        if (query) {
          url.searchParams.set('search', query);
        } else {
          url.searchParams.delete('search');
        }
        window.location.href = url.toString();
      }
    });
  }

  // --- Initialise ---
  initSearchQuery();
  loadStateData();
  initCollapsibleSidebar();
})();
