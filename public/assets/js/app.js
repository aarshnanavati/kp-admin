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

    // Seed from the server-rendered bootstrap so modal dropdowns are populated
    // even before the async /api/data call resolves. Refreshed by loadStateData().
    try {
        if (window.__kpBootstrap) {
            if (Array.isArray(window.__kpBootstrap.categories) && window.__kpBootstrap.categories.length) {
                categories = window.__kpBootstrap.categories;
            }
            if (Array.isArray(window.__kpBootstrap.items) && window.__kpBootstrap.items.length) {
                items = window.__kpBootstrap.items;
            }
        }
    } catch (e) { /* no-op */ }

    // Chart instances
    let ordersChartInstance = null;
    let itemsChartInstance = null;

    const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const getBaseUrl = () => (window.AppConfig && window.AppConfig.baseUrl) ? window.AppConfig.baseUrl : '';

    const formatDateStr = (str) => {
        if (!str || str === 'N/A') return 'N/A';
        const date = new Date(str);
        if (isNaN(date.getTime())) return str;
        const day = String(date.getDate()).padStart(2, '0');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[date.getMonth()];
        const year = date.getFullYear();
        return `${day} ${month} ${year}`;
    };

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

    // Normalised list of plan "slot" components (fixed items + "Or" choices).
    // Falls back to seeding fixed slots from the legacy free-text basic list.
    const getTiffinComponents = tiffin => {
        const raw = tiffin && tiffin.items && !Array.isArray(tiffin.items)
            ? tiffin.items.components
            : null;

        if (Array.isArray(raw) && raw.length) {
            return raw.map(c => {
                const options = (Array.isArray(c.options) ? c.options : []).map(o => ({
                    name: o.name || '',
                    item_id: (o.item_id !== undefined && o.item_id !== null) ? o.item_id : '',
                    price_delta: Number(o.price_delta || 0),
                    default: !!o.default,
                })).filter(o => o.name);
                return {
                    label: c.label || '',
                    type: (c.type === 'fixed' || options.length < 2) ? 'fixed' : 'single_choice',
                    required: c.required !== undefined ? !!c.required : true,
                    options,
                };
            }).filter(c => c.label && c.options.length);
        }

        return getTiffinBasicItems(tiffin).map(name => ({
            label: name,
            type: 'fixed',
            required: true,
            options: [{ name, item_id: '', price_delta: 0, default: true }],
        }));
    };

    // Builds a menu-item <select>, grouped by category, for picking a slot option.
    // Resolves the current option to a catalog item (by id, then by name); an
    // option that matches no catalog item falls back to the "custom name" field.
    const tiffinItemSelectHtml = opt => {
        const o = opt || {};
        const nameLc = String(o.name || '').trim().toLowerCase();

        let matchedId = (o.item_id !== undefined && o.item_id !== null && o.item_id !== '')
            ? Number(o.item_id)
            : null;
        if ((!matchedId || Number.isNaN(matchedId)) && nameLc) {
            const m = items.find(i => String(i.name).toLowerCase() === nameLc);
            matchedId = m ? Number(m.id) : null;
        }
        const isCustom = !matchedId && nameLc !== '';

        const groups = {};
        items
            .filter(i => (i.status || 'Active') === 'Active')
            .forEach(i => {
                const cat = (i.category && i.category.name) ? i.category.name : 'Other';
                (groups[cat] = groups[cat] || []).push(i);
            });

        const groupHtml = Object.keys(groups).sort((a, b) => a.localeCompare(b)).map(cat => {
            const optionHtml = groups[cat]
                .slice()
                .sort((a, b) => String(a.name).localeCompare(String(b.name)))
                .map(i => `<option value="${i.id}" data-name="${escapeHtml(i.name)}" data-price="${Number(i.price || 0)}" ${matchedId === Number(i.id) ? 'selected' : ''}>${escapeHtml(i.name)}</option>`)
                .join('');
            return `<optgroup label="${escapeHtml(cat)}">${optionHtml}</optgroup>`;
        }).join('');

        return `
      <select class="kp-opt-name kp_kitchen_admin_panel_form_select" style="margin-bottom:0; flex:2; min-width:150px;">
        <option value="" ${(!matchedId && !isCustom) ? 'selected' : ''}>&mdash; Select menu item &mdash;</option>
        ${groupHtml}
        <option value="__custom__" ${isCustom ? 'selected' : ''}>&#9998; Custom name&hellip;</option>
      </select>
      <input type="text" class="kp-opt-custom kp_kitchen_admin_panel_form_input" list="kpTiffinItemNames" value="${escapeHtml(isCustom ? (o.name || '') : '')}" placeholder="Custom item name" style="margin-bottom:0; flex:2; min-width:150px; ${isCustom ? '' : 'display:none;'}">`;
    };

    // Renders one option row inside a component card.
    const tiffinOptionRowHtml = (opt, compUid) => {
        const o = opt || {};
        return `
    <div class="kp-tiffin-opt" style="display:flex; gap:6px; align-items:center; margin-bottom:6px; flex-wrap:wrap;">
      <input type="radio" name="kp_compdefault_${compUid}" class="kp-opt-default" ${o.default ? 'checked' : ''} title="Default / pre-selected option" style="width:auto; margin:0;">
      ${tiffinItemSelectHtml(o)}
      <input type="number" step="0.01" class="kp-opt-delta kp_kitchen_admin_panel_form_input" value="${Number(o.price_delta || 0).toFixed(2)}" title="Extra $ charged if this option is chosen" style="margin-bottom:0; width:84px;">
      <button type="button" class="kp-opt-remove" title="Remove option" style="background:none; border:none; color:var(--danger-color, #e74c3c); cursor:pointer; font-size:1rem; line-height:1;">&times;</button>
    </div>`;
    };

    // Renders one component ("slot") card.
    const tiffinComponentCardHtml = comp => {
        const c = comp || { label: '', required: true, options: [{ name: '', item_id: '', price_delta: 0, default: true }] };
        const uid = Math.random().toString(36).slice(2, 9);
        const opts = (c.options && c.options.length) ? c.options.slice() : [{ name: '', price_delta: 0, default: true }];
        if (!opts.some(o => o.default)) opts[0].default = true;
        return `
    <div class="kp-tiffin-component" data-uid="${uid}" style="border:1px solid var(--panel-border); border-radius:8px; padding:10px 12px; margin-bottom:10px; background:var(--bg-color);">
      <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
        <input type="text" class="kp-comp-label kp_kitchen_admin_panel_form_input" value="${escapeHtml(c.label || '')}" placeholder="Slot name e.g. Bread / Khichadi" style="margin-bottom:0; flex:1; font-weight:600;">
        <label style="display:flex; align-items:center; gap:4px; font-size:0.72rem; white-space:nowrap;"><input type="checkbox" class="kp-comp-required" ${c.required !== false ? 'checked' : ''} style="width:auto; margin:0;"> Required</label>
        <button type="button" class="kp-comp-remove" title="Remove slot" style="background:none; border:none; color:var(--danger-color, #e74c3c); cursor:pointer; font-size:1.1rem; line-height:1;">&times;</button>
      </div>
      <div class="kp-comp-options">
        ${opts.map(o => tiffinOptionRowHtml(o, uid)).join('')}
      </div>
      <button type="button" class="kp-comp-add-option kp_kitchen_admin_panel_small_button" style="padding:3px 10px; font-size:0.72rem;">+ Add "Or" alternative</button>
    </div>`;
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
        modalForm.onsubmit = function (e) {
            const streetInput = modalForm.querySelector('input[name="add_customer_street"]');
            if (streetInput) {
                const suburbInput = modalForm.querySelector('input[name="add_customer_suburb"]');
                const pincodeInput = modalForm.querySelector('input[name="pincode"]');
                const addressHiddenInput = modalForm.querySelector('input[name="address"]');
                if (addressHiddenInput && suburbInput && pincodeInput) {
                    const street = streetInput.value.trim();
                    const suburb = suburbInput.value.trim();
                    const pincode = pincodeInput.value.trim();
                    addressHiddenInput.value = `${street}, ${suburb}, ${pincode}`;
                }
            }
            return true;
        };
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

    // One "name + price" row for bulk item creation.
    const itemNameRowHtml = isFirst => `
    <div class="kp-item-name-row" style="display:flex; gap:6px; align-items:center; margin-bottom:6px;">
      <input name="names[]" class="kp_kitchen_admin_panel_form_input kp-item-name" ${isFirst ? 'required' : ''} placeholder="e.g. Bhakhari" style="margin-bottom:0; flex:1;">
      <input name="prices[]" type="number" step="0.01" min="0" class="kp_kitchen_admin_panel_form_input kp-item-price" ${isFirst ? 'required' : ''} placeholder="Price" style="margin-bottom:0; width:96px;">
      <button type="button" class="kp-item-name-remove" title="Remove" style="background:none; border:none; color:var(--danger-color, #e74c3c); cursor:pointer; font-size:1rem; line-height:1;">&times;</button>
    </div>`;

    const itemFields = item => {
        const isEdit = !!(item && item.id);
        const catOptions = categories.map(c => `<option value="${c.id}" ${c.id === item?.category_id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('');

        const nameBlock = isEdit
            ? `
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Item Name</span>
        <input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(item?.name || '')}" required placeholder="Garlic Bread">
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Price ($ AUD)</span>
        <input name="price" type="number" step="0.01" class="kp_kitchen_admin_panel_form_input" value="${item?.price || ''}" required placeholder="12.50">
      </label>`
            : `
      <div class="kp_kitchen_admin_panel_form_group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <span class="kp_kitchen_admin_panel_form_label" style="margin-bottom:0;">Item Name &amp; Price ($ AUD)</span>
          <button type="button" id="addItemNameBtn" class="kp_kitchen_admin_panel_small_button" style="padding:4px 10px; font-size:0.75rem;">+ Add another</button>
        </div>
        <div id="itemNamesContainer">
          ${itemNameRowHtml(true)}
        </div>
        <p style="font-size:0.72rem; opacity:0.7; margin:4px 0 0;">Add several rows to create multiple items at once, each with its own price. They share the category, description and status below.</p>
      </div>`;

        return `
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Item Image</span>
        <input id="itemImageInput" name="image_file" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*">
        <input id="itemImageData" name="image" type="hidden" value="${escapeHtml(item?.image || '')}">
        <div id="itemImagePreview" class="kp_kitchen_admin_panel_image_preview">${item?.image ? `<img src="${escapeHtml(item.image)}" alt="Preview">` : '<span>Image preview</span>'}</div>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Category</span>
        <select name="category_id" class="kp_kitchen_admin_panel_form_select" required>
          <option value="">Select Category</option>
          ${catOptions}
        </select>
      </label>
      ${nameBlock}
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
        <div id="tiffinImagePreview" class="kp_kitchen_admin_panel_image_preview">${tiffin?.image ? `<img src="${getBaseUrl()}/${escapeHtml(tiffin.image)}" alt="Preview">` : '<span>Image preview</span>'}</div>
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
        <label class="kp_kitchen_admin_panel_form_group" id="tiffinBasePriceField">
          <span class="kp_kitchen_admin_panel_form_label">Base Price ($ AUD)</span>
          <input name="price" type="number" step="0.01" class="kp_kitchen_admin_panel_form_input" value="${tiffin?.price || ''}" required placeholder="19.90">
        </label>
      </div>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Prep Time (mins)</span>
        <input name="prep_time" type="number" class="kp_kitchen_admin_panel_form_input" value="${tiffin?.prep_time || 30}" required>
      </label>

      <div class="kp_kitchen_admin_panel_form_group" style="border:1px solid var(--panel-border); border-radius:8px; padding:12px 14px; background:var(--bg-color);">
        <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer; margin-bottom:0;">
          <input type="checkbox" name="is_customizable" id="tiffinIsCustomizable" value="1" ${tiffin?.is_customizable ? 'checked' : ''} style="width:auto; margin:0;">
          Customize Tiffin (customer builds their own from today&#39;s menu items)
        </label>
        <p id="tiffinCustomizeNote" style="font-size:0.72rem; opacity:0.75; margin:10px 0 0 0; ${tiffin?.is_customizable ? '' : 'display:none;'}">
          This becomes the single Customize Tiffin. It has <strong>no base price</strong> &mdash; the customer's total is the sum of the items they pick.
          The item list is pulled automatically from every other Active tiffin plan, so it stays in sync with today's menu. Any other tiffin currently marked customizable will be unset when you save.
        </p>
      </div>

      <div id="tiffinStandardBuilder" style="${tiffin?.is_customizable ? 'display:none;' : ''}">
      <div class="kp_kitchen_admin_panel_form_group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span class="kp_kitchen_admin_panel_form_label" style="margin-bottom: 0;">Plan Items &amp; &quot;Or&quot; Choices</span>
          <button type="button" id="addTiffinComponentBtn" class="kp_kitchen_admin_panel_small_button" style="padding: 4px 10px; font-size: 0.75rem;">+ Add Slot</button>
        </div>
        <p style="font-size:0.72rem; opacity:0.7; margin:0 0 8px 0;">Each slot is one line of the plan. Pick an item from the menu list (grouped by category) or choose &quot;Custom name&hellip;&quot; to type one. Add a second option to a slot to turn it into an &quot;Or&quot; choice the customer picks. Enter a price only if that option costs extra; the radio marks the pre-selected default.</p>
        <div id="tiffinComponentsContainer">
          ${((getTiffinComponents(tiffin).length ? getTiffinComponents(tiffin) : [null, null, null, null]).map(c => tiffinComponentCardHtml(c)).join(''))}
        </div>
        <input type="hidden" name="components_json" id="tiffinComponentsJson">
        <datalist id="kpTiffinItemNames">
          ${items.map(i => `<option value="${escapeHtml(i.name)}"></option>`).join('')}
        </datalist>
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
        <span class="kp_kitchen_admin_panel_form_label">Password ${c ? '(leave blank to keep current)' : ''}</span>
        <input name="password" type="password" class="kp_kitchen_admin_panel_form_input" placeholder="********" ${c ? '' : 'required'}>
      </label>
      <label class="kp_kitchen_admin_panel_form_group">
        <span class="kp_kitchen_admin_panel_form_label">Default Delivery Postcode</span>
        <input name="pincode" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(c?.pincode || '')}" required placeholder="3000">
      </label>
    </div>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Street Address</span>
      <input name="add_customer_street" class="kp_kitchen_admin_panel_form_input" required placeholder="e.g. 12 Spring St">
    </label>
    <label class="kp_kitchen_admin_panel_form_group">
      <span class="kp_kitchen_admin_panel_form_label">Town/Suburbs</span>
      <input name="add_customer_suburb" class="kp_kitchen_admin_panel_form_input" required placeholder="e.g. Melbourne">
    </label>
    <input name="address" type="hidden">
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

    // Item modal: image preview + "add another name" rows for bulk creation.
    function setupItemFormListeners() {
        setupItemImagePreview();

        const addBtn = getElement('addItemNameBtn');
        const container = getElement('itemNamesContainer');
        if (!addBtn || !container) return;

        addBtn.addEventListener('click', () => {
            const rows = container.querySelectorAll('.kp-item-name-row');
            const lastPrice = rows.length
                ? (rows[rows.length - 1].querySelector('.kp-item-price')?.value || '')
                : '';
            container.insertAdjacentHTML('beforeend', itemNameRowHtml(false));
            const newRow = container.lastElementChild;
            if (lastPrice) newRow.querySelector('.kp-item-price').value = lastPrice;
            newRow.querySelector('.kp-item-name').focus();
        });

        container.addEventListener('click', event => {
            const rm = event.target.closest('.kp-item-name-remove');
            if (rm && container.querySelectorAll('.kp-item-name-row').length > 1) {
                rm.closest('.kp-item-name-row').remove();
            }
        });

        const form = getElement('modalForm');
        if (form) {
            form.addEventListener('submit', () => {
                const rows = [...container.querySelectorAll('.kp-item-name-row')];
                rows.forEach(r => {
                    const name = (r.querySelector('.kp-item-name')?.value || '').trim();
                    const price = (r.querySelector('.kp-item-price')?.value || '').trim();
                    if (!name && !price && rows.length > 1) r.remove();
                });
            });
        }
    }

    function setupTiffinFormListeners() {
        setupTiffinImagePreview();
        const form = getElement('modalForm');
        if (!form) return;

        // Customize Tiffin toggle: hide the slot builder + base price (no base price).
        const customizeToggle = getElement('tiffinIsCustomizable');
        const customizeNote = getElement('tiffinCustomizeNote');
        const standardBuilder = getElement('tiffinStandardBuilder');
        const basePriceField = getElement('tiffinBasePriceField');
        if (customizeToggle) {
            const priceInput = form.querySelector('input[name="price"]');
            const applyCustomizeMode = () => {
                const on = customizeToggle.checked;
                if (customizeNote) customizeNote.style.display = on ? '' : 'none';
                if (standardBuilder) standardBuilder.style.display = on ? 'none' : '';
                if (basePriceField) basePriceField.style.display = on ? 'none' : '';
                if (priceInput) {
                    if (on) {
                        priceInput.dataset.prev = priceInput.value || priceInput.dataset.prev || '';
                        priceInput.value = '0';
                    } else if (priceInput.value === '0' && priceInput.dataset.prev) {
                        priceInput.value = priceInput.dataset.prev;
                    }
                }
            };
            customizeToggle.addEventListener('change', applyCustomizeMode);
            applyCustomizeMode();
        }

        const componentsContainer = getElement('tiffinComponentsContainer');
        const addComponentBtn = getElement('addTiffinComponentBtn');
        const componentsJsonInput = getElement('tiffinComponentsJson');

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
            if (componentsContainer) {
                componentsContainer.querySelectorAll('.kp-tiffin-component').forEach(card => {
                    const optRows = [...card.querySelectorAll('.kp-tiffin-opt')];
                    if (optRows.length < 2) return; // fixed slot -> no price delta
                    const picked = optRows.find(r => r.querySelector('.kp-opt-default')?.checked) || optRows[0];
                    selectedSum += Number(picked.querySelector('.kp-opt-delta')?.value || 0);
                });
            }
            totalDisplay.textContent = `$${(basePrice + selectedSum).toFixed(2)}`;
        };

        // Serialise the slot/option cards into the hidden components_json field.
        const serializeComponents = () => {
            if (!componentsContainer || !componentsJsonInput) return;
            const out = [];
            componentsContainer.querySelectorAll('.kp-tiffin-component').forEach(card => {
                const label = (card.querySelector('.kp-comp-label')?.value || '').trim();
                const required = card.querySelector('.kp-comp-required')?.checked ?? true;
                const options = [...card.querySelectorAll('.kp-tiffin-opt')].map(r => {
                    const sel = r.querySelector('.kp-opt-name');
                    const customEl = r.querySelector('.kp-opt-custom');
                    let name = '';
                    let itemId = '';
                    if (sel && sel.value === '__custom__') {
                        name = (customEl?.value || '').trim();
                        const match = items.find(i => String(i.name).toLowerCase() === name.toLowerCase());
                        if (match) itemId = Number(match.id);
                    } else if (sel && sel.value) {
                        itemId = Number(sel.value);
                        const picked = sel.options[sel.selectedIndex];
                        name = (picked?.dataset.name || picked?.textContent || '').trim();
                    }
                    return {
                        name,
                        item_id: itemId === '' ? '' : Number(itemId),
                        price_delta: Number(r.querySelector('.kp-opt-delta')?.value || 0),
                        default: r.querySelector('.kp-opt-default')?.checked || false,
                    };
                }).filter(o => o.name);
                if (!label || !options.length) return;
                if (!options.some(o => o.default)) options[0].default = true;
                out.push({
                    label,
                    type: options.length > 1 ? 'single_choice' : 'fixed',
                    required,
                    options,
                });
            });
            componentsJsonInput.value = JSON.stringify(out);
        };

        if (addComponentBtn && componentsContainer) {
            addComponentBtn.addEventListener('click', () => {
                componentsContainer.insertAdjacentHTML('beforeend', tiffinComponentCardHtml(null));
                updateCalculatedTotal();
            });
        }

        if (componentsContainer) {
            componentsContainer.addEventListener('click', event => {
                const addOptBtn = event.target.closest('.kp-comp-add-option');
                if (addOptBtn) {
                    const card = addOptBtn.closest('.kp-tiffin-component');
                    card.querySelector('.kp-comp-options')
                        .insertAdjacentHTML('beforeend', tiffinOptionRowHtml(null, card.dataset.uid));
                    updateCalculatedTotal();
                    return;
                }
                const optRemove = event.target.closest('.kp-opt-remove');
                if (optRemove) {
                    const card = optRemove.closest('.kp-tiffin-component');
                    if (card.querySelectorAll('.kp-tiffin-opt').length > 1) {
                        optRemove.closest('.kp-tiffin-opt').remove();
                    } else {
                        card.remove();
                    }
                    updateCalculatedTotal();
                    return;
                }
                const compRemove = event.target.closest('.kp-comp-remove');
                if (compRemove) {
                    compRemove.closest('.kp-tiffin-component').remove();
                    updateCalculatedTotal();
                }
            });

            componentsContainer.addEventListener('change', event => {
                if (event.target.classList.contains('kp-opt-default')) {
                    updateCalculatedTotal();
                }
                if (event.target.classList.contains('kp-opt-name')) {
                    const row = event.target.closest('.kp-tiffin-opt');
                    const customEl = row.querySelector('.kp-opt-custom');
                    const isCustom = event.target.value === '__custom__';
                    if (customEl) {
                        customEl.style.display = isCustom ? '' : 'none';
                        if (isCustom) customEl.focus();
                    }
                    // Auto-name the slot from the picked item's category, if still blank.
                    if (!isCustom && event.target.value) {
                        const card = event.target.closest('.kp-tiffin-component');
                        const labelEl = card.querySelector('.kp-comp-label');
                        const picked = items.find(i => Number(i.id) === Number(event.target.value));
                        if (labelEl && !labelEl.value.trim() && picked && picked.category && picked.category.name) {
                            labelEl.value = picked.category.name;
                        }
                    }
                    updateCalculatedTotal();
                }
            });

            componentsContainer.addEventListener('input', event => {
                if (event.target.classList.contains('kp-opt-delta')) {
                    updateCalculatedTotal();
                }
            });
        }

        form.addEventListener('submit', serializeComponents);

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
            openModal('Add Menu Item', itemFields(), 'Create Item', getBaseUrl() + '/items/save', setupItemFormListeners);
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
            openModal('Edit Menu Item', itemFields(item) + `<input type="hidden" name="id" value="${item.id}">`, 'Save Changes', getBaseUrl() + '/items/save', setupItemFormListeners);
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
                image: tiffinBtn.dataset.image,
                is_customizable: tiffinBtn.dataset.is_customizable === '1',
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

                // Split Name
                const fullName = d.name || '';
                const nameParts = fullName.trim().split(/\s+/);
                const firstName = nameParts[0] || '';
                const lastName = nameParts.slice(1).join(' ') || '';
                if (getElement('editDriverFirstName')) {
                    getElement('editDriverFirstName').value = firstName;
                    getElement('editDriverLastName').value = lastName;
                }
                getElement('editDriverName').value = fullName;
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

                // Split Name
                const fullName = customerBtn.dataset.name || '';
                const nameParts = fullName.trim().split(/\s+/);
                const firstName = nameParts[0] || '';
                const lastName = nameParts.slice(1).join(' ') || '';
                getElement('editCustomerFirstName').value = firstName;
                getElement('editCustomerLastName').value = lastName;
                getElement('editCustomerName').value = fullName;

                getElement('editCustomerPhone').value = customerBtn.dataset.phone;
                getElement('editCustomerEmail').value = customerBtn.dataset.email;
                getElement('editCustomerPincode').value = customerBtn.dataset.pincode;
                if (getElement('editCustomerStatus')) {
                    getElement('editCustomerStatus').value = customerBtn.dataset.status || 'Active';
                }

                // Split Address (format: street, town/suburbs, postcode)
                const rawAddress = customerBtn.dataset.address || '';
                const addressParts = rawAddress.split(',').map(p => p.trim());
                const street = addressParts[0] || '';
                const suburb = addressParts[1] || '';
                getElement('editCustomerStreet').value = street;
                getElement('editCustomerSuburb').value = suburb;
                getElement('editCustomerAddress').value = rawAddress;

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

        // Helper to format date to dd-mm-yyyy
        function formatDateDMY(dateStr) {
            if (!dateStr || dateStr === 'N/A') return dateStr;
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
            return dateStr;
        }

        // Print Invoice helper
        function printOrderInvoice(order, customer) {
            const printWindow = window.open('', '_blank');

            // Resolve custom items or selections
            let customItemsSummary = '';
            if (order.custom_items && order.custom_items.length > 0) {
                customItemsSummary = order.custom_items.map(ci => (ci.name || ci) + (ci.price ? ` ($${Number(ci.price).toFixed(2)})` : '')).join(', ');
            } else if (order.choices_list && order.choices_list.length > 0) {
                customItemsSummary = order.choices_list.map(c => `${c.component}: ${c.chosen}`).join(', ');
            } else if (order.choices && order.choices !== 'None') {
                customItemsSummary = order.choices;
            } else if (order.choices_summary) {
                customItemsSummary = order.choices_summary;
            }

            let customItemsHtml = '';
            if (customItemsSummary) {
                customItemsHtml = `
                    <div style="margin-top: 6px; padding: 6px 10px; background: #f4fbf6; border-left: 3px solid #2ecc71; border-radius: 4px; font-size: 0.82rem; color: #27ae60; line-height: 1.4;">
                        <strong>Customized Meal Items:</strong> ${escapeHtml(customItemsSummary)}
                    </div>
                `;
            }

            let addonsHtml = '';
            if (order.raw_addons && order.raw_addons.length > 0) {
                addonsHtml = `
          <tr class="heading">
            <td colspan="2" style="background: #f7f9fa; border-bottom: 1px solid #ddd; font-weight: bold; padding: 6px 10px; font-size: 0.85rem; text-align: left; text-transform: uppercase; color: #555;">Add-on Items</td>
          </tr>
          ${order.raw_addons.map(addon => `
            <tr class="item">
              <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;">${escapeHtml(addon.name)} (x${addon.qty || 1})</td>
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
                      <td class="title"><img src="${getBaseUrl()}/public/assets/images/logo.png" alt="KP's Kitchen" style="max-height: 80px; width: auto; display: block;"></td>
                      <td style="text-align: right; font-size: 0.9rem; color: #777;">
                        Invoice ID: <strong>${escapeHtml(order.id)}</strong><br>
                        Invoice Date: ${escapeHtml(formatDateDMY(order.date))}
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
                        120 King William Street<br>
                        Adelaide, SA 5000
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
                  <strong style="font-size: 1rem; color: #222;">${escapeHtml(order.tiffin)}</strong> (Qty: <strong>${order.quantity || 1}</strong>)<br>
                  <span style="font-size: 0.75rem; color: #777;">Daily Subscription Plan Meal</span>
                  ${customItemsHtml}
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
            const startDate = inv.start_of_week || inv.start_date;
            const endDate = inv.end_of_week || inv.end_date;
            const weekOrders = (orders || []).filter(o => {
                if (startDate && endDate) {
                    return o.date >= startDate && o.date <= endDate;
                }
                return true;
            });

            // Fallback if no orders mapped inside the week range
            if (weekOrders.length === 0) {
                const singleOrder = (orders || []).find(o => String(o.id) === String(inv.order_id));
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
                let customItemsSummary = '';
                if (order.custom_items && order.custom_items.length > 0) {
                    customItemsSummary = order.custom_items.map(ci => (ci.name || ci) + (ci.price ? ` ($${Number(ci.price).toFixed(2)})` : '')).join(', ');
                } else if (order.choices_list && order.choices_list.length > 0) {
                    customItemsSummary = order.choices_list.map(c => `${c.component}: ${c.chosen}`).join(', ');
                } else if (order.choices && order.choices !== 'None') {
                    customItemsSummary = order.choices;
                } else if (order.choices_summary) {
                    customItemsSummary = order.choices_summary;
                }

                let customItemsHtml = '';
                if (customItemsSummary) {
                    customItemsHtml = `
            <div style="margin-top: 5px; padding-left: 10px; border-left: 2px solid #2ecc71; font-size: 0.8rem; color: #27ae60;">
              <strong>Custom Items:</strong> ${escapeHtml(customItemsSummary)}
            </div>
          `;
                }

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
              <strong style="color: #333;">Order #${escapeHtml(order.id)}</strong> <span style="font-size: 0.8rem; color: #888; margin-left: 8px;">(${escapeHtml(formatDateDMY(order.date))})</span>
              <div style="font-size: 0.85rem; color: #555; margin-top: 4px;"><strong>Tiffin:</strong> ${escapeHtml(order.tiffin)} (Qty: <strong>${order.quantity || 1}</strong>)</div>
              ${customItemsHtml}
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
            .status-badge status-paid { background-color: #e8f8f0; color: #2ecc71; }
            .status-badge status-unpaid { background-color: #fde8e8; color: #e74c3c; }
            .status-badge status-pending { background-color: #fef5e7; color: #f39c12; }
          </style>
        </head>
        <body>
          <div class="invoice-box">
            <table>
              <tr class="top">
                <td colspan="2">
                  <table>
                    <tr>
                      <td style="font-size: 28px; line-height: 35px;">
                        <img src="${getBaseUrl()}/public/assets/images/logo.png" alt="KP's Kitchen" style="max-height: 80px; width: auto; display: block;">
                      </td>
                      <td style="text-align: right; font-size: 0.9rem; line-height: 1.5;">
                        <strong>Invoice ID:</strong> ${escapeHtml(inv.id)}<br>
                        <strong>Billing Period:</strong> ${escapeHtml(formatDateDMY(inv.start_of_week))} - ${escapeHtml(formatDateDMY(inv.end_of_week))}<br>
                        <strong>Due Date:</strong> ${escapeHtml(formatDateDMY(inv.due_date))}<br>
                        <strong>Payment Date:</strong> ${escapeHtml(formatDateDMY(inv.paid_date))}
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
                        120 King William Street<br>
                        Adelaide, SA 5000
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
                if (getElement('historySearchInput')) {
                    getElement('historySearchInput').value = '';
                }
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
              <!-- Section 2: Previous Orders History & Invoice Downloads -->
              <div>
                <h4 style="margin: 0 0 14px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600;">📦 Delivery & Order History</h4>
                <div class="kp_kitchen_admin_panel_table_wrap" style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <table class="kp_kitchen_admin_panel_table" style="margin: 0;">
                    <thead class="kp_kitchen_admin_panel_table_head">
                      <tr class="kp_kitchen_admin_panel_table_row">
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 14%;">Order ID</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 12%;">Date</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 22%;">Tiffin Plan</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 8%; text-align: center;">Quantity</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 18%;">Add-ons ordered</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 10%;">Amount</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 6%; text-align: center;">Details</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="width: 10%; text-align: right;">Invoices</th>
                      </tr>
                    </thead>
                    <tbody class="kp_kitchen_admin_panel_table_body">
                      ${orders.length === 0 ? `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td colspan="8" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6; padding: 20px;">No past orders found.</td>
                        </tr>
                      ` : orders.map((order, idx) => `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td class="kp_kitchen_admin_panel_table_cell">
                            <strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong>
                          </td>
                          <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(formatDateDMY(order.date))}</td>
                          <td class="kp_kitchen_admin_panel_table_cell">
                            <strong style="color: var(--text-primary);">${escapeHtml(order.tiffin)}</strong>
                            ${(order.choices && order.choices !== 'None') ? `
                              <div style="font-size: 0.78rem; color: #27ae60; margin-top: 3px; font-weight: 500;">
                                ${escapeHtml(order.choices)}
                              </div>
                            ` : ''}
                          </td>
                          <td class="kp_kitchen_admin_panel_table_cell" style="text-align: center;"><strong>${escapeHtml(order.quantity || 1)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell" style="font-size: 0.8rem; color: var(--text-secondary);">${escapeHtml(order.addons)}</td>
                          <td class="kp_kitchen_admin_panel_table_cell"><strong>$${Number(order.amount).toFixed(2)}</strong></td>
                          <td class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">
                            <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-order-details-btn"
                              style="background: rgba(52, 152, 219, 0.1); border: 1px solid rgba(52, 152, 219, 0.2); color: #3498DB; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                              title="View Order Details"
                              data-id="${escapeHtml(order.id)}">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                              </svg>
                            </button>
                          </td>
                          <td class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">
                            <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view print-invoice-btn"
                              style="background: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.2); color: #2ECC71; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                              title="Print Receipt"
                              data-idx="${idx}">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" style="pointer-events: none;">
                                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                              </svg>
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

                    if (paymentContent) {
                        paymentContent.innerHTML = html;

                        document.querySelectorAll('#customerPaymentGridSection .print-invoice-btn').forEach(btn => {
                            btn.addEventListener('click', e => {
                                const targetBtn = e.target.closest('.print-invoice-btn');
                                if (!targetBtn) return;
                                const idx = parseInt(targetBtn.dataset.idx);
                                const selectedOrder = orders[idx];
                                const ordDate = selectedOrder.date;
                                const matchedCycle = (weeklyBilling || []).find(w => ordDate >= w.start_date && ordDate <= w.end_date);
                                if (matchedCycle) {
                                    printWeeklyInvoice(matchedCycle, orders, customer);
                                } else {
                                    const d = new Date(ordDate);
                                    const day = d.getDay();
                                    const diffToMon = d.getDate() - day + (day === 0 ? -6 : 1);
                                    const monDate = new Date(d.setDate(diffToMon));
                                    const sunDate = new Date(monDate);
                                    sunDate.setDate(monDate.getDate() + 6);
                                    const mon = monDate.toISOString().split('T')[0];
                                    const sun = sunDate.toISOString().split('T')[0];
                                    printWeeklyInvoice({
                                        id: 'INV-W' + mon.replace(/-/g, '') + '-' + customer.id,
                                        start_of_week: mon,
                                        end_of_week: sun,
                                        due_date: sun,
                                        paid_date: 'N/A',
                                        status: 'Pending',
                                        amount: selectedOrder.amount
                                    }, orders, customer);
                                }
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
                        <th class="kp_kitchen_admin_panel_table_heading">Invoice No</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Billing Cycle</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Total Amount</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Due Date</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Paid Date</th>
                        <th class="kp_kitchen_admin_panel_table_heading">Billing Status</th>
                        <th class="kp_kitchen_admin_panel_table_heading" style="text-align: right;">Actions</th>
                      </tr>
                    </thead>
                    <tbody class="kp_kitchen_admin_panel_table_body">
                      ${weeklyBilling.length === 0 ? `
                        <tr class="kp_kitchen_admin_panel_table_row">
                          <td colspan="7" class="kp_kitchen_admin_panel_table_cell" style="text-align: center; opacity: 0.6; padding: 20px;">No billing statements found.</td>
                        </tr>
                      ` : weeklyBilling.map((cycle, idx) => {
                        const invoiceNo = 'INV-' + cycle.start_date.replace(/-/g, '') + '-' + customer.id;
                        return `
                          <tr class="kp_kitchen_admin_panel_table_row">
                            <td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(invoiceNo)}</strong></td>
                            <td class="kp_kitchen_admin_panel_table_cell">📅 <strong>${escapeHtml(formatDateDMY(cycle.start_date))} - ${escapeHtml(formatDateDMY(cycle.end_date))}</strong></td>
                            <td class="kp_kitchen_admin_panel_table_cell"><strong>$${Number(cycle.amount).toFixed(2)}</strong></td>
                            <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(formatDateDMY(cycle.end_date))}</td>
                            <td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(formatDateDMY(cycle.paid_date))}</td>
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
                        `;
                    }).join('')}
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
                                const invoiceNo = 'INV-' + cycle.start_date.replace(/-/g, '') + '-' + customer.id;

                                const inv = {
                                    id: invoiceNo,
                                    week_range: cycle.week_range,
                                    start_of_week: cycle.start_date,
                                    end_of_week: cycle.end_date,
                                    due_date: formatDateStr(cycle.end_date),
                                    paid_date: formatDateStr(cycle.paid_date),
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

                    const appr = driver.approval_status || 'Approved';
                    const apprMeta = {
                        Pending: { bg: 'rgba(241,196,15,0.12)', color: '#B7950B', label: 'Pending Approval' },
                        Approved: { bg: 'rgba(46,204,113,0.12)', color: '#2ECC71', label: 'Approved' },
                        Rejected: { bg: 'rgba(231,76,60,0.12)', color: '#E74C3C', label: 'Rejected' }
                    }[appr] || { bg: 'rgba(52,152,219,0.12)', color: '#3498DB', label: appr };

                    const approvalPanel = `
              <div style="background-color: var(--bg-color); border: 1px solid ${appr === 'Pending' ? '#F1C40F' : 'var(--panel-border)'}; border-radius: 12px; padding: 18px 20px; display:flex; flex-wrap:wrap; gap:16px; align-items:center; justify-content:space-between;">
                <div>
                  <div style="display:flex; align-items:center; gap:10px;">
                    <h4 style="margin:0; color: var(--primary-color); font-size:1.05rem; font-weight:600;">🔎 Registration Review</h4>
                    <span style="display:inline-block; font-size:0.78rem; font-weight:700; padding:3px 12px; border-radius:20px; background:${apprMeta.bg}; color:${apprMeta.color};">${apprMeta.label}</span>
                  </div>
                  <p style="margin:8px 0 0 0; font-size:0.82rem; color: var(--text-secondary);">
                    Registered: <strong style="color:var(--text-primary);">${escapeHtml(driver.registered_at || 'N/A')}</strong>
                    ${driver.reviewed_at ? ` &nbsp;·&nbsp; Reviewed: <strong style="color:var(--text-primary);">${escapeHtml(driver.reviewed_at)}</strong>` : ''}
                    ${appr === 'Rejected' && driver.rejection_reason ? `<br>Reason: <em>${escapeHtml(driver.rejection_reason)}</em>` : ''}
                  </p>
                  ${appr === 'Pending' ? `<p style="margin:8px 0 0 0; font-size:0.8rem; color:#B7950B;">This driver cannot log in until you approve the profile below.</p>` : ''}
                </div>
                <div style="display:flex; gap:10px;">
                  ${appr !== 'Approved' ? `<button type="button" class="approve-driver-btn kp_kitchen_admin_panel_primary_button" data-id="${driver.id}" data-name="${escapeHtml(driver.name)}" style="padding:8px 18px;">✔ Approve</button>` : ''}
                  ${appr !== 'Rejected' ? `<button type="button" class="reject-driver-btn kp_kitchen_admin_panel_danger_button" data-id="${driver.id}" data-name="${escapeHtml(driver.name)}" style="padding:8px 18px;">✖ Reject</button>` : ''}
                </div>
              </div>
            `;

                    const html = `
            <div style="display: flex; flex-direction: column; gap: 28px; font-family: var(--font-family); color: var(--text-primary);">
              ${approvalPanel}
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

        // Approve a pending driver registration
        const approveDriverBtn = event.target.closest('.approve-driver-btn');
        if (approveDriverBtn) {
            const driverId = approveDriverBtn.dataset.id;
            const driverName = approveDriverBtn.dataset.name || 'this driver';
            if (!confirm(`Approve ${driverName}? They will be able to log in to the driver app.`)) return;
            approveDriverBtn.disabled = true;
            approveDriverBtn.textContent = 'Approving...';
            (async () => {
                try {
                    const res = await apiRequest(`api/drivers/${driverId}/approve`, 'POST', {});
                    showToast(res.message || 'Driver approved.');
                    setTimeout(() => window.location.reload(), 600);
                } catch (err) {
                    showToast(err.message || 'Could not approve driver.');
                    approveDriverBtn.disabled = false;
                    approveDriverBtn.textContent = '✔ Approve';
                }
            })();
            return;
        }

        // Reject a pending driver registration
        const rejectDriverBtn = event.target.closest('.reject-driver-btn');
        if (rejectDriverBtn) {
            const driverId = rejectDriverBtn.dataset.id;
            const driverName = rejectDriverBtn.dataset.name || 'this driver';
            const reason = prompt(`Reject ${driverName}? Optionally add a reason (shown to the driver):`, '');
            if (reason === null) return;
            rejectDriverBtn.disabled = true;
            rejectDriverBtn.textContent = 'Rejecting...';
            (async () => {
                try {
                    const res = await apiRequest(`api/drivers/${driverId}/reject`, 'POST', { reason });
                    showToast(res.message || 'Driver rejected.');
                    setTimeout(() => window.location.reload(), 600);
                } catch (err) {
                    showToast(err.message || 'Could not reject driver.');
                    rejectDriverBtn.disabled = false;
                    rejectDriverBtn.textContent = '✖ Reject';
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
                        <th class="kp_kitchen_admin_panel_table_heading">Customer Address</th>
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
                          <td class="kp_kitchen_admin_panel_table_cell" style="font-size: 0.85rem; max-width: 280px; white-space: normal; line-height: 1.4;">${escapeHtml(order.customer_address || 'No address')}</td>
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

        // View Order Details -> Dynamic Inline Grid
        const viewOrderDetailsBtn = event.target.closest('.view-order-details-btn');
        if (viewOrderDetailsBtn) {
            const orderId = viewOrderDetailsBtn.dataset.id;

            // Check if we are inside Customer Management or Orders Page
            const custDetailsSec = getElement('customerOrderDetailGridSection');
            const custDetailsContent = getElement('customerOrderDetailGridContent');
            const custPaymentSec = getElement('customerPaymentGridSection');

            const ordersListSec = getElement('ordersListSection');
            const ordersDetailsSec = getElement('orderDetailsGridSection');
            const ordersDetailsContent = getElement('orderDetailsGridContent');

            let activeDetailsSec = null;
            let activeContentSec = null;

            if (custDetailsSec && custDetailsContent && custPaymentSec && custPaymentSec.style.display !== 'none') {
                custPaymentSec.style.display = 'none';
                custDetailsSec.style.display = 'block';
                activeDetailsSec = custDetailsSec;
                activeContentSec = custDetailsContent;

                const backBtn = getElement('backToCustomerPaymentFromOrderDetailsBtn');
                if (backBtn) {
                    backBtn.onclick = () => {
                        custDetailsSec.style.display = 'none';
                        custPaymentSec.style.display = 'block';
                    };
                }
            } else if (ordersListSec && ordersDetailsSec && ordersDetailsContent) {
                ordersListSec.style.display = 'none';
                ordersDetailsSec.style.display = 'block';
                activeDetailsSec = ordersDetailsSec;
                activeContentSec = ordersDetailsContent;

                const backBtn = getElement('backToOrdersListBtn');
                if (backBtn) {
                    backBtn.onclick = () => {
                        ordersDetailsSec.style.display = 'none';
                        ordersListSec.style.display = 'block';
                    };
                }
            }

            if (activeContentSec) {
                activeContentSec.innerHTML = `
          <div style="padding: 40px; text-align: center; color: var(--text-secondary); background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
            <div style="display: inline-block; width: 36px; height: 36px; border: 3px solid rgba(52, 152, 219, 0.2); border-radius: 50%; border-top-color: #3498DB; animation: spin 0.8s linear infinite; margin-bottom: 12px;"></div>
            <p style="margin: 0; font-size: 0.9rem; font-weight: 500;">Loading order details...</p>
          </div>
        `;
            }

            (async () => {
                try {
                    const response = await apiRequest(`api/orders/${orderId}/details`);
                    if (!response.success) {
                        throw new Error(response.message || 'Failed to load details.');
                    }

                    const order = response.order;

                    // Build custom items / selections html
                    let customItemsHtml = '';
                    if (order.custom_items && order.custom_items.length > 0) {
                        customItemsHtml = `
              <div style="margin-top: 14px; margin-bottom: 14px;">
                <span style="font-size: 0.85rem; color: #27ae60; display: block; margin-bottom: 8px; font-weight: 600;">Customized Meal Items:</span>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                  ${order.custom_items.map(ci => `
                    <span style="background: rgba(46, 204, 113, 0.12); color: #27ae60; border: 1px solid rgba(46, 204, 113, 0.3); border-radius: 6px; padding: 5px 12px; font-size: 0.85rem; font-weight: 600;">
                      ${escapeHtml(ci.name || ci)} ${ci.price ? `<span style="font-weight: normal; color: #555;">($${Number(ci.price).toFixed(2)})</span>` : ''}
                    </span>
                  `).join('')}
                </div>
              </div>
            `;
                    } else if (order.selections && order.selections.length > 0) {
                        customItemsHtml = `
              <div style="margin-top: 14px; margin-bottom: 14px;">
                <span style="font-size: 0.85rem; color: #2980b9; display: block; margin-bottom: 8px; font-weight: 600;">Selected Meal Choices:</span>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                  ${order.selections.map(sel => `
                    <span style="background: rgba(52, 152, 219, 0.12); color: #2980b9; border: 1px solid rgba(52, 152, 219, 0.3); border-radius: 6px; padding: 5px 12px; font-size: 0.85rem; font-weight: 600;">
                      ${escapeHtml(sel.component)}: ${escapeHtml(sel.chosen)}
                    </span>
                  `).join('')}
                </div>
              </div>
            `;
                    } else if (order.choices_summary) {
                        customItemsHtml = `
              <div style="margin-top: 10px; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">
                <strong style="color: #27ae60;">Custom Items:</strong> ${escapeHtml(order.choices_summary)}
              </div>
            `;
                    }

                    // Build addons list html
                    let addonsHtml = '';
                    if (order.add_ons && order.add_ons.length > 0) {
                        addonsHtml = `
              <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary);">
                ${order.add_ons.map(addon => `
                  <li><strong>${escapeHtml(addon.name)}</strong> (Quantity: <strong>${addon.qty || 1}</strong>) - $${Number(addon.price).toFixed(2)}</li>
                `).join('')}
              </ul>
            `;
                    } else {
                        addonsHtml = `<p style="margin: 0; font-size: 0.9rem; font-style: italic; color: var(--text-secondary);">No extra addons ordered.</p>`;
                    }

                    // Build Proof of Delivery / Drop Photo Card
                    const podHtml = `
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                  <h4 style="margin: 0 0 16px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <span>📸 Proof of Delivery</span>
                    <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_${(order.status || 'Pending').toLowerCase().replace(/ /g, '')}">${escapeHtml(order.status)}</span>
                  </h4>
                  <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 14px;">
                    ${order.proof_of_delivery_photo ? `
                      <div style="background: var(--panel-bg); border: 1px solid var(--panel-border); border-radius: 12px; padding: 14px; width: 100%; display: flex; flex-direction: column; gap: 10px; align-items: center; box-sizing: border-box;">
                        <span style="font-size: 0.85rem; color: #27ae60; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                          ✅ Drop Photo Uploaded (${escapeHtml(order.driver_name || 'Driver')})
                        </span>
                        <a href="${getBaseUrl()}/${order.proof_of_delivery_photo}" target="_blank" title="Click to view full resolution drop photo" style="display: block; width: 100%; max-width: 280px; text-align: center;">
                          <img src="${getBaseUrl()}/${order.proof_of_delivery_photo}" alt="Drop-off Photo" style="max-height: 200px; width: 100%; border-radius: 8px; border: 1px solid var(--panel-border); object-fit: cover; display: block; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                        </a>
                        <span style="font-size: 0.78rem; color: var(--text-secondary); text-align: center;">🔍 Click photo to open full resolution</span>
                      </div>
                    ` : `
                      <div style="padding: 30px 20px; text-align: center; background: rgba(255, 255, 255, 0.02); border: 1px dashed var(--panel-border); border-radius: 10px; width: 100%; box-sizing: border-box;">
                        <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); font-style: italic;">📷 No drop photo uploaded yet by driver for this order.</p>
                      </div>
                    `}
                    ${order.proof_of_delivery_signature ? `
                      <div style="background: var(--panel-bg); border: 1px solid var(--panel-border); border-radius: 12px; padding: 12px; width: 100%; display: flex; flex-direction: column; gap: 8px; align-items: center; box-sizing: border-box;">
                        <span style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 700;">✍️ Customer Signature:</span>
                        <a href="${getBaseUrl()}/${order.proof_of_delivery_signature}" target="_blank" title="Click to view signature">
                          <img src="${getBaseUrl()}/${order.proof_of_delivery_signature}" alt="Customer Signature" style="max-height: 80px; border-radius: 6px; border: 1px solid var(--panel-border); background: #fff; padding: 4px; display: block;">
                        </a>
                      </div>
                    ` : ''}
                  </div>
                </div>
              `;

                    const html = `
            <div style="display: flex; flex-direction: column; gap: 24px; font-family: var(--font-family); color: var(--text-primary);">
              <!-- Top Row (2 Cards): Customer & Order Details Grid -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                <!-- Card 1: Customer Details Card -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <h4 style="margin: 0 0 16px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">👤 Customer Information</h4>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Name: <strong style="color: var(--text-primary);">${escapeHtml(order.customer_name)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Phone: <strong style="color: var(--text-primary);">${escapeHtml(order.customer_phone)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Email: <strong style="color: var(--text-primary);">${escapeHtml(order.customer_email)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Delivery Address: <span style="color: var(--text-secondary); font-weight: 600;">${escapeHtml(order.customer_address)}</span></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Postcode / Area: <strong style="color: var(--primary-color);">${escapeHtml(order.customer_pincode)}</strong></p>
                </div>

                <!-- Card 2: Order Information Card -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                  <h4 style="margin: 0 0 16px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">📋 Order Metadata</h4>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Order ID: <strong style="color: var(--text-primary); font-size: 1rem;">${escapeHtml(order.id)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Date Placed: <strong style="color: var(--text-primary);">${escapeHtml(formatDateDMY(order.date))}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Total Amount: <strong style="color: #2ECC71; font-size: 1.15rem;">$${Number(order.amount).toFixed(2)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">Assigned Driver: <strong style="color: var(--text-primary);">${escapeHtml(order.driver_name)}</strong></p>
                  <p style="margin: 8px 0; font-size: 0.9rem; line-height: 1.5;">
                    Current Status:
                    <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_${(order.status || 'Pending').toLowerCase().replace(/ /g, '')}">${escapeHtml(order.status)}</span>
                  </p>
                </div>
              </div>

              <!-- Bottom Row (2 Cards): Tiffin Plan & Notes (Merged) and Proof of Delivery -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                <!-- Card 3: Tiffin & Addons + Notes Card (Merged) -->
                <div style="background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                  <h4 style="margin: 0 0 16px 0; color: var(--primary-color); font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px;">🍱 Subscription / Tiffin Plan</h4>
                  <p style="margin: 8px 0 10px 0; font-size: 0.95rem; line-height: 1.5;">Tiffin Plan: <strong style="color: var(--text-primary); font-size: 1.05rem;">${escapeHtml(order.tiffin_name)}</strong></p>
                  <p style="margin: 6px 0; font-size: 0.9rem; color: var(--text-secondary);">Ordered Quantity: <strong style="color: var(--text-primary); font-size: 1rem;">${escapeHtml(order.quantity)}</strong></p>
                  <p style="margin: 6px 0; font-size: 0.9rem; color: var(--text-secondary);">Plan Unit Price: <strong style="color: var(--text-primary);">$${Number(order.tiffin_price || 0).toFixed(2)}</strong> each</p>

                  ${customItemsHtml}

                  <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-top: 14px; margin-bottom: 8px; font-weight: 600;">Ordered Add-ons:</span>
                  ${addonsHtml}

                  <!-- Merged Preparation & Delivery Notes (Chef Note) -->
                  <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--panel-border);">
                    <span style="font-size: 0.85rem; color: var(--text-secondary); display: block; margin-bottom: 8px; font-weight: 600;">📝 Preparation & Delivery Notes:</span>
                    <div style="padding: 10px 14px; background: rgba(230, 126, 34, 0.08); border: 1px dashed rgba(230, 126, 34, 0.3); border-radius: 8px; color: #E67E22; font-size: 0.9rem; line-height: 1.5; font-style: italic; font-weight: 500;">
                      "${escapeHtml(order.note || 'No special notes provided.')}"
                    </div>
                  </div>
                </div>

                <!-- Card 4: Proof of Delivery Card -->
                ${podHtml}
              </div>
            </div>
          `;

                    if (activeContentSec) {
                        activeContentSec.innerHTML = html;
                    }

                } catch (err) {
                    if (activeContentSec) {
                        activeContentSec.innerHTML = `
              <div style="padding: 30px; text-align: center; color: #E74C3C; background-color: var(--bg-color); border: 1px solid var(--panel-border); border-radius: 12px;">
                <p style="font-weight: 600; margin: 0 0 10px 0; font-size: 1.05rem;">Error Loading Details</p>
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
                const addressCell = row.cells[3]?.textContent.toLowerCase() || '';

                if (nameCell.includes(filterText) || phoneCell.includes(filterText) || emailCell.includes(filterText) || addressCell.includes(filterText)) {
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

    // --- Client-side Customer Edit Form Concatenation ---
    const customerEditForm = getElement('customerEditForm');
    if (customerEditForm) {
        customerEditForm.addEventListener('submit', () => {
            const firstName = getElement('editCustomerFirstName')?.value.trim() || '';
            const lastName = getElement('editCustomerLastName')?.value.trim() || '';
            const nameInput = getElement('editCustomerName');
            if (nameInput) {
                nameInput.value = (firstName + ' ' + lastName).trim();
            }

            const street = getElement('editCustomerStreet')?.value.trim() || '';
            const suburb = getElement('editCustomerSuburb')?.value.trim() || '';
            const postcode = getElement('editCustomerPincode')?.value.trim() || '';
            const addressInput = getElement('editCustomerAddress');
            if (addressInput) {
                addressInput.value = `${street}, ${suburb}, ${postcode}`;
            }
        });
    }

    // --- Client-side Driver Edit Form Concatenation ---
    const inlineDriverEditForm = getElement('inlineDriverEditForm');
    if (inlineDriverEditForm) {
        inlineDriverEditForm.addEventListener('submit', () => {
            const firstName = getElement('editDriverFirstName')?.value.trim() || '';
            const lastName = getElement('editDriverLastName')?.value.trim() || '';
            const nameInput = getElement('editDriverName');
            if (nameInput) {
                nameInput.value = (firstName + ' ' + lastName).trim();
            }
        });
    }

    // --- Client-side Delivery/Order History Search ---
    const historySearchInput = getElement('historySearchInput');
    if (historySearchInput) {
        historySearchInput.addEventListener('input', () => {
            const filterText = historySearchInput.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#customerPaymentGridContent tbody tr.kp_kitchen_admin_panel_table_row');

            rows.forEach(row => {
                if (row.cells.length < 5) return; // Skip empty state row

                const orderId = row.cells[0]?.textContent.toLowerCase() || '';
                const date = row.cells[1]?.textContent.toLowerCase() || '';
                const tiffin = row.cells[2]?.textContent.toLowerCase() || '';
                const qty = row.cells[3]?.textContent.toLowerCase() || '';
                const addons = row.cells[4]?.textContent.toLowerCase() || '';
                const amount = row.cells[5]?.textContent.toLowerCase() || '';

                if (orderId.includes(filterText) || date.includes(filterText) || tiffin.includes(filterText) || qty.includes(filterText) || addons.includes(filterText) || amount.includes(filterText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // --- Batch & Single Driver Assignment with Confirmation Modal ---
    function initOrderBatchAssignment() {
        const modal = document.getElementById('driverAssignModal');
        const modalTitle = document.getElementById('driverAssignModalTitle');
        const modalPrompt = document.getElementById('driverAssignModalPrompt');
        const modalSubtext = document.getElementById('driverAssignModalSubtext');
        const modalList = document.getElementById('driverAssignModalList');
        const modalClose = document.getElementById('driverAssignModalClose');
        const cancelBtn = document.getElementById('driverAssignCancelBtn');
        const confirmBtn = document.getElementById('driverAssignConfirmBtn');

        const selectAllCheckbox = document.getElementById('selectAllOrdersCheckbox');
        const rowCheckboxes = document.querySelectorAll('.order-batch-checkbox');
        const driverSelects = document.querySelectorAll('.order-driver-select');

        if (!selectAllCheckbox && rowCheckboxes.length === 0 && driverSelects.length === 0) {
            return;
        }

        let pendingAssignments = [];
        let onCancelAction = null;

        function openDriverModal(title, prompt, subtext, assignments, confirmButtonText, onConfirm, onCancel) {
            if (!modal) return;

            modalTitle.textContent = title || 'Driver Assignment Confirmation';
            modalPrompt.textContent = prompt || 'Do you want to continue with the selected drivers?';
            modalSubtext.textContent = subtext || 'Please review the driver assignment details below before proceeding:';
            confirmBtn.textContent = confirmButtonText || 'Assign Drivers';
            confirmBtn.disabled = false;

            // Render table rows
            modalList.innerHTML = assignments.map(item => `
                <tr class="kp_kitchen_admin_panel_table_row">
                    <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                        <strong style="color: var(--primary-color);">${item.orderId}</strong>
                    </td>
                    <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                        <strong>${item.customer || '-'}</strong>
                    </td>
                    <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                        <span style="background: rgba(52, 152, 219, 0.15); color: #3498db; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">
                            ${item.area || '-'}
                        </span>
                    </td>
                    <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                        <strong style="color: ${item.driverName === 'Unassigned' ? '#e74c3c' : '#2ecc71'};">
                            ${item.driverName}
                        </strong>
                    </td>
                </tr>
            `).join('');

            pendingAssignments = assignments;
            onCancelAction = onCancel;

            confirmBtn.onclick = async () => {
                confirmBtn.disabled = true;
                const originalText = confirmBtn.textContent;
                confirmBtn.innerHTML = '<span class="kp_kitchen_admin_panel_spinner" style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:6px;"></span> Processing...';
                try {
                    await onConfirm();
                    closeDriverModal(false);
                } catch (err) {
                    console.error(err);
                    showToast('An error occurred during assignment.');
                } finally {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = originalText;
                }
            };

            modal.classList.add('kp_kitchen_admin_panel_modal_visible');
        }

        function closeDriverModal(triggerCancel = true) {
            if (!modal) return;
            modal.classList.remove('kp_kitchen_admin_panel_modal_visible');
            if (triggerCancel && typeof onCancelAction === 'function') {
                onCancelAction();
            }
            onCancelAction = null;
            pendingAssignments = [];
        }

        if (modalClose) modalClose.addEventListener('click', () => closeDriverModal(true));
        if (cancelBtn) cancelBtn.addEventListener('click', () => closeDriverModal(true));
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeDriverModal(true);
                }
            });
        }

        // Helper function to assign driver to single order or batch of orders via AJAX
        async function assignDriverApi(orderId, driverName) {
            const response = await fetch(`${getBaseUrl()}/api/orders`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    id: orderId,
                    driver: driverName
                })
            });

            return await response.json();
        }

        async function assignBatchDriversApi(batchEntries) {
            const response = await fetch(`${getBaseUrl()}/api/orders`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    batch: batchEntries
                })
            });

            return await response.json();
        }

        function updateSelectAllState() {
            if (!selectAllCheckbox) return;
            const enabledCheckboxes = Array.from(rowCheckboxes).filter(cb => !cb.disabled);
            if (enabledCheckboxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.disabled = true;
                return;
            }
            selectAllCheckbox.disabled = false;
            const allChecked = enabledCheckboxes.every(cb => cb.checked);
            const someChecked = enabledCheckboxes.some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = (!allChecked && someChecked);
        }

        updateSelectAllState();

        // Initialize driver select previous values
        driverSelects.forEach(sel => {
            sel.dataset.prevValue = sel.value;
            const selectedOpt = sel.options[sel.selectedIndex];
            sel.dataset.prevDriverName = selectedOpt ? (selectedOpt.getAttribute('data-driver-name') || 'Unassigned') : 'Unassigned';
        });

        // 1. Single Row Checkbox Click (Simply toggles selection without popup)
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                updateSelectAllState();
            });
        });

        // 2. Main (Select All) Checkbox Click (Toggles all row selections without popup)
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', () => {
                const isChecked = selectAllCheckbox.checked;
                rowCheckboxes.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = isChecked;
                    }
                });
                updateSelectAllState();
            });
        }

        // 3. Driver Dropdown Selection Changed (Triggers confirmation popup with selected driver)
        driverSelects.forEach(selectEl => {
            selectEl.addEventListener('change', () => {
                const row = selectEl.closest('tr');
                const orderId = row.getAttribute('data-order-id');
                const checkboxEl = row.querySelector('.order-batch-checkbox');
                const customerName = row.children[2]?.innerText?.trim() || '';
                const area = row.children[5]?.innerText?.trim() || '';
                const selectedOption = selectEl.options[selectEl.selectedIndex];
                const newDriverName = selectedOption ? (selectedOption.getAttribute('data-driver-name') || 'Unassigned') : 'Unassigned';
                const prevValue = selectEl.dataset.prevValue || '';
                const prevDriverName = selectEl.dataset.prevDriverName || 'Unassigned';

                if (newDriverName === prevDriverName) {
                    return; // No change made
                }

                // Check if multiple checkboxes are checked
                const checkedBoxes = Array.from(document.querySelectorAll('.order-batch-checkbox:checked'));
                const isBatch = checkedBoxes.length > 1;

                let targetList = [];

                if (isBatch) {
                    const targetRows = checkedBoxes.map(cb => cb.closest('tr')).filter(Boolean);
                    if (!targetRows.includes(row)) {
                        targetRows.push(row);
                    }

                    targetList = targetRows.map(r => {
                        const rOrderId = r.getAttribute('data-order-id');
                        const rSelectEl = r.querySelector('.order-driver-select');
                        const rCbEl = r.querySelector('.order-batch-checkbox');
                        const rCustomer = r.children[2]?.innerText?.trim() || '';
                        const rArea = r.children[5]?.innerText?.trim() || '';
                        return {
                            orderId: rOrderId,
                            customer: rCustomer,
                            area: rArea,
                            driverName: newDriverName,
                            selectEl: rSelectEl,
                            checkboxEl: rCbEl
                        };
                    });
                } else {
                    targetList = [{
                        orderId,
                        customer: customerName,
                        area,
                        driverName: newDriverName,
                        selectEl,
                        checkboxEl
                    }];
                }

                const isUnassigning = (newDriverName === 'Unassigned');

                let title = 'Driver Assignment Confirmation';
                let prompt = 'Do you want to continue with the selected drivers?';
                let subtext = `Assigning driver for order ${orderId}:`;
                let btnText = isUnassigning ? 'Unassign Driver' : 'Assign Driver';

                if (isBatch) {
                    title = isUnassigning ? 'Batch Driver Unassignment' : 'Batch Driver Assignment';
                    prompt = isUnassigning 
                        ? `Do you want to unassign drivers from all ${targetList.length} selected orders?`
                        : `Do you want to continue with the selected drivers?`;
                    subtext = isUnassigning 
                        ? `Unassigning drivers from ${targetList.length} selected orders:`
                        : `Assigning all ${targetList.length} orders to driver "${newDriverName}":`;
                    btnText = isUnassigning 
                        ? `Unassign All (${targetList.length} Orders)` 
                        : `Assign All (${targetList.length} Orders)`;
                } else if (isUnassigning) {
                    title = 'Unassign Driver Confirmation';
                    prompt = `Do you want to unassign driver from order ${orderId}?`;
                    subtext = `Order ${orderId} driver assignment update:`;
                }

                openDriverModal(
                    title,
                    prompt,
                    subtext,
                    targetList,
                    btnText,
                    async () => {
                        if (isBatch) {
                            const batchPayload = targetList.map(item => ({
                                id: item.orderId,
                                driver: item.driverName
                            }));

                            const res = await assignBatchDriversApi(batchPayload);
                            if (res.success) {
                                targetList.forEach(item => {
                                    if (item.selectEl) {
                                        // Set matching option by driver name
                                        const matchingOption = Array.from(item.selectEl.options).find(
                                            opt => (opt.getAttribute('data-driver-name') || 'Unassigned') === newDriverName
                                        );
                                        if (matchingOption) {
                                            item.selectEl.value = matchingOption.value;
                                        } else {
                                            item.selectEl.value = isUnassigning ? '' : item.selectEl.value;
                                        }
                                        item.selectEl.dataset.prevValue = item.selectEl.value;
                                        item.selectEl.dataset.prevDriverName = newDriverName;
                                    }
                                    if (item.checkboxEl) {
                                        item.checkboxEl.checked = !isUnassigning;
                                    }
                                    const r = item.selectEl ? item.selectEl.closest('tr') : null;
                                    if (r) {
                                        const hintEl = r.querySelector('.kp_kitchen_admin_panel_assignment_hint');
                                        if (hintEl) {
                                            hintEl.innerHTML = isUnassigning 
                                                ? 'Select any available driver'
                                                : `<span style="color: #2ecc71; font-weight: 600;">✓ Assigned: ${escapeHtml(newDriverName)}</span>`;
                                        }
                                    }
                                });
                                showToast(isUnassigning ? `Successfully unassigned ${targetList.length} orders.` : `Successfully assigned ${targetList.length} orders to ${newDriverName}.`);
                                updateSelectAllState();
                            } else {
                                targetList.forEach(item => {
                                    if (item.selectEl) item.selectEl.value = item.selectEl.dataset.prevValue || '';
                                });
                                showToast(res.message || 'Failed to update assignment.');
                            }
                        } else {
                            const res = await assignDriverApi(orderId, newDriverName);
                            if (res.success) {
                                selectEl.dataset.prevValue = selectEl.value;
                                selectEl.dataset.prevDriverName = newDriverName;
                                if (checkboxEl) {
                                    checkboxEl.checked = !isUnassigning;
                                }
                                const hintEl = row.querySelector('.kp_kitchen_admin_panel_assignment_hint');
                                if (hintEl) {
                                    hintEl.innerHTML = isUnassigning 
                                        ? 'Select any available driver'
                                        : `<span style="color: #2ecc71; font-weight: 600;">✓ Assigned: ${escapeHtml(newDriverName)}</span>`;
                                }
                                showToast(isUnassigning ? `Order ${orderId} is now unassigned.` : `Order ${orderId} successfully assigned to ${newDriverName}.`);
                                updateSelectAllState();
                            } else {
                                selectEl.value = prevValue;
                                showToast(res.message || 'Failed to update assignment.');
                            }
                        }
                    },
                    () => {
                        // Revert dropdown on cancel
                        selectEl.value = prevValue;
                    }
                );
            });
        });

        // 4. Bulk Driver Assignment Dropdown (in Top Toolbar)
        const bulkDriverSelect = document.getElementById('bulkDriverSelect');
        if (bulkDriverSelect) {
            bulkDriverSelect.addEventListener('change', () => {
                const selectedOption = bulkDriverSelect.options[bulkDriverSelect.selectedIndex];
                const newDriverName = selectedOption ? (selectedOption.getAttribute('data-driver-name') || selectedOption.value) : '';
                if (!newDriverName) return;

                const visibleRows = Array.from(document.querySelectorAll('#ordersTableBody tr.kp_kitchen_admin_panel_table_row'));
                if (visibleRows.length === 0) {
                    showToast('No orders found on this page to assign.');
                    bulkDriverSelect.selectedIndex = 0;
                    return;
                }

                // Gather checked rows or all visible rows if none are explicitly checked
                const checkedBoxes = Array.from(document.querySelectorAll('.order-batch-checkbox:checked'));
                let targetRows = [];
                if (checkedBoxes.length > 0) {
                    targetRows = checkedBoxes.map(cb => cb.closest('tr')).filter(Boolean);
                } else {
                    targetRows = visibleRows;
                }

                const targetList = targetRows.map(r => {
                    const rOrderId = r.getAttribute('data-order-id');
                    const rSelectEl = r.querySelector('.order-driver-select');
                    const rCbEl = r.querySelector('.order-batch-checkbox');
                    const rCustomer = r.children[2]?.innerText?.trim() || '';
                    const rArea = r.children[5]?.innerText?.trim() || '';
                    return {
                        orderId: rOrderId,
                        customer: rCustomer,
                        area: rArea,
                        driverName: newDriverName,
                        selectEl: rSelectEl,
                        checkboxEl: rCbEl
                    };
                });

                const isUnassigning = (newDriverName === 'Unassigned');
                const title = isUnassigning ? 'Batch Driver Unassignment' : 'Batch Driver Assignment';
                const prompt = isUnassigning 
                    ? `Do you want to unassign drivers from all ${targetList.length} selected orders?`
                    : `Do you want to continue with assigning driver "${newDriverName}" to ${targetList.length} orders?`;
                const subtext = isUnassigning 
                    ? `Unassigning drivers from ${targetList.length} selected orders:`
                    : `Assigning all ${targetList.length} orders to driver "${newDriverName}":`;
                const btnText = isUnassigning 
                    ? `Unassign All (${targetList.length} Orders)` 
                    : `Assign All (${targetList.length} Orders)`;

                openDriverModal(
                    title,
                    prompt,
                    subtext,
                    targetList,
                    btnText,
                    async () => {
                        const batchPayload = targetList.map(item => ({
                            id: item.orderId,
                            driver: item.driverName
                        }));

                        const res = await assignBatchDriversApi(batchPayload);
                        if (res.success) {
                            targetList.forEach(item => {
                                if (item.selectEl) {
                                    const matchingOption = Array.from(item.selectEl.options).find(
                                        opt => (opt.getAttribute('data-driver-name') || opt.text.trim()) === newDriverName
                                    );
                                    if (matchingOption) {
                                        item.selectEl.value = matchingOption.value;
                                    } else {
                                        item.selectEl.value = isUnassigning ? '' : item.selectEl.value;
                                    }
                                    item.selectEl.dataset.prevValue = item.selectEl.value;
                                    item.selectEl.dataset.prevDriverName = newDriverName;
                                }
                                if (item.checkboxEl) {
                                    item.checkboxEl.checked = !isUnassigning;
                                }
                                const r = item.selectEl ? item.selectEl.closest('tr') : null;
                                if (r) {
                                    const hintEl = r.querySelector('.kp_kitchen_admin_panel_assignment_hint');
                                    if (hintEl) {
                                        hintEl.innerHTML = isUnassigning 
                                            ? 'Select any available driver'
                                            : `<span style="color: #2ecc71; font-weight: 600;">✓ Assigned: ${escapeHtml(newDriverName)}</span>`;
                                    }
                                }
                            });
                            showToast(isUnassigning ? `Successfully unassigned ${targetList.length} orders.` : `Successfully assigned ${targetList.length} orders to ${newDriverName}.`);
                            bulkDriverSelect.selectedIndex = 0;
                            updateSelectAllState();
                        } else {
                            showToast(res.message || 'Failed to update assignment.');
                            bulkDriverSelect.selectedIndex = 0;
                        }
                    },
                    () => {
                        // Reset bulk selector on cancel
                        bulkDriverSelect.selectedIndex = 0;
                    }
                );
            });
        }

        // 5. "Orders Ready for Dispatch" Workflow
        const dispatchBtn = document.getElementById('dispatchOrdersBtn');
        const dispatchModal = document.getElementById('dispatchModal');
        const dispatchModalList = document.getElementById('dispatchModalList');
        const dispatchModalClose = document.getElementById('dispatchModalClose');
        const dispatchCancelBtn = document.getElementById('dispatchCancelBtn');
        const dispatchConfirmBtn = document.getElementById('dispatchConfirmBtn');
        const dispatchModalPrompt = document.getElementById('dispatchModalPrompt');

        if (dispatchBtn && dispatchModal) {
            function closeDispatchModal() {
                dispatchModal.classList.remove('kp_kitchen_admin_panel_modal_visible');
            }

            if (dispatchModalClose) dispatchModalClose.addEventListener('click', closeDispatchModal);
            if (dispatchCancelBtn) dispatchCancelBtn.addEventListener('click', closeDispatchModal);
            dispatchModal.addEventListener('click', (e) => {
                if (e.target === dispatchModal) closeDispatchModal();
            });

            dispatchBtn.addEventListener('click', () => {
                // Collect orders ready for dispatch
                const rows = document.querySelectorAll('#ordersTableBody tr.kp_kitchen_admin_panel_table_row');
                const checkedBoxes = Array.from(document.querySelectorAll('.order-batch-checkbox:checked'));

                let targetRows = [];

                if (checkedBoxes.length > 0) {
                    // If specific checkboxes are checked, use checked rows with assigned drivers
                    targetRows = checkedBoxes.map(cb => cb.closest('tr')).filter(Boolean);
                } else {
                    // Otherwise, gather all rows that have an assigned driver
                    targetRows = Array.from(rows);
                }

                const dispatchableOrders = [];
                targetRows.forEach(row => {
                    const orderId = row.getAttribute('data-order-id');
                    const selectEl = row.querySelector('.order-driver-select');
                    const customerName = row.children[2]?.innerText?.trim() || '';
                    const area = row.children[5]?.innerText?.trim() || '';
                    const selectedOption = selectEl ? selectEl.options[selectEl.selectedIndex] : null;
                    const driverName = selectedOption ? (selectedOption.getAttribute('data-driver-name') || 'Unassigned') : 'Unassigned';

                    if (orderId && driverName !== 'Unassigned') {
                        dispatchableOrders.push({
                            orderId,
                            customer: customerName,
                            area,
                            driverName
                        });
                    }
                });

                if (dispatchableOrders.length === 0) {
                    showToast('Please assign drivers to orders before clicking Ready for Dispatch.');
                    return;
                }

                if (dispatchModalPrompt) {
                    dispatchModalPrompt.textContent = `Are you ready to dispatch ${dispatchableOrders.length} assigned ${dispatchableOrders.length === 1 ? 'order' : 'orders'} now?`;
                }

                if (dispatchModalList) {
                    dispatchModalList.innerHTML = dispatchableOrders.map(item => `
                        <tr class="kp_kitchen_admin_panel_table_row">
                            <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                                <strong style="color: var(--primary-color);">${escapeHtml(item.orderId)}</strong>
                            </td>
                            <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                                <strong>${escapeHtml(item.customer || '-')}</strong>
                            </td>
                            <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                                <span style="background: rgba(52, 152, 219, 0.15); color: #3498db; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">
                                    ${escapeHtml(item.area || '-')}
                                </span>
                            </td>
                            <td class="kp_kitchen_admin_panel_table_cell" style="padding: 8px 12px;">
                                <strong style="color: #2ecc71;">
                                    🚚 ${escapeHtml(item.driverName)}
                                </strong>
                            </td>
                        </tr>
                    `).join('');
                }

                dispatchConfirmBtn.onclick = async () => {
                    dispatchConfirmBtn.disabled = true;
                    const origText = dispatchConfirmBtn.innerHTML;
                    dispatchConfirmBtn.innerHTML = '<span class="kp_kitchen_admin_panel_spinner" style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:6px;"></span> Dispatching...';

                    try {
                        const response = await fetch(`${getBaseUrl()}/api/orders/dispatch`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                order_ids: dispatchableOrders.map(o => o.orderId)
                            })
                        });

                        const res = await response.json();
                        if (res.success) {
                            showToast(res.message || `Successfully dispatched ${dispatchableOrders.length} orders! Drivers have been notified.`);
                            closeDispatchModal();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            showToast(res.message || 'Failed to dispatch orders.');
                        }
                    } catch (err) {
                        console.error(err);
                        showToast('An error occurred during dispatch.');
                    } finally {
                        dispatchConfirmBtn.disabled = false;
                        dispatchConfirmBtn.innerHTML = origText;
                    }
                };

                dispatchModal.classList.add('kp_kitchen_admin_panel_modal_visible');
            });
        }
    }

    // --- Initialise ---
    initSearchQuery();
    loadStateData();
    initCollapsibleSidebar();
    initOrderBatchAssignment();
})();
