document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const modal = document.getElementById('password-modal');
    const lengthReq = document.getElementById('req-length');
    const uppercaseReq = document.getElementById('req-uppercase');
    const numberReq = document.getElementById('req-number');
    const specialReq = document.getElementById('req-special');
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');
    const submitBtn = document.getElementById('change-password-btn');
    const mismatchError = document.getElementById('password-mismatch-error');
    const loginCard = document.querySelector('.login-card');
    const toggleNewPassword = document.getElementById('toggle-new-password');
    const toggleConfirmPassword = document.getElementById('toggle-confirm-password');

    if (!passwordInput || !confirmPasswordInput || !modal || !submitBtn || !mismatchError || !loginCard || !toggleNewPassword || !toggleConfirmPassword) return;

    const requirements = [
        { element: lengthReq, regex: /.{8,}/ },
        { element: uppercaseReq, regex: /[A-Z]/ },
        { element: numberReq, regex: /[0-9]/ },
        { element: specialReq, regex: /[!@#$%^&*(),.?":{}|<>]/ }
    ];

    function calculateStrength(password) {
        let score = 0;
        if (password.length >= 8) score += 25;
        if (/[A-Z]/.test(password)) score += 25;
        if (/[0-9]/.test(password)) score += 25;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score += 25;

        if (score < 50) {
            strengthBar.className = 'strength-bar weak';
            strengthText.textContent = 'Weak';
            strengthText.style.color = '#ff4d4f';
        } else if (score < 75) {
            strengthBar.className = 'strength-bar medium';
            strengthText.textContent = 'Medium';
            strengthText.style.color = '#ffeb3b';
        } else {
            strengthBar.className = 'strength-bar strong';
            strengthText.textContent = 'Strong';
            strengthText.style.color = '#28a745';
        }
    }

    function positionModal() {
        const inputRect = passwordInput.getBoundingClientRect();
        const cardRect = loginCard.getBoundingClientRect();
        const modalWidth = modal.offsetWidth || 250; // Default width if not rendered yet

        if (window.innerWidth > 768) {
            // Desktop: Position to the left of the login card, overlapping illustration
            modal.style.top = `${inputRect.top + window.scrollY + inputRect.height / 2}px`;
            modal.style.left = `${cardRect.left - modalWidth - 15}px`; // 15px gap
            modal.style.transform = 'translateY(-50%)';
        } else {
            // Mobile: Position at the bottom of the login card
            modal.style.top = `${cardRect.bottom + window.scrollY}px`;
            modal.style.left = `${cardRect.left}px`;
            modal.style.width = `${cardRect.width}px`;
            modal.style.transform = 'none';
        }
    }

    function validatePassword() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        let allMet = true;

        // Validate password requirements
        requirements.forEach(req => {
            if (req.regex.test(password)) {
                req.element.classList.add('met');
            } else {
                req.element.classList.remove('met');
                allMet = false;
            }
        });

        calculateStrength(password);

        // Check if passwords match
        const passwordsMatch = password === confirmPassword && password !== '';
        mismatchError.style.display = passwordsMatch || confirmPassword === '' ? 'none' : 'block';

        // Enable submit button only if all requirements are met and passwords match
        submitBtn.disabled = !allMet || !passwordsMatch;
    }

    // Toggle password visibility
    function togglePasswordVisibility(inputId, toggleIcon) {
        const input = document.getElementById(inputId);
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        toggleIcon.classList.toggle('fa-eye-slash');
        toggleIcon.classList.toggle('fa-eye');
    }

    toggleNewPassword.addEventListener('click', () => togglePasswordVisibility('new_password', toggleNewPassword));
    toggleConfirmPassword.addEventListener('click', () => togglePasswordVisibility('confirm_password', toggleConfirmPassword));

    // Show/hide modal on focus/blur and position it
    passwordInput.addEventListener('focus', () => {
        positionModal();
        modal.style.display = 'block';
    });

    passwordInput.addEventListener('blur', () => {
        // Delay hiding to allow clicking inside modal
        setTimeout(() => {
            if (!modal.contains(document.activeElement)) {
                modal.style.display = 'none';
            }
        }, 150);
    });

    // Reposition modal on window resize or scroll
    window.addEventListener('resize', positionModal);
    window.addEventListener('scroll', positionModal);

    // Validate on input for both password and confirm password
    passwordInput.addEventListener('input', validatePassword);
    confirmPasswordInput.addEventListener('input', validatePassword);

    // Initial validation
    validatePassword();
});