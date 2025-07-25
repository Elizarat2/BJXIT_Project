document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('productSelect');
    const productInfo = document.getElementById('productInfo');
    const productStock = document.getElementById('productStock');
    const productPrice = document.getElementById('productPrice');
    const orderTotal = document.getElementById('orderTotal');
    const quantityInput = document.getElementById('quantity');
    const orderForm = document.getElementById('orderForm');
    const orderSuccess = document.getElementById('orderSuccess');
    const clearFormBtn = document.getElementById('clearForm');
    const newOrderBtn = document.getElementById('newOrderBtn');
    const printOrderBtn = document.getElementById('printOrderBtn');

    // Cargar productos disponibles
    function loadProducts() {
        fetch('../../api/products/read.php', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('authToken')}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products) {
                productSelect.innerHTML = '<option value="">Seleccionar producto</option>';
                
                data.products.forEach(product => {
                    if (product.stock > 0) {
                        const option = document.createElement('option');
                        option.value = product.product_id;
                        option.textContent = `${product.product_key} - ${product.name}`;
                        option.dataset.stock = product.stock;
                        option.dataset.price = product.price || '0';
                        option.dataset.name = product.name;
                        productSelect.appendChild(option);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error al cargar productos:', error);
            showNotification('Error al cargar productos', 'error');
        });
    }

    // Mostrar información del producto seleccionado
    productSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            productStock.textContent = selectedOption.dataset.stock;
            productPrice.textContent = selectedOption.dataset.price;
            updateOrderTotal();
            productInfo.style.display = 'block';
        } else {
            productInfo.style.display = 'none';
        }
    });

    // Actualizar total al cambiar cantidad
    quantityInput.addEventListener('input', function() {
        updateOrderTotal();
        validateStock();
    });

    function updateOrderTotal() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        if (selectedOption.value) {
            const price = parseFloat(selectedOption.dataset.price) || 0;
            const quantity = parseInt(quantityInput.value) || 0;
            const total = (price * quantity).toFixed(2);
            orderTotal.textContent = total;
        }
    }

    function validateStock() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        if (!selectedOption.value) return;
        
        const maxStock = parseInt(selectedOption.dataset.stock) || 0;
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (quantity > maxStock) {
            quantityInput.classList.add('invalid');
            document.getElementById('submitOrder').disabled = true;
        } else {
            quantityInput.classList.remove('invalid');
            document.getElementById('submitOrder').disabled = false;
        }
    }

    // Enviar pedido
    orderForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const orderData = {
            product_id: productSelect.value,
            client_name: document.getElementById('clientName').value,
            quantity: quantityInput.value
        };

        fetch('../../api/orders/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('authToken')}`
            },
            body: JSON.stringify(orderData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar mensaje de éxito
                orderForm.style.display = 'none';
                orderSuccess.style.display = 'block';
                document.getElementById('orderNumber').textContent = data.order_id;
                document.getElementById('successClientName').textContent = orderData.client_name;
                document.getElementById('successProductName').textContent = selectedOption.dataset.name;
                document.getElementById('successQuantity').textContent = orderData.quantity;
                document.getElementById('successTotal').textContent = orderTotal.textContent;
                
                // Recargar productos para actualizar stock
                loadProducts();
            } else {
                showNotification(data.message || 'Error al crear el pedido', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al conectar con el servidor', 'error');
        });
    });

    // Limpiar formulario
    clearFormBtn.addEventListener('click', function() {
        orderForm.reset();
        productInfo.style.display = 'none';
    });

    // Nuevo pedido después de éxito
    newOrderBtn.addEventListener('click', function() {
        orderForm.reset();
        orderForm.style.display = 'block';
        orderSuccess.style.display = 'none';
        productInfo.style.display = 'none';
    });

    // Imprimir comprobante
    printOrderBtn.addEventListener('click', function() {
        window.print();
    });

    // Cargar productos al iniciar
    loadProducts();
});

// Función para mostrar notificaciones
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 500);
    }, 3000);
}