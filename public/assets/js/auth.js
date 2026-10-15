(function () {
  const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const getBaseUrl = () => (window.AppConfig && window.AppConfig.baseUrl) ? window.AppConfig.baseUrl : '';

  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      const remember = document.getElementById('loginRemember')?.checked || false;
      const message = document.getElementById('loginMessage');

      try {
        const response = await fetch(getBaseUrl() + '/api/auth/login', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
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

        if (result.token) {
          try {
            localStorage.setItem('kp_admin_token', result.token);
          } catch (e) {
            console.warn('Could not save token in localStorage:', e);
          }
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
})();
