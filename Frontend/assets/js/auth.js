document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordMatchMessage = document.getElementById('passwordMatch');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    // Validación de contraseña en tiempo real
    passwordInput.addEventListener('input', function() {
        validatePasswordStrength(this.value);
        checkPasswordMatch();
    });

    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    // Manejar envío del formulario
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm()) {
            registerUser();
        }
    });

    function validatePasswordStrength(password) {
        // Implementación de fuerza de contraseña
        let strength = 0;
        
        // Longitud mínima
        if (password.length >= 8) strength += 1;
        
        // Contiene números y letras
        if (/[0-9]/.test(password)) strength += 1;
        if (/[a-zA-Z]/.test(password)) strength += 1;
        
        // Contiene caracteres especiales
        if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
        
        // Actualizar UI
        const width = (strength / 4) * 100;
        strengthBar.style.width = width + '%';
        
        // Cambiar colores 
        if (strength < 2) {
            strengthBar.style.backgroundColor = '#e74c3c';
            strengthText.textContent = 'Débil';
        } else if (strength < 4) {
            strengthBar.style.backgroundColor = '#f39c12';
            strengthText.textContent = 'Moderada';
        } else {
            strengthBar.style.backgroundColor = '#2ecc71';
            strengthText.textContent = 'Fuerte';
        }
    }

    function checkPasswordMatch() {
        if (passwordInput.value !== confirmPasswordInput.value) {
            passwordMatchMessage.textContent = 'Las contraseñas no coinciden';
            passwordMatchMessage.className = 'validation-message invalid';
            return false;
        } else {
            passwordMatchMessage.textContent = 'Las contraseñas coinciden';
            passwordMatchMessage.className = 'validation-message valid';
            return true;
        }
    }

    function validateForm() {
        // Validación básica
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (!username || !email || !password || !confirmPassword) {
            alert('Todos los campos son obligatorios');
            return false;
        }
        
        if (password !== confirmPassword) {
            alert('Las contraseñas no coinciden');
            return false;
        }
        
        if (password.length < 8) {
            alert('La contraseña debe tener al menos 8 caracteres');
            return false;
        }
        
        if (!/[0-9]/.test(password) || !/[a-zA-Z]/.test(password)) {
            alert('La contraseña debe incluir números y letras');
            return false;
        }
        
        return true;
    }

    async function registerUser() {
        const registerBtn = document.getElementById('registerBtn');
        registerBtn.disabled = true;
        registerBtn.textContent = 'Registrando...';
        
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = passwordInput.value;
        const roleField = document.getElementById('roleField');
        const role = roleField.style.display === 'block' ? document.getElementById('role').value : 'sales';
        
        try {
          const response = await fetch('http://localhost/BJXIT_Project/Backend/api/auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('authToken') || ''}`
                },
                body: JSON.stringify({
                    username,
                    email,
                    password,
                    role
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (localStorage.getItem('authToken')) {
                    // Si es admin registrando, mostrar mensaje y resetear formulario
                    alert('Usuario registrado exitosamente');
                    registerForm.reset();
                } else {
                    // Si es nuevo usuario, guardar token y redirigir
                    localStorage.setItem('authToken', data.token);
                    localStorage.setItem('userRole', data.role);
                    window.location.href = '../sales/orders.html';
                }
            } else {
                alert(data.message || 'Error al registrar usuario');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al conectar con el servidor');
        } finally {
            registerBtn.disabled = false;
            registerBtn.textContent = 'Registrarse';
        }
    }
});