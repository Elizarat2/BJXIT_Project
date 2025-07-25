document.addEventListener('DOMContentLoaded', function() {
    // Cargar tabla de usuarios
    loadUsers();
    
    // Configurar modal
    const modal = document.getElementById('userModal');
    const addUserBtn = document.getElementById('addUserBtn');
    const closeBtn = document.querySelector('.close');
    
    addUserBtn.addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'Agregar Usuario';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        modal.style.display = 'block';
    });
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Manejar envío del formulario
    document.getElementById('userForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const userId = document.getElementById('userId').value;
        const username = document.getElementById('modalUsername').value;
        const email = document.getElementById('modalEmail').value;
        const password = document.getElementById('modalPassword').value;
        const role = document.getElementById('modalRole').value;
        
        const userData = {
            username,
            email,
            role
        };
        
        if (password) {
            userData.password = password;
        }
        
        const url = userId ? `../api/auth/update.php?id=${userId}` : '../api/auth/register.php';
        const method = userId ? 'PUT' : 'POST';
        
        fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('authToken')}`
            },
            body: JSON.stringify(userData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadUsers();
                modal.style.display = 'none';
            } else {
                alert(data.message || 'Error al guardar el usuario');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al conectar con el servidor');
        });
    });
});

function loadUsers() {
    fetch('../api/auth/users.php', {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('authToken')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = '';
        
        data.users.forEach(user => {
            const tr = document.createElement('tr');
            
            tr.innerHTML = `
                <td>${user.user_id}</td>
                <td>${user.username}</td>
                <td>${user.email}</td>
                <td>${getRoleName(user.role)}</td>
                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn-edit" data-id="${user.user_id}">Editar</button>
                    <button class="btn-delete" data-id="${user.user_id}">Eliminar</button>
                </td>
            `;
            
            tbody.appendChild(tr);
        });
        
        // Configurar botones de editar
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-id');
                editUser(userId);
            });
        });
        
        // Configurar botones de eliminar
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-id');
                if (confirm('¿Estás seguro de eliminar este usuario?')) {
                    deleteUser(userId);
                }
            });
        });
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function getRoleName(role) {
    switch(role) {
        case 'admin': return 'Administrador';
        case 'staff': return 'Personal Admin';
        case 'sales': return 'Vendedor';
        default: return role;
    }
}

function editUser(userId) {
    fetch(`../api/auth/users.php?id=${userId}`, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('authToken')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const user = data.user;
            document.getElementById('modalTitle').textContent = 'Editar Usuario';
            document.getElementById('userId').value = user.user_id;
            document.getElementById('modalUsername').value = user.username;
            document.getElementById('modalEmail').value = user.email;
            document.getElementById('modalRole').value = user.role;
            
            document.getElementById('userModal').style.display = 'block';
        } else {
            alert(data.message || 'Error al cargar el usuario');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
    });
}

function deleteUser(userId) {
    fetch(`../api/auth/users.php?id=${userId}`, {
        method: 'DELETE',
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('authToken')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadUsers();
        } else {
            alert(data.message || 'Error al eliminar el usuario');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
    });
}