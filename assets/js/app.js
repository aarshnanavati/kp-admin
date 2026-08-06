(function () {
  const getElement = id => document.getElementById(id);
  const currentAdmin = JSON.parse(localStorage.getItem('kpKitchenCurrentAdmin') || 'null');
  if (!currentAdmin) { window.location.href = 'login.html'; return; }

  const initialData = {
    drivers: [
      { id: 1, name: 'Rakesh Patel', phone: '9876543210', area: 'Satellite', status: 'Active' },
      { id: 2, name: 'Amit Shah', phone: '9825012345', area: 'Navrangpura', status: 'Active' },
      { id: 3, name: 'Vijay Solanki', phone: '9909011122', area: 'Bopal', status: 'Inactive' }
    ],
    tiffins: [
      { id: 1, name: 'Regular Veg Tiffin', type: 'Lunch', price: 120, items: '4 Roti, Seasonal Sabji, Dal, Rice, Salad', description: 'Balanced everyday Gujarati meal.', prepTime: 30, status: 'Active', image: '' },
      { id: 2, name: 'Premium Tiffin', type: 'Dinner', price: 180, items: '5 Roti, 2 Sabji, Dal, Rice, Sweet', description: 'A larger meal with sweet and two vegetables.', prepTime: 40, status: 'Active', image: '' },
      { id: 3, name: 'Mini Tiffin', type: 'Lunch', price: 90, items: '3 Roti, Sabji, Dal Rice', description: 'Compact meal for a lighter appetite.', prepTime: 25, status: 'Active', image: '' }
    ],
    orders: [
      { id: 'KP1001', customer: 'Neha Mehta', tiffin: 'Regular Veg Tiffin', area: 'Satellite', driver: 'Rakesh Patel', amount: 120, status: 'Pending', date: '2026-08-06' },
      { id: 'KP1002', customer: 'Rahul Desai', tiffin: 'Premium Tiffin', area: 'Navrangpura', driver: 'Amit Shah', amount: 180, status: 'Preparing', date: '2026-08-06' },
      { id: 'KP1003', customer: 'Pooja Joshi', tiffin: 'Mini Tiffin', area: 'Bopal', driver: 'Unassigned', amount: 90, status: 'Confirmed', date: '2026-08-06' },
      { id: 'KP1004', customer: 'Dev Patel', tiffin: 'Regular Veg Tiffin', area: 'Satellite', driver: 'Rakesh Patel', amount: 120, status: 'Delivered', date: '2026-08-05' }
    ],
    payments: [
      { id: 'TXN9001', customer: 'Neha Mehta', plan: 'Weekly Plan', amount: 840, date: '2026-08-05', status: 'Successful' },
      { id: 'TXN9002', customer: 'Rahul Desai', plan: 'Monthly Plan', amount: 4680, date: '2026-08-05', status: 'Successful' },
      { id: 'TXN9003', customer: 'Pooja Joshi', plan: 'Weekly Plan', amount: 630, date: '2026-08-05', status: 'Failed' }
    ],
    notifications: [
      { id: 1, title: 'New order received', message: 'Neha Mehta placed order KP1001.', time: '5 minutes ago', read: false },
      { id: 2, title: 'New order received', message: 'Rahul Desai placed order KP1002.', time: '18 minutes ago', read: false },
      { id: 3, title: 'Payment failed', message: 'Automatic deduction failed for Pooja Joshi.', time: '1 hour ago', read: false },
      { id: 4, title: 'Order delivered', message: 'Order KP1004 was marked as delivered.', time: 'Yesterday', read: true }
    ]
  };

  const load = key => JSON.parse(localStorage.getItem(`kpKitchen${key}`) || 'null');
  const save = (key, value) => localStorage.setItem(`kpKitchen${key}`, JSON.stringify(value));
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  const formatCurrency = value => `₹${Number(value).toLocaleString('en-IN')}`;
  const statusClass = status => `kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_${String(status).toLowerCase().replaceAll(' ', '_')}`;
  const areaKey = area => String(area || '').trim().toLowerCase();

  let drivers = load('Drivers') || initialData.drivers;
  let tiffins = load('Tiffins') || initialData.tiffins;
  let orders = load('Orders') || initialData.orders;
  let payments = load('Payments') || initialData.payments;
  let notifications = load('Notifications') || initialData.notifications;
  tiffins = tiffins.map(item => ({ description: '', prepTime: 30, image: '', ...item }));
  save('Drivers', drivers); save('Tiffins', tiffins); save('Orders', orders); save('Payments', payments); save('Notifications', notifications);

  getElement('adminName').textContent = currentAdmin.name;
  document.querySelector('.kp_kitchen_admin_panel_admin_avatar').textContent = currentAdmin.name.charAt(0).toUpperCase();

  const applyTheme = theme => {
    document.documentElement.setAttribute('data-kp-theme', theme);
    localStorage.setItem('kpKitchenTheme', theme);
    const button = getElement('themeToggle');
    if (button) { button.textContent = theme === 'dark' ? '☀️' : '🌙'; button.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'; }
  };
  applyTheme(localStorage.getItem('kpKitchenTheme') || 'light');
  if (getElement('themeToggle')) getElement('themeToggle').addEventListener('click', () => applyTheme(document.documentElement.getAttribute('data-kp-theme') === 'dark' ? 'light' : 'dark'));

  const sidebar = getElement('sidebar');
  const overlay = getElement('sidebarOverlay');
  const closeSidebar = () => { sidebar.classList.remove('kp_kitchen_admin_panel_sidebar_open'); overlay.classList.remove('kp_kitchen_admin_panel_sidebar_overlay_visible'); };
  getElement('sidebarToggle').addEventListener('click', () => { sidebar.classList.add('kp_kitchen_admin_panel_sidebar_open'); overlay.classList.add('kp_kitchen_admin_panel_sidebar_overlay_visible'); });
  getElement('sidebarClose').addEventListener('click', closeSidebar);
  overlay.addEventListener('click', closeSidebar);

  function showToast(message) {
    const toast = getElement('toast');
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

  function renderDashboard() {
    if (!getElement('statOrders')) return;
    getElement('statOrders').textContent = orders.length;
    getElement('statDrivers').textContent = drivers.filter(item => item.status === 'Active').length;
    getElement('statTiffins').textContent = tiffins.filter(item => item.status === 'Active').length;
    getElement('statRevenue').textContent = formatCurrency(payments.filter(item => item.status === 'Successful').reduce((sum, item) => sum + item.amount, 0));
    getElement('dashboardOrdersBody').innerHTML = orders.slice(0, 5).map(order => `<tr class="kp_kitchen_admin_panel_table_row"><td class="kp_kitchen_admin_panel_table_cell"><strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong></td><td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.customer)}</td><td class="kp_kitchen_admin_panel_table_cell">${formatCurrency(order.amount)}</td><td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(order.status)}">${escapeHtml(order.status)}</span></td></tr>`).join('');
    const statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered'];
    getElement('deliverySummary').innerHTML = statuses.map(status => { const count = orders.filter(item => item.status === status).length; const percent = orders.length ? Math.round(count / orders.length * 100) : 0; return `<div class="kp_kitchen_admin_panel_progress_item"><div class="kp_kitchen_admin_panel_progress_header"><span class="kp_kitchen_admin_panel_progress_label">${status}</span><strong class="kp_kitchen_admin_panel_progress_value">${count}</strong></div><div class="kp_kitchen_admin_panel_progress_track"><span class="kp_kitchen_admin_panel_progress_fill" style="width:${percent}%"></span></div></div>`; }).join('');
  }

  function renderDrivers() {
    if (!getElement('driversGrid')) return;
    getElement('driversGrid').innerHTML = drivers.map(driver => `<article class="kp_kitchen_admin_panel_driver_card"><div class="kp_kitchen_admin_panel_driver_top"><div class="kp_kitchen_admin_panel_driver_avatar">${escapeHtml(driver.name.charAt(0))}</div><span class="${statusClass(driver.status)}">${escapeHtml(driver.status)}</span></div><h3 class="kp_kitchen_admin_panel_driver_name">${escapeHtml(driver.name)}</h3><p class="kp_kitchen_admin_panel_driver_meta">📞 ${escapeHtml(driver.phone)}</p><p class="kp_kitchen_admin_panel_driver_meta">📍 Assigned area: <strong>${escapeHtml(driver.area)}</strong></p><p class="kp_kitchen_admin_panel_driver_meta">📦 ${orders.filter(order => order.driver === driver.name && order.status !== 'Delivered').length} active deliveries</p><div class="kp_kitchen_admin_panel_card_actions"><button class="kp_kitchen_admin_panel_small_button" data-edit-driver="${driver.id}">Edit / Assign Area</button><button class="kp_kitchen_admin_panel_danger_button" data-delete-driver="${driver.id}">Delete</button></div></article>`).join('');
  }

  function renderTiffins() {
    if (!getElement('tiffinsGrid')) return;
    getElement('tiffinsGrid').innerHTML = tiffins.map(tiffin => {
      const image = tiffin.image ? `<img class="kp_kitchen_admin_panel_tiffin_photo" src="${escapeHtml(tiffin.image)}" alt="${escapeHtml(tiffin.name)}">` : '<span class="kp_kitchen_admin_panel_tiffin_emoji">🍱</span>';
      return `<article class="kp_kitchen_admin_panel_tiffin_card"><div class="kp_kitchen_admin_panel_tiffin_image">${image}<span class="${statusClass(tiffin.status)}">${escapeHtml(tiffin.status)}</span></div><div class="kp_kitchen_admin_panel_tiffin_content"><span class="kp_kitchen_admin_panel_tiffin_type">${escapeHtml(tiffin.type)} • ${escapeHtml(tiffin.prepTime)} min</span><h3 class="kp_kitchen_admin_panel_tiffin_name">${escapeHtml(tiffin.name)}</h3><p class="kp_kitchen_admin_panel_tiffin_description">${escapeHtml(tiffin.description)}</p><div class="kp_kitchen_admin_panel_tiffin_item_chips">${String(tiffin.items).split(/,|\n/).filter(Boolean).map(item => `<span class="kp_kitchen_admin_panel_tiffin_item_chip">${escapeHtml(item.trim())}</span>`).join('')}</div><div class="kp_kitchen_admin_panel_tiffin_footer"><strong class="kp_kitchen_admin_panel_tiffin_price">${formatCurrency(tiffin.price)}</strong><div class="kp_kitchen_admin_panel_card_actions"><button class="kp_kitchen_admin_panel_icon_button" data-edit-tiffin="${tiffin.id}">Edit Full Tiffin</button><button class="kp_kitchen_admin_panel_danger_button" data-delete-tiffin="${tiffin.id}">Delete</button></div></div></div></article>`;
    }).join('');
  }

  function renderOrders(filter = 'all') {
    if (!getElement('ordersTableBody')) return;
    const areaFilter = getElement('orderAreaFilter')?.value || 'all';
    const filtered = orders.filter(order => (filter === 'all' || order.status === filter) && (areaFilter === 'all' || order.area === areaFilter));
    const statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];
    getElement('ordersTableBody').innerHTML = filtered.map(order => {
      const matchingDrivers = drivers.filter(driver => driver.status === 'Active' && areaKey(driver.area) === areaKey(order.area));
      const selectedDriverValid = matchingDrivers.some(driver => driver.name === order.driver);
      const driverOptions = matchingDrivers.map(driver => `<option value="${escapeHtml(driver.name)}" ${driver.name === order.driver ? 'selected' : ''}>${escapeHtml(driver.name)} — ${escapeHtml(driver.area)}</option>`).join('');
      const noDriver = matchingDrivers.length === 0;
      return `<tr class="kp_kitchen_admin_panel_table_row"><td class="kp_kitchen_admin_panel_table_cell"><strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(order.id)}</strong><span class="kp_kitchen_admin_panel_table_secondary">${escapeHtml(order.date)}</span></td><td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.customer)}</td><td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(order.tiffin)}</td><td class="kp_kitchen_admin_panel_table_cell"><strong>${escapeHtml(order.area)}</strong></td><td class="kp_kitchen_admin_panel_table_cell"><select class="kp_kitchen_admin_panel_inline_select ${noDriver ? 'kp_kitchen_admin_panel_inline_select_warning' : ''}" data-order-driver="${order.id}" ${noDriver ? 'disabled' : ''}><option value="Unassigned" ${!selectedDriverValid ? 'selected' : ''}>${noDriver ? `No active driver for ${escapeHtml(order.area)}` : 'Unassigned'}</option>${driverOptions}</select><span class="kp_kitchen_admin_panel_assignment_hint">${noDriver ? 'Assign this area to an active driver first.' : `Area-matched drivers only`}</span></td><td class="kp_kitchen_admin_panel_table_cell">${formatCurrency(order.amount)}</td><td class="kp_kitchen_admin_panel_table_cell"><select class="kp_kitchen_admin_panel_inline_select" data-order-status="${order.id}">${statuses.map(status => `<option ${status === order.status ? 'selected' : ''}>${status}</option>`).join('')}</select></td></tr>`;
    }).join('') || '<tr><td colspan="7" class="kp_kitchen_admin_panel_table_cell"><div class="kp_kitchen_admin_panel_empty_state">No orders match these filters.</div></td></tr>';
  }

  function renderPayments() {
    if (!getElement('paymentsTableBody')) return;
    getElement('paymentsTableBody').innerHTML = payments.map(payment => `<tr class="kp_kitchen_admin_panel_table_row"><td class="kp_kitchen_admin_panel_table_cell"><strong class="kp_kitchen_admin_panel_table_primary">${escapeHtml(payment.id)}</strong></td><td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(payment.customer)}</td><td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(payment.plan)}</td><td class="kp_kitchen_admin_panel_table_cell">${formatCurrency(payment.amount)}</td><td class="kp_kitchen_admin_panel_table_cell">${escapeHtml(payment.date)}</td><td class="kp_kitchen_admin_panel_table_cell"><span class="${statusClass(payment.status)}">${escapeHtml(payment.status)}</span></td></tr>`).join('');
    if (getElement('paymentTotal')) getElement('paymentTotal').textContent = formatCurrency(payments.filter(item => item.status === 'Successful').reduce((sum, item) => sum + item.amount, 0));
    if (getElement('paymentSuccessful')) getElement('paymentSuccessful').textContent = payments.filter(item => item.status === 'Successful').length;
    if (getElement('paymentFailed')) getElement('paymentFailed').textContent = payments.filter(item => item.status === 'Failed').length;
  }

  function renderNotifications() {
    if (!getElement('notificationsList')) return;
    getElement('notificationsList').innerHTML = notifications.length ? notifications.map(item => `<article class="kp_kitchen_admin_panel_notification_item ${item.read ? '' : 'kp_kitchen_admin_panel_notification_item_unread'}"><div class="kp_kitchen_admin_panel_notification_icon">${item.title.includes('Payment') ? '₹' : '🔔'}</div><div class="kp_kitchen_admin_panel_notification_content"><div class="kp_kitchen_admin_panel_notification_heading"><strong class="kp_kitchen_admin_panel_notification_title">${escapeHtml(item.title)}</strong><span class="kp_kitchen_admin_panel_notification_time">${escapeHtml(item.time)}</span></div><p class="kp_kitchen_admin_panel_notification_message">${escapeHtml(item.message)}</p></div><button class="kp_kitchen_admin_panel_notification_read_button" data-read-notification="${item.id}">${item.read ? 'Read' : 'Mark read'}</button></article>`).join('') : '<div class="kp_kitchen_admin_panel_empty_state">No notifications available.</div>';
  }

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
  getElement('modalClose').addEventListener('click', closeModal);
  modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });

  const driverFields = driver => `<label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Driver name</span><input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(driver?.name || '')}" required></label><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Phone number</span><input name="phone" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(driver?.phone || '')}" required></label><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Assigned delivery area</span><input name="area" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(driver?.area || '')}" placeholder="Example: Satellite" required><small class="kp_kitchen_admin_panel_form_help">Orders are offered only when their area exactly matches this area.</small></label><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Status</span><select name="status" class="kp_kitchen_admin_panel_form_select"><option ${driver?.status === 'Active' ? 'selected' : ''}>Active</option><option ${driver?.status === 'Inactive' ? 'selected' : ''}>Inactive</option></select></label>`;

  const tiffinFields = tiffin => `<label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Main tiffin image</span><input id="tiffinImageInput" name="imageFile" class="kp_kitchen_admin_panel_form_input" type="file" accept="image/*"><input id="tiffinImageData" name="image" type="hidden" value="${escapeHtml(tiffin?.image || '')}"><div id="tiffinImagePreview" class="kp_kitchen_admin_panel_image_preview">${tiffin?.image ? `<img src="${escapeHtml(tiffin.image)}" alt="Preview">` : '<span>Image preview</span>'}</div><small class="kp_kitchen_admin_panel_form_help">For this front-end demo, the selected image is saved in browser storage.</small></label><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Tiffin name</span><input name="name" class="kp_kitchen_admin_panel_form_input" value="${escapeHtml(tiffin?.name || '')}" required></label><div class="kp_kitchen_admin_panel_form_grid"><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Meal type</span><select name="type" class="kp_kitchen_admin_panel_form_select"><option ${tiffin?.type === 'Lunch' ? 'selected' : ''}>Lunch</option><option ${tiffin?.type === 'Dinner' ? 'selected' : ''}>Dinner</option><option ${tiffin?.type === 'Both' ? 'selected' : ''}>Both</option></select></label><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Price (₹)</span><input name="price" class="kp_kitchen_admin_panel_form_input" type="number" min="1" value="${tiffin?.price || ''}" required></label></div><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Item names</span><textarea name="items" class="kp_kitchen_admin_panel_form_textarea" placeholder="Roti, Sabji, Dal, Rice, Salad" required>${escapeHtml(tiffin?.items || '')}</textarea></label><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Description</span><textarea name="description" class="kp_kitchen_admin_panel_form_textarea" placeholder="Describe this tiffin">${escapeHtml(tiffin?.description || '')}</textarea></label><div class="kp_kitchen_admin_panel_form_grid"><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Preparation time (minutes)</span><input name="prepTime" class="kp_kitchen_admin_panel_form_input" type="number" min="1" value="${tiffin?.prepTime || 30}" required></label><label class="kp_kitchen_admin_panel_form_group"><span class="kp_kitchen_admin_panel_form_label">Status</span><select name="status" class="kp_kitchen_admin_panel_form_select"><option ${tiffin?.status === 'Active' ? 'selected' : ''}>Active</option><option ${tiffin?.status === 'Inactive' ? 'selected' : ''}>Inactive</option></select></label></div>`;

  function setupImagePreview() {
    const input = getElement('tiffinImageInput');
    input.addEventListener('change', () => {
      const file = input.files[0]; if (!file) return;
      if (file.size > 1.5 * 1024 * 1024) { input.value = ''; showToast('Please select an image smaller than 1.5 MB.'); return; }
      const reader = new FileReader();
      reader.onload = () => { getElement('tiffinImageData').value = reader.result; getElement('tiffinImagePreview').innerHTML = `<img src="${reader.result}" alt="Preview">`; };
      reader.readAsDataURL(file);
    });
  }

  if (getElement('addDriverButton')) getElement('addDriverButton').addEventListener('click', () => openModal('Add Driver & Assign Area', driverFields(), 'Add Driver', data => { drivers.push({ id: Date.now(), name: data.get('name').trim(), phone: data.get('phone').trim(), area: data.get('area').trim(), status: data.get('status') }); save('Drivers', drivers); renderAll(); closeModal(); showToast('Driver added and area assigned.'); }));
  if (getElement('addTiffinButton')) getElement('addTiffinButton').addEventListener('click', () => openModal('Add Complete Tiffin', tiffinFields(), 'Add Tiffin', data => { tiffins.push({ id: Date.now(), name: data.get('name').trim(), type: data.get('type'), price: Number(data.get('price')), items: data.get('items').trim(), description: data.get('description').trim(), prepTime: Number(data.get('prepTime')), status: data.get('status'), image: data.get('image') }); save('Tiffins', tiffins); renderAll(); closeModal(); showToast('Tiffin added successfully.'); }, setupImagePreview));

  document.addEventListener('click', event => {
    const target = event.target;
    if (target.dataset.editDriver) {
      const driver = drivers.find(item => item.id === Number(target.dataset.editDriver));
      openModal('Edit Driver & Delivery Area', driverFields(driver), 'Save Changes', data => {
        const oldName = driver.name;
        Object.assign(driver, { name: data.get('name').trim(), phone: data.get('phone').trim(), area: data.get('area').trim(), status: data.get('status') });
        orders.forEach(order => { if (order.driver === oldName && (areaKey(order.area) !== areaKey(driver.area) || driver.status !== 'Active')) order.driver = 'Unassigned'; else if (order.driver === oldName) order.driver = driver.name; });
        save('Drivers', drivers); save('Orders', orders); renderAll(); closeModal(); showToast('Driver and assigned area updated.');
      });
    }
    if (target.dataset.deleteDriver && confirm('Delete this driver? Assigned orders will become unassigned.')) { const driver = drivers.find(item => item.id === Number(target.dataset.deleteDriver)); orders.forEach(order => { if (order.driver === driver.name) order.driver = 'Unassigned'; }); drivers = drivers.filter(item => item.id !== driver.id); save('Drivers', drivers); save('Orders', orders); renderAll(); showToast('Driver deleted.'); }
    if (target.dataset.editTiffin) {
      const tiffin = tiffins.find(item => item.id === Number(target.dataset.editTiffin));
      openModal('Edit Complete Tiffin', tiffinFields(tiffin), 'Save Changes', data => { Object.assign(tiffin, { name: data.get('name').trim(), type: data.get('type'), price: Number(data.get('price')), items: data.get('items').trim(), description: data.get('description').trim(), prepTime: Number(data.get('prepTime')), status: data.get('status'), image: data.get('image') }); save('Tiffins', tiffins); renderAll(); closeModal(); showToast('Complete tiffin details updated.'); }, setupImagePreview);
    }
    if (target.dataset.deleteTiffin && confirm('Delete this tiffin?')) { tiffins = tiffins.filter(item => item.id !== Number(target.dataset.deleteTiffin)); save('Tiffins', tiffins); renderAll(); showToast('Tiffin deleted.'); }
    if (target.dataset.readNotification) { const item = notifications.find(notification => notification.id === Number(target.dataset.readNotification)); item.read = true; save('Notifications', notifications); renderAll(); }
  });

  document.addEventListener('change', event => {
    if (event.target.dataset.orderStatus) { const order = orders.find(item => item.id === event.target.dataset.orderStatus); order.status = event.target.value; save('Orders', orders); renderAll(); showToast('Order status updated.'); }
    if (event.target.dataset.orderDriver) { const order = orders.find(item => item.id === event.target.dataset.orderDriver); const driver = drivers.find(item => item.name === event.target.value); if (event.target.value !== 'Unassigned' && (!driver || areaKey(driver.area) !== areaKey(order.area) || driver.status !== 'Active')) { renderAll(); showToast('This driver is not active in the order area.'); return; } order.driver = event.target.value; if (order.driver !== 'Unassigned' && ['Pending', 'Confirmed', 'Preparing'].includes(order.status)) order.status = 'Out for Delivery'; notifications.unshift({ id: Date.now(), title: 'Delivery assigned', message: `${order.id} assigned to ${order.driver} for ${order.area}.`, time: 'Just now', read: false }); save('Orders', orders); save('Notifications', notifications); renderAll(); showToast(`Order assigned according to ${order.area} area.`); }
  });

  const populateAreaFilter = () => { const select = getElement('orderAreaFilter'); if (!select) return; const selected = select.value; const areas = [...new Set(orders.map(order => order.area))].sort(); select.innerHTML = '<option value="all">All areas</option>' + areas.map(area => `<option value="${escapeHtml(area)}">${escapeHtml(area)}</option>`).join(''); select.value = areas.includes(selected) ? selected : 'all'; };
  if (getElement('orderStatusFilter')) getElement('orderStatusFilter').addEventListener('change', () => renderOrders(getElement('orderStatusFilter').value));
  if (getElement('orderAreaFilter')) getElement('orderAreaFilter').addEventListener('change', () => renderOrders(getElement('orderStatusFilter').value));
  if (getElement('markAllReadButton')) getElement('markAllReadButton').addEventListener('click', () => { notifications = notifications.map(item => ({ ...item, read: true })); save('Notifications', notifications); renderAll(); showToast('All notifications marked as read.'); });
  if (getElement('runDeductionButton')) getElement('runDeductionButton').addEventListener('click', () => { const id = `TXN${Math.floor(1000 + Math.random() * 9000)}`; payments.unshift({ id, customer: 'Demo Customer', plan: 'Weekly Plan', amount: 840, date: new Date().toISOString().slice(0, 10), status: 'Successful' }); notifications.unshift({ id: Date.now(), title: 'Payment deducted', message: `${id} completed successfully for Demo Customer.`, time: 'Just now', read: false }); save('Payments', payments); save('Notifications', notifications); renderAll(); showToast('Automatic deduction completed.'); });

  getElement('globalSearch').addEventListener('keydown', event => { if (event.key !== 'Enter') return; const term = event.target.value.trim().toLowerCase(); if (!term) return; if (orders.some(item => Object.values(item).some(value => String(value).toLowerCase().includes(term)))) window.location.href = `orders.html?search=${encodeURIComponent(term)}`; else if (drivers.some(item => Object.values(item).some(value => String(value).toLowerCase().includes(term)))) window.location.href = `drivers.html?search=${encodeURIComponent(term)}`; else if (tiffins.some(item => Object.values(item).some(value => String(value).toLowerCase().includes(term)))) window.location.href = `tiffins.html?search=${encodeURIComponent(term)}`; else showToast('No matching record found.'); });
  getElement('logoutButton').addEventListener('click', () => { localStorage.removeItem('kpKitchenCurrentAdmin'); window.location.href = 'login.html'; });

  function renderAll() { populateAreaFilter(); renderDashboard(); renderDrivers(); renderTiffins(); renderOrders(getElement('orderStatusFilter')?.value || 'all'); renderPayments(); renderNotifications(); updateBadges(); }
  renderAll();
})();
