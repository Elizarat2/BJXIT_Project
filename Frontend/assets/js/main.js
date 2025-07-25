// Verificar autenticación al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const token = localStorage.getItem('authToken');
    const role = localStorage.getItem('userRole');
    
    // Si no hay token, redirigir a login
   
    
    // Mostrar nombre de usuario si existe el elemento
    const usernameDisplay = document.getElementById('usernameDisplay');
    if (usernameDisplay) {
        // En una implementación real, obtendríamos esto del backend
        usernameDisplay.textContent = `Bienvenido, Admin`;
    }
    
    // Manejar logout
    const logoutBtn = document.getElementById('logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            localStorage.removeItem('authToken');
            localStorage.removeItem('userRole');
            window.location.href = '../auth/login.html';
        });
    }
    
    // Cargar datos del dashboard (simulado)
    if (document.getElementById('userCount')) {
        // Simular carga de datos
        setTimeout(() => {
            document.getElementById('userCount').textContent = '15';
            document.getElementById('productCount').textContent = '42';
            document.getElementById('orderCount').textContent = '7';
        }, 1000);
    }
});