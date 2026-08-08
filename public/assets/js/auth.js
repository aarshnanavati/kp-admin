(function () {
  const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      const remember = document.getElementById('loginRemember')?.checked || false;
      const message = document.getElementById('loginMessage');

      try {
        const response = await fetch('api/auth/login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
          },
          body: JSON.stringify({ email, password, remember })
        });
        const result = await response.json();

        if (!result.success) {
          message.textContent = result.message || 'Invalid email or password.';
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
          return;
        }

        message.textContent = 'Login successful. Redirecting...';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_success';
        
        setTimeout(() => { window.location.href = './'; }, 500);
      } catch (err) {
        message.textContent = 'An error occurred. Please try again.';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
      }
    });
  }

  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const name = document.getElementById('registerName').value.trim();
      const email = document.getElementById('registerEmail').value.trim();
      const password = document.getElementById('registerPassword').value;
      const confirmPassword = document.getElementById('registerConfirmPassword').value;
      const message = document.getElementById('registerMessage');

      if (password !== confirmPassword) {
        message.textContent = 'Passwords do not match.';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        return;
      }

      try {
        const response = await fetch('api/auth/register', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
          },
          body: JSON.stringify({ name, email, password, confirm_password: confirmPassword })
        });
        const result = await response.json();

        if (!result.success) {
          message.textContent = result.message || 'Registration failed.';
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
          return;
        }

        message.textContent = result.message || 'Registration successful. Redirecting...';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_success';
        
        setTimeout(() => { window.location.href = './login'; }, 800);
      } catch (err) {
        message.textContent = 'An error occurred. Please try again.';
        message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
      }
    });
  }
})();
