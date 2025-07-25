document.addEventListener('DOMContentLoaded', function() {
    // Cargar tabla de productos
    loadProducts();
    
    // Configurar modal de productos
    const modal = document.getElementById('productModal');
    const addProductBtn = document.getElementById('addProductBtn');
    const closeBtn = modal.querySelector('.close');
    
    addProductBtn.addEventListener('click', function() {
        document.getElementById('productModalTitle').textContent = 'Agregar Producto';
        document.getElementById('productForm').reset();
        document.getElementById('productId').value = '';
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
    
    // Manejar envío del formulario de producto
    document.getElementById('productForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const productId = document.getElementById('productId').value;
        const productKey = document.getElementById('productKey').value;
        const productName = document.getElementById('productName').value;
        const productStock = document.getElementById('productStock').value;
        
        const productData = {
            product_key: productKey,
            name: productName,
            stock: productStock
        };
        
        const url = productId ? `../api/products/update.php?id=${productId}` : '../api/products/create.php';
        const method = productId ? 'PUT' : 'POST';
        
        fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('authToken')}`
            },
            body: JSON.stringify(productData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadProducts();
                modal.style.display = 'none';
            } else {
                alert(data.message || 'Error al guardar el producto');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al conectar con el servidor');
        });
    });
});

function loadProducts() {
    fetch('../api/products/read.php', {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('authToken')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        const tbody = document.querySelector('#productsTable tbody');
        tbody.innerHTML = '';
        
        data.products.forEach(product => {
            const tr = document.createElement('tr');
            
            tr.innerHTML = `
                <td>${product.product_key}</td>
                <td>${product.name}</td>
                <td>${product.stock}</td>
                <td>${new Date(product.updated_at).toLocaleString()}</td>
                <td>
                    <button class="btn-edit" data-id="${product.product_id}">Editar</button>
                    <button class="btn-delete" data-id="${product.product_id}">Eliminar</button>
                </td>
            `;
            
            tbody.appendChild(tr);
        });
        
        // Configurar botones de editar
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                editProduct(productId);
            });
        });
        
        // Configurar botones de eliminar
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                if (confirm('¿Estás seguro de eliminar este producto?')) {
                    deleteProduct(productId);
                }
            });
        });
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function editProduct(productId) {
    fetch(`../api/products/read.php?id=${productId}`, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('authToken')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const product = data.product;
            document.getElementById('productModalTitle').textContent = 'Editar Producto';
            document.getElementById('productId').value = product.product_id;
            document.getElementById('productKey').value = product.product_key;
            document.getElementById('productName').value = product.name;
            document.getElementById('productStock').value = product.stock;
            
            document.getElementById('productModal').style.display = 'block';
        } else {
            alert(data.message || 'Error al cargar el producto');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
    });
}

function deleteProduct(productId) {
    fetch(`../api/products/delete.php?id=${productId}`, {
        method: 'DELETE',
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('authToken')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadProducts();
        } else {
            alert(data.message || 'Error al eliminar el producto');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
    });
}