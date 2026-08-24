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
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Collected Tiffin Photo</span>
        <input name="collected_photo" type="file" class="kp_kitchen_admin_panel_form_input" accept="image/*">
        ${inv?.collected_photo ? `
          <div style="margin-top: 10px;">
            <span style="font-size: 0.8rem; color: var(--text-secondary); display: block; margin-bottom: 5px;">Current Photo:</span>
            <a href="${inv.collected_photo}" target="_blank">
              <img src="${inv.collected_photo}" style="max-width: 120px; border-radius: 8px; border: 1px solid var(--panel-border);">
            </a>
          </div>
        ` : ''}
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

    // Edit Driver -> Inline transition to Grid-by-Grid Form
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
        status: driverBtn.dataset.status
      };
      
      const listSec = getElement('driverListSection');
      const editSec = getElement('driverEditSection');
      
      if (listSec && editSec) {
        listSec.style.display = 'none';
        editSec.style.display = 'block';
        
        // Populate inputs
        getElement('editDriverId').value = d.id;
        getElement('editDriverName').value = d.name;
        getElement('editDriverPhone').value = d.phone;
        getElement('editDriverEmail').value = d.email === 'null' || !d.email ? '' : d.email;
        getElement('editDriverVehicle').value = d.vehicle_reg_no === 'null' || !d.vehicle_reg_no ? '' : d.vehicle_reg_no;
        getElement('editDriverPostcode').value = d.assigned_zip === 'null' || !d.assigned_zip ? '' : d.assigned_zip;
        getElement('editDriverLicense').value = d.license_no === 'null' || !d.license_no ? '' : d.license_no;
        getElement('editDriverLicenseExpiry').value = d.license_expiry === 'null' || !d.license_expiry ? '' : d.license_expiry;
        getElement('editDriverAddress').value = d.address === 'null' || !d.address ? '' : d.address;
        getElement('editDriverStatus').value = d.status || 'Active';
        getElement('editDriverPassword').value = '';
        
        // Bind back/cancel actions
        const backToList = () => {
          editSec.style.display = 'none';
          listSec.style.display = 'block';
        };
        
        const backBtn = getElement('backToDriverListBtn');
        if (backBtn) backBtn.onclick = backToList;
        
        const cancelBtn = getElement('cancelDriverEditBtn');
        if (cancelBtn) cancelBtn.onclick = backToList;
      }
      return;
    }

    // Edit Customer
    const customerBtn = event.target.closest('.edit-customer-btn');
    if (customerBtn) {
      const listSec = getElement('customerListSection');
      const editSec = getElement('customerEditGridSection');
      
      if (listSec && editSec) {
        listSec.style.display = 'none';
        if (getElement('customerDetailedGridSection')) getElement('customerDetailedGridSection').style.display = 'none';
        if (getElement('customerPaymentGridSection')) getElement('customerPaymentGridSection').style.display = 'none';
        if (getElement('customerInvoicesGridSection')) getElement('customerInvoicesGridSection').style.display = 'none';
        
        // Populate inputs
        getElement('editCustomerId').value = customerBtn.dataset.id;
        getElement('editCustomerName').value = customerBtn.dataset.name;
        getElement('editCustomerPhone').value = customerBtn.dataset.phone;
        getElement('editCustomerEmail').value = customerBtn.dataset.email;
        getElement('editCustomerPincode').value = customerBtn.dataset.pincode;
        getElement('editCustomerAddress').value = customerBtn.dataset.address;
        
        editSec.style.display = 'block';

        // Setup back triggers
        const backBtn = getElement('backToCustomerListFromEditBtn');
        const cancelBtn = getElement('cancelCustomerEditBtn');
        const goBack = () => {
          editSec.style.display = 'none';
          listSec.style.display = 'block';
        };
        if (backBtn) backBtn.onclick = goBack;
        if (cancelBtn) cancelBtn.onclick = goBack;
      }
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
        status: invoiceBtn.dataset.status,
        collected_photo: invoiceBtn.dataset.collected_photo
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

    // Print Invoice helper
    function printOrderInvoice(order, customer) {
      const printWindow = window.open('', '_blank');
      
      let addonsHtml = '';
      if (order.raw_addons && order.raw_addons.length > 0) {
        addonsHtml = `
          <tr class="heading">
            <td colspan="2" style="background: #f7f9fa; border-bottom: 1px solid #ddd; font-weight: bold; padding: 6px 10px; font-size: 0.85rem; text-align: left; text-transform: uppercase; color: #555;">Add-on Items</td>
          </tr>
          ${order.raw_addons.map(addon => `
            <tr class="item">
              <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;">${escapeHtml(addon.name)}</td>
              <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">$${Number(addon.price).toFixed(2)}</td>
            </tr>
          `).join('')}
        `;
      }

      const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Invoice - ${order.id}</title>
          <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; padding: 20px; background-color: #fafafa; }
            .invoice-box { max-width: 800px; margin: auto; padding: 40px; border: 1px solid #eee; box-shadow: 0 4px 12px rgba(0, 0, 0, .05); font-size: 14px; line-height: 22px; background-color: #fff; border-radius: 8px; }
            .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
            .invoice-box table td { padding: 8px; vertical-align: top; }
            .invoice-box table tr td:nth-child(2) { text-align: right; }
            .invoice-box table tr.top table td { padding-bottom: 30px; }
            .invoice-box table tr.top table td.title { font-size: 28px; line-height: 32px; color: #FF6B6B; font-weight: bold; letter-spacing: 0.5px; }
            .invoice-box table tr.information table td { padding-bottom: 30px; }
            .invoice-box table tr.heading td { background: #FF6B6B; color: #fff; font-weight: bold; padding: 10px; font-size: 0.9rem; text-transform: uppercase; }
            .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
            .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #FF6B6B; font-weight: bold; font-size: 18px; color: #FF6B6B; padding-top: 15px; }
            @media print {
              body { padding: 0; background-color: #fff; }
              .invoice-box { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            }
          </style>
        </head>
        <body>
          <div class="invoice-box">
            <table>
              <tr class="top">
                <td colspan="2">
                  <table>
                    <tr>
                      <td class="title">KP'S KITCHEN</td>
                      <td style="text-align: right; font-size: 0.9rem; color: #777;">
                        Invoice ID: <strong>${escapeHtml(order.id)}</strong><br>
                        Invoice Date: ${escapeHtml(order.date)}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr class="information">
                <td colspan="2">
                  <table>
                    <tr>
                      <td style="color: #666; font-size: 0.85rem;">
                        <strong>KP's Kitchen Pty Ltd.</strong><br>
                        120 Collins Street<br>
                        Melbourne, VIC 3000
                      </td>
                      <td style="text-align: right; color: #666; font-size: 0.85rem;">
                        <strong>Bill To:</strong><br>
                        ${escapeHtml(customer.name)}<br>
                        ${escapeHtml(customer.phone)}<br>
                        ${escapeHtml(customer.email)}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr class="heading">
                <td style="text-align: left;">Ordered Service</td>
                <td style="text-align: right;">Price</td>
              </tr>
              <tr class="item">
                <td style="text-align: left; padding: 12px 10px;">
                  <strong>${escapeHtml(order.tiffin)}</strong><br>
                  <span style="font-size: 0.75rem; color: #777;">Daily Subscription Plan Combo</span>
                </td>
                <td style="text-align: right; padding: 12px 10px; font-weight: 600;">
                  $${(Number(order.amount) - (order.raw_addons ? order.raw_addons.reduce((sum, a) => sum + Number(a.price), 0) : 0)).toFixed(2)}
                </td>
              </tr>
              ${addonsHtml}
              <tr class="total">
                <td></td>
                <td style="text-align: right;">Total Amount Due: $${Number(order.amount).toFixed(2)}</td>
              </tr>
            </table>
          </div>
          <script>
            window.onload = function() {
              window.print();
              window.onafterprint = function() { window.close(); };
            }
          </script>
        </body>
        </html>
      `;

      printWindow.document.open();
      printWindow.document.write(htmlContent);
      printWindow.document.close();
    }

    function printWeeklyInvoice(inv, orders, customer) {
      const weekOrders = orders.filter(o => {
        return o.date >= inv.start_of_week && o.date <= inv.end_of_week;
      });

      // Fallback if no orders mapped inside the week range
      if (weekOrders.length === 0) {
        const singleOrder = orders.find(o => String(o.id) === String(inv.order_id));
        if (singleOrder) {
          weekOrders.push(singleOrder);
        } else {
          weekOrders.push({
            id: inv.order_id || 'N/A',
            date: inv.created_at || 'N/A',
            tiffin: 'Tiffin Plan Service',
            addons: 'None',
            amount: inv.amount,
            raw_addons: []
          });
        }
      }

      const printWindow = window.open('', '_blank');
      const totalAmount = weekOrders.reduce((sum, o) => sum + Number(o.amount || 0), 0);

      let ordersHtml = '';
      weekOrders.forEach((order, index) => {
        let addonsListHtml = '';
        if (order.raw_addons && order.raw_addons.length > 0) {
          addonsListHtml = `
            <div style="margin-top: 5px; padding-left: 10px; border-left: 2px solid #ddd; font-size: 0.8rem; color: #666;">
              <strong>Add-ons:</strong> ${order.raw_addons.map(a => `${escapeHtml(a.name)} (x${a.qty || 1}) - $${Number(a.price).toFixed(2)}`).join(', ')}
            </div>
          `;
        }

        ordersHtml += `
          <tr class="item ${index === weekOrders.length - 1 ? 'last' : ''}">
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle;">
              <strong style="color: #333;">Order #${escapeHtml(order.id)}</strong> <span style="font-size: 0.8rem; color: #888; margin-left: 8px;">(${escapeHtml(order.date)})</span>
              <div style="font-size: 0.85rem; color: #555; margin-top: 4px;"><strong>Tiffin:</strong> ${escapeHtml(order.tiffin)}</div>
              ${addonsListHtml}
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right; vertical-align: middle; font-weight: 600;">
              $${Number(order.amount).toFixed(2)}
            </td>
          </tr>
        `;
      });

      const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Weekly Invoice - ${inv.id}</title>
          <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; padding: 20px; background-color: #fafafa; }
            .invoice-box { max-width: 800px; margin: auto; padding: 40px; border: 1px solid #eee; box-shadow: 0 4px 12px rgba(0, 0, 0, .05); font-size: 14px; line-height: 22px; background-color: #fff; border-radius: 8px; }
            .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
            .invoice-box table tr td:nth-child(2) { text-align: right; }
            .invoice-box table tr.top table td { padding-bottom: 30px; }
            .invoice-box table tr.information table td { padding-bottom: 40px; }
            .invoice-box table tr.heading td { background: #f8f9fa; border-bottom: 2px solid #ddd; font-weight: bold; padding: 12px; font-size: 0.85rem; text-transform: uppercase; color: #555; }
            .invoice-box table tr.details td { padding-bottom: 20px; }
            .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
            .invoice-box table tr.item.last td { border-bottom: none; }
            .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; font-size: 1.1rem; color: #FF6B6B; padding-top: 15px; }
            .status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
            .status-paid { background-color: #e8f8f0; color: #2ecc71; }
            .status-unpaid { background-color: #fde8e8; color: #e74c3c; }
            .status-pending { background-color: #fef5e7; color: #f39c12; }
          </style>
        </head>
        <body>
          <div class="invoice-box">
            <table>
              <tr class="top">
                <td colspan="2">
                  <table>
                    <tr>
                      <td style="font-size: 28px; line-height: 35px; font-weight: bold; color: #FF6B6B;">
                        KP'S KITCHEN
                      </td>
                      <td style="text-align: right; font-size: 0.9rem; line-height: 1.5;">
                        <strong>Invoice ID:</strong> ${escapeHtml(inv.id)}<br>
                        <strong>Billing Period:</strong> ${escapeHtml(inv.week_range)}<br>
                        <strong>Due Date:</strong> ${escapeHtml(inv.due_date)}<br>
                        <strong>Payment Date:</strong> ${escapeHtml(inv.paid_date)}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              
              <tr class="information">
                <td colspan="2">
                  <table>
                    <tr>
                      <td style="color: #666; font-size: 0.85rem;">
                        <strong>KP's Kitchen Ltd.</strong><br>
                        100 Melbourne Way<br>
                        Melbourne VIC 3000
                      </td>
                      <td style="text-align: right; color: #666; font-size: 0.85rem;">
                        <strong>Customer Name:</strong> ${escapeHtml(customer.name)}<br>
                        <strong>Phone:</strong> ${escapeHtml(customer.phone)}<br>
                        <strong>Email:</strong> ${escapeHtml(customer.email)}<br>
                        <strong>Address:</strong> ${escapeHtml(customer.address)}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              
              <tr class="heading">
                <td style="text-align: left;">Weekly Orders Summary</td>
                <td style="text-align: right;">Price</td>
              </tr>
              
              ${ordersHtml}
              
              <tr class="total">
                <td style="padding-top: 15px; text-align: left; vertical-align: middle;">
                  <strong>Payment Status:</strong>
                  <span class="status-badge status-${inv.status.toLowerCase()}">${escapeHtml(inv.status)}</span>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                  Weekly Total: $${totalAmount.toFixed(2)}
                </td>
              </tr>
            </table>
          </div>
          <script>
            window.onload = function() {
              window.print();
              window.onafterprint = function() { window.close(); };
            }
          <\/script>
        </body>
        </html>
      `;

      printWindow.document.open();
      printWindow.document.write(htmlContent);
      printWindow.document.close();
    }

    // View Customer Details -> Dynamic Inline Grid
    const viewCustomerBtn = event.target.closest('.view-customer-details-btn');
    if (viewCustomerBtn) {
      const customerId = viewCustomerBtn.dataset.id;
      
      const listSec = getElement('customerListSection');
      const gridSec = getElement('customerDetailedGridSection');
      const gridContent = getElement('customerDetailsGridContent');
      
      if (listSec && gridSec && gridContent) {
        listSec.style.display = 'none';
        gridSec.style.display = 'block';
        gridContent.innerHTML = `
          <div style="padding: 40px; text-align: center; color: var(--text-secondary); background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
            <div style="display: inline-block; width: 36px; height: 36px; border: 3px solid rgba(255,107,107,0.2); border-radius: 50%; border-top-color: var(--primary-color); animation: spin 0.8s linear infinite; margin-bottom: 12px;"></div>
            <p style="margin: 0; font-size: 0.9rem; font-weight: 500;">Loading customer profile details & billing cycles...</p>
          </div>
        `;
        
        // Setup Back Button
        const backBtn = getElement('backToCustomerListBtn');
        if (backBtn) {
          backBtn.onclick = () => {
            gridSec.style.display = 'none';
            listSec.style.display = 'block';
          };
        }
      }
      
      (async () => {
        try {
          const response = await apiRequest(`api/customers/${customerId}/details`);
          if (!response.success) {
            throw new Error(response.message || 'Failed to load details.');
          }
          
          const customer = response.customer;
          const addresses = response.addresses;
          const orders = response.orders;
          const weeklyBilling = response.weekly_billing;
          
          const totalOrdersCount = orders.length;
          const totalSpentAmount = orders.reduce((sum, o) => sum + Number(o.amount || 0), 0);
          
          const html = `
            <div style="display: flex; flex-direction: column; gap: 28px; font-family: var(--font-family); color: var(--text-primary);">
              <!-- Top Profile & Statistics Cards Grid -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                <!-- Profile Card -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <h4 style="margin: 0 0 16px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">👤 Contact & Profile</h4>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Name: <strong style="color: var(--text-primary);">${escapeHtml(customer.name)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Phone: <strong style="color: var(--text-primary);">${escapeHtml(customer.phone)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Email: <strong style="color: var(--text-primary);">${escapeHtml(customer.email)}</strong></p>
                </div>
                
                <!-- Billing Account Stats Card -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: center; gap: 16px;">
                  <h4 style="margin: 0 0 4px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">📊 Account Activity</h4>
                  <div style="display: flex; justify-content: space-around; text-align: center; padding-top: 10px;">
                    <div>
                      <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-bottom: 6px;">Total Orders</span>
                      <strong style="font-size: 1.8rem; color: var(--primary-color); font-family: var(--font-title);">${totalOrdersCount}</strong>
                    </div>
                    <div style="border-left: 1px solid var(--panel-border); height: 50px;"></div>
                    <div>
                      <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-bottom: 6px;">Total Spend</span>
                      <strong style="font-size: 1.8rem; color: #2ECC71; font-family: var(--font-title);">$${totalSpentAmount.toFixed(2)}</strong>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Section 1: Saved Addresses -->
              <div>
                <h4 style="margin: 0 0 14px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600;">📍 Saved Delivery Addresses</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 20px;">
                  ${addresses.map(addr => `
                    <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                      <span style="position: absolute; top: 14px; right: 14px; font-size: 0.75rem; padding: 3px 10px; border-radius: 20px; background-color: ${addr.type === 'Primary Address' ? 'rgba(46,204,113,0.1)' : 'rgba(52,152,219,0.1)'}; color: ${addr.type === 'Primary Address' ? '#2ECC71' : '#3498DB'}; font-weight: 600;">${addr.type}</span>
                      <p style="margin: 0 0 8px 0; font-size: 0.9rem; font-weight: 600; padding-right: 120px; color: var(--text-primary);">Postcode: ${escapeHtml(addr.pincode)}</p>
                      <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">${escapeHtml(addr.address)}</p>
                    </div>
                  `).join('')}
                </div>
              </div>
            </div>
          `;
          
          if (gridContent) {
            gridContent.innerHTML = html;
          }
          
        } catch (err) {
          if (gridContent) {
            gridContent.innerHTML = `
              <div style="padding: 30px; text-align: center; color: #E74C3C; background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
                <p style="font-weight: 600; margin: 0 0 10px 0; font-size: 1.05rem;">Error Loading Profile</p>
                <p style="margin: 0; font-size: 0.85rem;">${escapeHtml(err.message)}</p>
              </div>
            `;
          }
        }
      })();
      return;
    }

    // View Customer Payment History -> Dynamic Inline Grid
    const viewCustomerPaymentBtn = event.target.closest('.view-customer-payment-btn');
    if (viewCustomerPaymentBtn) {
      const customerId = viewCustomerPaymentBtn.dataset.id;
      
      const listSec = getElement('customerListSection');
      const paymentSec = getElement('customerPaymentGridSection');
      const paymentContent = getElement('customerPaymentGridContent');
      
      if (listSec && paymentSec && paymentContent) {
        listSec.style.display = 'none';
        if (getElement('customerDetailedGridSection')) getElement('customerDetailedGridSection').style.display = 'none';
        paymentSec.style.display = 'block';
        paymentContent.innerHTML = `
          <div style="padding: 40px; text-align: center; color: var(--text-secondary); background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
            <div style="display: inline-block; width: 36px; height: 36px; border: 3px solid rgba(46,204,113,0.2); border-radius: 50%; border-top-color: #2ECC71; animation: spin 0.8s linear infinite; margin-bottom: 12px;"></div>
            <p style="margin: 0; font-size: 0.9rem; font-weight: 500;">Loading customer payment history & statements...</p>
          </div>
        `;
        
        // Setup Back Button
        const backBtn = getElement('backToCustomerListFromPaymentBtn');
        if (backBtn) {
          backBtn.onclick = () => {
            paymentSec.style.display = 'none';
            listSec.style.display = 'block';
          };
        }
      }
      
      (async () => {
        try {
          const response = await apiRequest(`api/customers/${customerId}/details`);
          if (!response.success) {
            throw new Error(response.message || 'Failed to load details.');
          }
          
          const customer = response.customer;
          const orders = response.orders;
          const weeklyBilling = response.weekly_billing;
          
          const totalOrdersCount = orders.length;
          const totalSpentAmount = orders.reduce((sum, o) => sum + Number(o.amount || 0), 0);
          
          const html = `
            <div style="display: flex; flex-direction: column; gap: 28px; font-family: var(--font-family); color: var(--text-primary);">
              <!-- Section 1: Weekly Billing & Payment History -->
              <div>
                <h4 style="margin: 0 0 14px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600;">💳 Weekly Billing & Statements (Payments on Weekly Basis)</h4>
                <div class="kp_kitchen_admin_panel_table_wrap" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <table class="kp_kitchen_admin_panel_table" style="margin: 0;">
                    <thead class="kp_kitchen_admin_panel_table_head">
                      <tr class="kp_kitchen_admin_panel_table_row">
                        <th class="kp_kitchen_admin_panel_table_heading">Billing Period (Weekly Cycle)</th>
                        <th class="kp_kitchen_admin_table_heading">Orders Placed</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Total Amount</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Billing Status</th>
                      </tr>
                    </thead>
                    <tbody class="kp_kitchen_admin_panel_table_body">
                      ${weeklyBilling.length === 0 ? `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td colspan="4" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6; padding: 20px;">No weekly billing records found.</td>
                        </tr>
                      ` : weeklyBilling.map(week => `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td class="kp_kitchen_admin_panel_table_cell">📅 <strong>${escapeHtml(week.week_range)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell">${week.orders_count} delivery orders</td>
                          <td class="kp_kitchen_admin_panel_table_cell"><strong>$${Number(week.amount).toFixed(2)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell">
                            <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_${week.status === 'Paid' ? 'paid' : 'unpaid'}">${week.status}</span>
                          </td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Section 2: Previous Orders History & Invoice Downloads -->
              <div>
                <h4 style="margin: 0 0 14px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600;">📦 Delivery & Order History</h4>
                <div class="kp_kitchen_admin_panel_table_wrap" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <table class="kp_kitchen_admin_panel_table" style="margin: 0;">
                    <thead class="kp_kitchen_admin_panel_table_head">
                      <tr class="kp_kitchen_admin_panel_table_row">
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 20%;">Order Info</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 25%;">Tiffin Plan</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 25%;">Add-ons ordered</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 15%;">Amount</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 15%; text-align: right;">Invoices</th>
                      </tr>
                    </thead>
                    <tbody class="kp_kitchen_admin_panel_table_body">
                      ${orders.length === 0 ? `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td colspan="5" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6; padding: 20px;">No past orders found.</td>
                        </tr>
                      ` : orders.map((order, idx) => `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td class="kp_kitchen_admin_panel_table_cell">
                            <strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong>
                            <span class="kp_kitchen_admin_panel_table_secondary">${escapeHtml(order.date)}</span>
                          </td>
                          <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.tiffin)}</td>
                          <td class="kp_kitchen_admin_panel_table_cell" style="font-size: 0.8rem; color: var(--text-secondary);">${escapeHtml(order.addons)}</td>
                          <td class="kp_kitchen_admin_panel_table_cell"><strong>$${Number(order.amount).toFixed(2)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell" style="text-align: right;">
                            <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view print-invoice-btn" data-idx="${idx}" style="padding: 4px 10px; font-size: 0.75rem;">🖨️ Print Receipt</button>
                          </td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          `;
          
          if (paymentContent) {
            paymentContent.innerHTML = html;
            
            document.querySelectorAll('#customerPaymentGridSection .print-invoice-btn').forEach(btn => {
              btn.addEventListener('click', e => {
                const idx = parseInt(e.target.dataset.idx);
                const selectedOrder = orders[idx];
                printOrderInvoice(selectedOrder, customer);
              });
            });
          }
          
        } catch (err) {
          if (paymentContent) {
            paymentContent.innerHTML = `
              <div style="padding: 30px; text-align: center; color: #E74C3C; background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
                <p style="font-weight: 600; margin: 0 0 10px 0; font-size: 1.05rem;">Error Loading Profile</p>
                <p style="margin: 0; font-size: 0.85rem;">${escapeHtml(err.message)}</p>
              </div>
            `;
          }
        }
      })();
      return;
    }

    // View Customer Invoices -> Dynamic Inline Grid
    const viewCustomerInvoicesBtn = event.target.closest('.view-customer-invoices-btn');
    if (viewCustomerInvoicesBtn) {
      const customerId = viewCustomerInvoicesBtn.dataset.id;
      
      const listSec = getElement('customerListSection');
      const invoicesSec = getElement('customerInvoicesGridSection');
      const invoicesContent = getElement('customerInvoicesGridContent');
      
      if (listSec && invoicesSec && invoicesContent) {
        listSec.style.display = 'none';
        if (getElement('customerDetailedGridSection')) getElement('customerDetailedGridSection').style.display = 'none';
        if (getElement('customerPaymentGridSection')) getElement('customerPaymentGridSection').style.display = 'none';
        invoicesSec.style.display = 'block';
        invoicesContent.innerHTML = `
          <div style="padding: 40px; text-align: center; color: var(--text-secondary); background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
            <div style="display: inline-block; width: 36px; height: 36px; border: 3px solid rgba(230,126,34,0.2); border-radius: 50%; border-top-color: #E67E22; animation: spin 0.8s linear infinite; margin-bottom: 12px;"></div>
            <p style="margin: 0; font-size: 0.9rem; font-weight: 500;">Loading customer invoice records...</p>
          </div>
        `;
        
        // Setup Back Button
        const backBtn = getElement('backToCustomerListFromInvoicesBtn');
        if (backBtn) {
          backBtn.onclick = () => {
            invoicesSec.style.display = 'none';
            listSec.style.display = 'block';
          };
        }
      }
      
      (async () => {
        try {
          const response = await apiRequest(`api/customers/${customerId}/details`);
          if (!response.success) {
            throw new Error(response.message || 'Failed to load details.');
          }
          
          const customer = response.customer;
          const orders = response.orders;
          const weeklyBilling = response.weekly_billing || [];
          
          const html = `
            <div style="display: flex; flex-direction: column; gap: 28px; font-family: var(--font-family); color: var(--text-primary);">
              <div>
                <h4 style="margin: 0 0 14px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600;">🧾 Customer Weekly Invoices & Statements</h4>
                <div class="kp_kitchen_admin_panel_table_wrap" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <table class="kp_kitchen_admin_panel_table" style="margin: 0;">
                    <thead class="kp_kitchen_admin_panel_table_head">
                      <tr class="kp_kitchen_admin_panel_table_row">
                        <th class="kp_kitchen_admin_panel_table_heading">Billing Cycle</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Orders Placed</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Total Amount</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Billing Status</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="text-align: right;">Actions</th>
                      </tr>
                    </thead>
                    <tbody class="kp_kitchen_admin_panel_table_body">
                      ${weeklyBilling.length === 0 ? `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td colspan="5" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6; padding: 20px;">No billing cycles found for this week range.</td>
                        </tr>
                      ` : weeklyBilling.map((cycle, idx) => `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td class="kp_kitchen_admin_panel_table_cell">📅 <strong>${escapeHtml(cycle.week_range)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell"><strong>${cycle.orders_count} times</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell"><strong>$${Number(cycle.amount).toFixed(2)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell">
                            <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_${cycle.status.toLowerCase()}">${escapeHtml(cycle.status)}</span>
                          </td>
                          <td class="kp_kitchen_admin_panel_table_cell" style="text-align: right;">
                            <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view invoice-print-btn" 
                              data-idx="${idx}" style="padding: 4px 10px; font-size: 0.75rem; background-color: #3498db; color: white; border-color: #2980b9;">
                              🖨️ Print PDF
                            </button>
                          </td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          `;
          
          if (invoicesContent) {
            invoicesContent.innerHTML = html;
            
            // 3. Print Action
            document.querySelectorAll('#customerInvoicesGridSection .invoice-print-btn').forEach(btn => {
              btn.addEventListener('click', e => {
                const idx = parseInt(e.target.dataset.idx);
                const cycle = weeklyBilling[idx];
                
                // Construct a mock invoice object containing week range, start_of_week, end_of_week, status
                const inv = {
                  id: 'INV-' + cycle.start_date.replace(/-/g, '') + '-' + customer.id,
                  week_range: cycle.week_range,
                  start_of_week: cycle.start_date,
                  end_of_week: cycle.end_date,
                  due_date: cycle.end_date,
                  paid_date: cycle.status === 'Paid' ? cycle.end_date : 'N/A',
                  status: cycle.status,
                  amount: cycle.amount
                };
                
                printWeeklyInvoice(inv, orders, customer);
              });
            });
          }
          
        } catch (err) {
          if (invoicesContent) {
            invoicesContent.innerHTML = `
              <div style="padding: 30px; text-align: center; color: #E74C3C; background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
                <p style="font-weight: 600; margin: 0 0 10px 0; font-size: 1.05rem;">Error Loading Invoices</p>
                <p style="margin: 0; font-size: 0.85rem;">${escapeHtml(err.message)}</p>
              </div>
            `;
          }
        }
      })();
      return;
    }

    // View Driver Details -> Dynamic Inline Grid
    const viewDriverBtn = event.target.closest('.view-driver-details-btn');
    if (viewDriverBtn) {
      const driverId = viewDriverBtn.dataset.id;

      const listSec = getElement('driverListSection');
      const detailsSec = getElement('driverDetailsSection');
      const detailsContent = getElement('driverDetailsContent');

      if (listSec && detailsSec && detailsContent) {
        listSec.style.display = 'none';
        if (getElement('driverEditSection')) getElement('driverEditSection').style.display = 'none';
        if (getElement('driverHistorySection')) getElement('driverHistorySection').style.display = 'none';
        detailsSec.style.display = 'block';
        detailsContent.innerHTML = `
          <div style="padding: 40px; text-align: center; color: var(--text-secondary); background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
            <div style="display: inline-block; width: 36px; height: 36px; border: 3px solid rgba(52, 152, 219, 0.2); border-radius: 50%; border-top-color: #3498DB; animation: spin 0.8s linear infinite; margin-bottom: 12px;"></div>
            <p style="margin: 0; font-size: 0.9rem; font-weight: 500;">Loading driver profile details...</p>
          </div>
        `;

        // Bind Back Button
        const backBtn = getElement('backToDriverListFromDetailsBtn');
        if (backBtn) {
          backBtn.onclick = () => {
            detailsSec.style.display = 'none';
            listSec.style.display = 'block';
          };
        }
      }

      (async () => {
        try {
          const response = await apiRequest(`api/drivers/${driverId}/details`);
          if (!response.success) {
            throw new Error(response.message || 'Failed to load details.');
          }

          const driver = response.driver;
          const activeShipments = response.active_shipments;
          const totalOrders = response.total_orders;

          const frontImgHtml = driver.license_copy_front 
            ? `<img src="${getBaseUrl()}/${driver.license_copy_front}" style="width:100%; height:180px; object-fit:contain; border-radius:8px; background-color:#fafafa; border:1px solid var(--panel-border);" alt="Front">`
            : `<div style="height:180px; display:flex; align-items:center; justify-content:center; background:var(--bg-color); border:2px dashed var(--panel-border); border-radius:8px; opacity:0.6;">No Document Uploaded</div>`;

          const backImgHtml = driver.license_copy_back 
            ? `<img src="${getBaseUrl()}/${driver.license_copy_back}" style="width:100%; height:180px; object-fit:contain; border-radius:8px; background-color:#fafafa; border:1px solid var(--panel-border);" alt="Back">`
            : `<div style="height:180px; display:flex; align-items:center; justify-content:center; background:var(--bg-color); border:2px dashed var(--panel-border); border-radius:8px; opacity:0.6;">No Document Uploaded</div>`;

          const html = `
            <div style="display: flex; flex-direction: column; gap: 28px; font-family: var(--font-family); color: var(--text-primary);">
              <!-- Top Profiles & Performance stats Grid -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                <!-- Profile card -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <h4 style="margin: 0 0 16px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">👤 Contact & Profile</h4>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Name: <strong style="color: var(--text-primary);">${escapeHtml(driver.name)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Phone: <strong style="color: var(--text-primary);">${escapeHtml(driver.phone)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Email: <strong style="color: var(--text-primary);">${escapeHtml(driver.email || 'No email registered')}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Address: <span style="color: var(--text-secondary);">${escapeHtml(driver.address || 'No address registered')}</span></p>
                </div>

                <!-- Documentation / Vehicle Info -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <h4 style="margin: 0 0 16px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">🛡️ License & Vehicle Details</h4>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Vehicle Registration: <strong style="color: var(--text-primary);">${escapeHtml(driver.vehicle_reg_no || 'N/A')}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">License Number: <strong style="color: var(--text-primary);">${escapeHtml(driver.license_no || 'N/A')}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">License Expiry: <strong style="color: var(--text-primary);">${escapeHtml(driver.license_expiry || 'N/A')}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Assigned Area Postcode: <strong style="color: var(--primary-color);">${escapeHtml(driver.assigned_zip || 'N/A')}</strong></p>
                </div>

                <!-- Stats & Performance Card -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: center; gap: 16px; min-height: 200px;">
                  <h4 style="margin: 0 0 4px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">📊 Activity Stats</h4>
                  <div style="display: flex; justify-content: space-around; text-align: center; padding-top: 8px;">
                    <div>
                      <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-bottom: 6px;">Active Deliveries</span>
                      <strong style="font-size: 1.8rem; color: #3498DB; font-family: var(--font-title);">${activeShipments}</strong>
                    </div>
                    <div style="border-left: 1px solid var(--panel-border); height: 50px;"></div>
                    <div>
                      <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-bottom: 6px;">Assigned Orders</span>
                      <strong style="font-size: 1.8rem; color: var(--primary-color); font-family: var(--font-title);">${totalOrders}</strong>
                    </div>
                  </div>
                  <div style="text-align: center; padding-top: 8px;">
                    <span style="font-size: 0.85rem; color: var(--text-secondary); margin-right: 8px;">Status:</span>
                    <span style="display:inline-block; font-size: 0.8rem; font-weight: 700; padding: 4px 12px; border-radius: 20px; ${driver.status === 'Active' ? 'background-color:rgba(46,204,113,0.1); color:#2ECC71;' : 'background-color:rgba(231,76,60,0.1); color:#E74C3C;'}">${driver.status}</span>
                  </div>
                </div>
              </div>

              <!-- Documents previews section -->
              <div>
                <h4 style="margin: 0 0 14px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600;">📁 Uploaded Identity Documents</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                  <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-bottom: 10px; font-weight: 600; text-align: center;">License Copy (Front)</span>
                    ${frontImgHtml}
                  </div>
                  <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-bottom: 10px; font-weight: 600; text-align: center;">License Copy (Back)</span>
                    ${backImgHtml}
                  </div>
                </div>
              </div>
            </div>
          `;

          if (detailsContent) {
            detailsContent.innerHTML = html;
          }

        } catch (err) {
          if (detailsContent) {
            detailsContent.innerHTML = `
              <div style="padding: 30px; text-align: center; color: #E74C3C; background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
                <p style="font-weight: 600; margin: 0 0 10px 0; font-size: 1.05rem;">Error Loading Profile</p>
                <p style="margin: 0; font-size: 0.85rem;">${escapeHtml(err.message)}</p>
              </div>
            `;
          }
        }
      })();
      return;
    }

    // View Driver History -> Dynamic Inline Grid
    const viewDriverHistoryBtn = event.target.closest('.view-driver-history-btn');
    if (viewDriverHistoryBtn) {
      const driverId = viewDriverHistoryBtn.dataset.id;

      const listSec = getElement('driverListSection');
      const historySec = getElement('driverHistorySection');
      const historyContent = getElement('driverHistoryContent');

      if (listSec && historySec && historyContent) {
        listSec.style.display = 'none';
        if (getElement('driverEditSection')) getElement('driverEditSection').style.display = 'none';
        if (getElement('driverDetailsSection')) getElement('driverDetailsSection').style.display = 'none';
        historySec.style.display = 'block';
        historyContent.innerHTML = `
          <div style="padding: 40px; text-align: center; color: var(--text-secondary); background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
            <div style="display: inline-block; width: 36px; height: 36px; border: 3px solid rgba(230,126,34,0.2); border-radius: 50%; border-top-color: #E67E22; animation: spin 0.8s linear infinite; margin-bottom: 12px;"></div>
            <p style="margin: 0; font-size: 0.9rem; font-weight: 500;">Loading delivery history...</p>
          </div>
        `;

        // Bind Back Button
        const backBtn = getElement('backToDriverListFromHistoryBtn');
        if (backBtn) {
          backBtn.onclick = () => {
            historySec.style.display = 'none';
            listSec.style.display = 'block';
          };
        }
      }

      (async () => {
        try {
          const response = await apiRequest(`api/drivers/${driverId}/details`);
          if (!response.success) {
            throw new Error(response.message || 'Failed to load details.');
          }

          const orders = response.orders || [];

          const html = `
            <div style="display: flex; flex-direction: column; gap: 28px; font-family: var(--font-family); color: var(--text-primary);">
              <!-- Deliveries Table Grid -->
              <div>
                <h4 style="margin: 0 0 14px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600;">🚚 Assigned Deliveries & Full History</h4>
                <div class="kp_kitchen_admin_panel_table_wrap" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <table class="kp_kitchen_admin_panel_table" style="margin: 0;">
                    <thead class="kp_kitchen_admin_panel_table_head">
                      <tr class="kp_kitchen_admin_panel_table_row">
                        <th class="kp_kitchen_admin_panel_table_heading">Order ID & Date</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Customer</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Tiffin Plan</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Dropped Image</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Delivery Status</th>
                      </tr>
                    </thead>
                    <tbody class="kp_kitchen_admin_panel_table_body">
                      ${orders.length === 0 ? `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td colspan="5" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6; padding: 20px;">No deliveries assigned to this driver yet.</td>
                        </tr>
                      ` : orders.map(order => `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td class="kp_kitchen_admin_panel_table_cell">
                            <strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong>
                            <span class="kp_kitchen_admin_panel_table_secondary">${escapeHtml(order.date)}</span>
                          </td>
                          <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(order.customer)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.tiffin)}</td>
                          <td class="kp_kitchen_admin_panel_table_cell">
                            ${order.proof_of_delivery_photo ? `
                              <a href="${getBaseUrl()}/${order.proof_of_delivery_photo}" target="_blank">
                                <img src="${getBaseUrl()}/${order.proof_of_delivery_photo}" style="max-height: 40px; border-radius: 4px; border: 1px solid var(--panel-border);">
                              </a>
                            ` : `<span style="font-size: 0.8rem; color: var(--text-secondary); opacity: 0.7;">No Photo</span>`}
                          </td>
                          <td class="kp_kitchen_admin_panel_table_cell">
                            <span class="${statusClass(order.status)}">${escapeHtml(order.status)}</span>
                          </td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          `;

          if (historyContent) {
            historyContent.innerHTML = html;
          }

        } catch (err) {
          if (historyContent) {
            historyContent.innerHTML = `
              <div style="padding: 30px; text-align: center; color: #E74C3C; background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
                <p style="font-weight: 600; margin: 0 0 10px 0; font-size: 1.05rem;">Error Loading History</p>
                <p style="margin: 0; font-size: 0.85rem;">${escapeHtml(err.message)}</p>
              </div>
            `;
          }
        }
      })();
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

  // --- Client-side Customer Search ---
  const customerSearchInput = getElement('customerSearchInput');
  const clearSearchBtn = getElement('clearCustomerSearchBtn');
  
  if (customerSearchInput) {
    customerSearchInput.addEventListener('input', () => {
      const filterText = customerSearchInput.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#customersTableBody .kp_kitchen_admin_panel_table_row');
      
      let visibleCount = 0;
      rows.forEach(row => {
        const nameCell = row.cells[0]?.textContent.toLowerCase() || '';
        const phoneCell = row.cells[1]?.textContent.toLowerCase() || '';
        const emailCell = row.cells[2]?.textContent.toLowerCase() || '';
        
        if (nameCell.includes(filterText) || phoneCell.includes(filterText) || emailCell.includes(filterText)) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      if (clearSearchBtn) {
        clearSearchBtn.style.display = filterText.length > 0 ? 'inline-flex' : 'none';
      }
      
      let noResultsRow = document.getElementById('customerNoResultsRow');
      if (visibleCount === 0 && rows.length > 0) {
        if (!noResultsRow) {
          noResultsRow = document.createElement('tr');
          noResultsRow.id = 'customerNoResultsRow';
          noResultsRow.innerHTML = `<td colspan="6" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6; padding: 20px;">No customers found matching "${escapeHtml(customerSearchInput.value)}".</td>`;
          document.getElementById('customersTableBody').appendChild(noResultsRow);
        } else {
          noResultsRow.style.display = '';
          noResultsRow.querySelector('td').textContent = `No customers found matching "${customerSearchInput.value}".`;
        }
      } else if (noResultsRow) {
        noResultsRow.style.display = 'none';
      }
    });
    
    if (clearSearchBtn) {
      clearSearchBtn.addEventListener('click', () => {
        customerSearchInput.value = '';
        customerSearchInput.dispatchEvent(new Event('input'));
      });
    }
  }

  // --- Initialise ---
  initSearchQuery();
  loadStateData();
  initCollapsibleSidebar();
})();
