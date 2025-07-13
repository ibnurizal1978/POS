document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('form');
    const loginBtn = document.getElementById('loginBtn');
    const loginAction = document.querySelector('a');

    loginForm.addEventListener('submit', function(e) {
        // Prevent multiple submissions
        if (loginBtn.dataset.submitted) {
            e.preventDefault();
            return;
        }

        // Change button state
        loginBtn.dataset.submitted = true;
        loginBtn.value = 'Logging in...';
        loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...';
    });

    loginAction.addEventListener('click', function(e) {
        // Prevent multiple submissions
        if (loginBtn2.disabled) {
            e.preventDefault();
            return;
        }

        // Change button state
        loginBtn.value = 'Logging in...';
        loginBtn.disabled = true;
    });

});