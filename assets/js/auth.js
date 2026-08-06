(function () {
  const defaultAdmin = { name: 'KP Kitchen Admin', email: 'admin@kpkitchen.com', password: 'admin123' };
  if (!localStorage.getItem('kpKitchenAdmins')) {
    localStorage.setItem('kpKitchenAdmins', JSON.stringify([defaultAdmin]));
  }

  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (event) {
      event.preventDefault();
      const email = document.getElementById('loginEmail').value.trim().toLowerCase();
      const password = document.getElementById('loginPassword').value;
      const message = document.getElementById('loginMessage');
      const admins = JSON.parse(localStorage.getItem('kpKitchenAdmins') || '[]');
      const admin = admins.find(item => item.email.toLowerCase() === email && item.password === password);
      if (!admin) {
        message.textContent = 'Invalid email or password.';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        return;
      }
      localStorage.setItem('kpKitchenCurrentAdmin', JSON.stringify({ name: admin.name, email: admin.email }));
      window.location.href = 'index.html';
    });
  }

  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', function (event) {
      event.preventDefault();
      const name = document.getElementById('registerName').value.trim();
      const email = document.getElementById('registerEmail').value.trim().toLowerCase();
      const password = document.getElementById('registerPassword').value;
      const confirmPassword = document.getElementById('registerConfirmPassword').value;
      const message = document.getElementById('registerMessage');
      const admins = JSON.parse(localStorage.getItem('kpKitchenAdmins') || '[]');
      if (password !== confirmPassword) {
        message.textContent = 'Passwords do not match.';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        return;
      }
      if (admins.some(item => item.email.toLowerCase() === email)) {
        message.textContent = 'An admin with this email already exists.';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        return;
      }
      admins.push({ name, email, password });
      localStorage.setItem('kpKitchenAdmins', JSON.stringify(admins));
      message.textContent = 'Registration successful. Redirecting to login...';
      message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_success';
      setTimeout(() => { window.location.href = 'login.html'; }, 800);
    });
  }
})();
