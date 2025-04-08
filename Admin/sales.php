<?php
/********************************************
 * 1) Database Connection
 ********************************************/
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // Adjust if your DB name differs

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/********************************************
 * 2) Fetch Products from `beautyshop` Table with Purchase Prices
 ********************************************/
$sql = "SELECT b.id, b.name, b.category, b.price, b.stock, b.subcategory, 
               COALESCE(p.purchase_price, 0) as purchase_price
        FROM beautyshop b
        LEFT JOIN purchases p ON b.id = p.product_id
        ORDER BY b.id ASC";
$result = $conn->query($sql);

$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Build an array item similar to your sample data structure
        $products[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'price' => (float) $row['price'],
            'purchase_price' => (float) $row['purchase_price'],
            'stock' => (int) $row['stock'],
            // If you want subcategory logic, include it:
            // 'subcategory' => $row['subcategory'] ?? ''
            // (But your existing JS only uses subcategory for "hair" items.)
        ];
    }
}
$conn->close();

/********************************************
 * 3) Convert $products to JSON for JavaScript
 ********************************************/
$products_json = json_encode($products);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty Shop Sales</title>
    <link rel="stylesheet" href="sales.css">
</head>

<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <h2 class="logo">abuelo jua code</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="purchases.php">Purchases</a></li>
            <li class="active"><a href="#">Sales</a></li>
            <li><a href="Customers.php">Customers</a></li>
            <li><a href="suppliers.php">Suppliers</a></li>
            <li><a href="Reports.php">Reports</a></li>
            <li><a href="myaccount.php">MyAccount</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
    </aside>

    <div id="notification" class="notification"></div>

    <header>
        <h1> Sales</h1>
        <div>
            <span id="date-time"></span>
        </div>
    </header>

    <div class="container">
        <div class="layout">
            <div class="products-section">
                <div class="search-tools">
                    <input type="text" id="search-products" class="search-input" placeholder="Search products...">
                    <select id="category-filter" class="category-filter">
                        <option value="">All Categories</option>
                        <option value="hair">Hair</option>
                        <option value="hairfood">Hair Food</option>
                        <option value="oils">Oils</option>
                        <option value="gels">Gels</option>
                        <option value="treatment">Treatment</option>
                        <option value="wax">Wax</option>
                        <option value="shampoos">Shampoos</option>
                        <option value="conditioners">Conditioners</option>
                        <option value="dyes">Dyes</option>
                        <option value="relaxers">Relaxers</option>
                        <option value="hairsprays">Hair Sprays</option>
                        <option value="toiletries">Toiletries</option>
                        <option value="body-lotions">Body Lotions</option>
                        <option value="sprays">Body Sprays</option>
                        <option value="hair-clips">Hair Clips</option>
                    </select>
                    <button class="barcode-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="16" rx="2" />
                            <line x1="7" y1="8" x2="7" y2="16" />
                            <line x1="11" y1="8" x2="11" y2="16" />
                            <line x1="15" y1="8" x2="15" y2="16" />
                            <line x1="19" y1="8" x2="19" y2="16" />
                        </svg>
                        Scan Barcode
                    </button>
                </div>

                <div class="table-container">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="products-list">
                            <!-- Products will be populated here via JavaScript -->
                        </tbody>
                    </table>

                    <ul class="pagination" id="pagination">
                        <!-- Pagination will be generated here -->
                    </ul>
                </div>
            </div>

            <div class="cart-section">
                <div class="cart-title">
                    <h2>Shopping Cart</h2>
                    <span id="cart-count">0 items</span>
                </div>

                <div class="cart-items" id="cart-items">
                    <!-- Cart items will be shown here -->
                    <div class="empty-cart" id="empty-cart">Your cart is empty</div>
                </div>

                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span id="subtotal">ksh0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Profit</span>
                        <span id="profit">ksh0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Total</span>
                        <span id="total">ksh0.00</span>
                    </div>
                </div>

                <button class="checkout-btn" id="checkout">Complete Sale</button>
                <div class="clear-cart" id="clear-cart">Clear Cart</div>
            </div>
        </div>
    </div>
    <script>
        /********************************************
         * 1) productsData from the DB (via PHP)
         ********************************************/
        const productsData = <?php echo $products_json; ?>;

        // Shopping cart array
        let cart = [];
        const itemsPerPage = 8;
        let currentPage = 1;

        // DOM Elements
        const productsList = document.getElementById('products-list');
        const paginationEl = document.getElementById('pagination');
        const cartItemsEl = document.getElementById('cart-items');
        const emptyCartEl = document.getElementById('empty-cart');
        const cartCountEl = document.getElementById('cart-count');
        const subtotalEl = document.getElementById('subtotal');
        const profitEl = document.getElementById('profit');
        const totalEl = document.getElementById('total');
        const checkoutBtn = document.getElementById('checkout');
        const clearCartBtn = document.getElementById('clear-cart');
        const searchInput = document.getElementById('search-products');
        const categoryFilter = document.getElementById('category-filter');
        const notificationEl = document.getElementById('notification');
        const dateTimeEl = document.getElementById('date-time');

        // Initialize date and time
        function updateDateTime() {
            const now = new Date();
            dateTimeEl.textContent = now.toLocaleString();
        }
        updateDateTime();
        setInterval(updateDateTime, 60000); // Update every minute

        // Filter and display products
        function displayProducts() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategory = categoryFilter.value;

            let filteredProducts = productsData.filter(product => {
                const matchSearch = product.name.toLowerCase().includes(searchTerm);
                const matchCategory = (selectedCategory === '' || product.category === selectedCategory);
                return matchSearch && matchCategory;
            });

            // Pagination logic
            const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const paginatedProducts = filteredProducts.slice(startIndex, startIndex + itemsPerPage);

            // Clear current products
            productsList.innerHTML = '';

            // Display products or "no products" message
            if (paginatedProducts.length === 0) {
                productsList.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No products found</td></tr>';
            } else {
                paginatedProducts.forEach(product => {
                    // Determine stock status for color coding
                    let stockStatus, stockClass;
                    if (product.stock > 20) {
                        stockStatus = 'High';
                        stockClass = 'stock-high';
                    } else if (product.stock > 10) {
                        stockStatus = 'Medium';
                        stockClass = 'stock-medium';
                    } else {
                        stockStatus = 'Low';
                        stockClass = 'stock-low';
                    }

                    const row = document.createElement('tr');
                    row.innerHTML = `
                    <td>${product.name}</td>
                    <td>${product.category.charAt(0).toUpperCase() + product.category.slice(1)}</td>
                    <td>ksh${product.price.toFixed(2)}</td>
                    <td>
                        <span class="stock-indicator ${stockClass}"></span>
                        ${product.stock} (${stockStatus})
                    </td>
                    <td>
                        <div class="quantity-control">
                            <button class="qty-btn decrease" data-id="${product.id}">-</button>
                            <input type="number" class="qty-input" value="1" min="1" max="${product.stock}" data-id="${product.id}">
                            <button class="qty-btn increase" data-id="${product.id}">+</button>
                            <button class="add-to-cart" data-id="${product.id}">Add</button>
                        </div>
                        <div class="stock-warning" id="warning-${product.id}">Not enough stock!</div>
                    </td>
                `;
                    productsList.appendChild(row);
                });
            }

            // Update pagination
            updatePagination(totalPages);

            // Add event listeners to new elements
            addProductEventListeners();
        }

        // Update pagination controls
        function updatePagination(totalPages) {
            paginationEl.innerHTML = '';

            if (totalPages <= 1) return;

            // Previous button
            const prevLi = document.createElement('li');
            prevLi.innerHTML = `<a href="#" ${currentPage === 1 ? 'style="opacity:0.5;pointer-events:none;"' : ''}>«</a>`;
            prevLi.addEventListener('click', (e) => {
                e.preventDefault();
                if (currentPage > 1) {
                    currentPage--;
                    displayProducts();
                }
            });
            paginationEl.appendChild(prevLi);

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const li = document.createElement('li');
                li.className = (i === currentPage) ? 'active' : '';
                li.innerHTML = `<a href="#">${i}</a>`;
                li.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentPage = i;
                    displayProducts();
                });
                paginationEl.appendChild(li);
            }

            // Next button
            const nextLi = document.createElement('li');
            nextLi.innerHTML = `<a href="#" ${currentPage === totalPages ? 'style="opacity:0.5;pointer-events:none;"' : ''}>»</a>`;
            nextLi.addEventListener('click', (e) => {
                e.preventDefault();
                if (currentPage < totalPages) {
                    currentPage++;
                    displayProducts();
                }
            });
            paginationEl.appendChild(nextLi);
        }

        // Add event listeners to product elements
        function addProductEventListeners() {
            // Quantity decrease buttons
            document.querySelectorAll('.decrease').forEach(button => {
                button.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const input = document.querySelector(`.qty-input[data-id="${id}"]`);
                    let value = parseInt(input.value);
                    if (value > 1) {
                        input.value = value - 1;
                    }
                });
            });

            // Quantity increase buttons
            document.querySelectorAll('.increase').forEach(button => {
                button.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const input = document.querySelector(`.qty-input[data-id="${id}"]`);
                    const product = productsData.find(p => p.id === parseInt(id));
                    let value = parseInt(input.value);
                    if (value < product.stock) {
                        input.value = value + 1;
                    }
                });
            });

            // Add to cart buttons
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', function () {
                    const id = parseInt(this.getAttribute('data-id'));
                    const product = productsData.find(p => p.id === id);
                    const qty = parseInt(document.querySelector(`.qty-input[data-id="${id}"]`).value);

                    // Check if there's enough stock
                    if (qty > product.stock) {
                        const warning = document.getElementById(`warning-${product.id}`);
                        warning.style.display = 'block';
                        setTimeout(() => {
                            warning.style.display = 'none';
                        }, 3000);
                        return;
                    }

                    // Check if product is already in cart
                    const existingItemIndex = cart.findIndex(item => item.id === id);

                    if (existingItemIndex !== -1) {
                        // Update quantity if product already in cart
                        const newQty = cart[existingItemIndex].quantity + qty;
                        if (newQty <= product.stock) {
                            cart[existingItemIndex].quantity = newQty;
                        } else {
                            showNotification(`Maximum stock reached for ${product.name}`);
                            return;
                        }
                    } else {
                        // Add new item to cart
                        cart.push({
                            id: product.id,
                            name: product.name,
                            price: product.price,
                            purchase_price: product.purchase_price,
                            quantity: qty,
                            category: product.category
                        });
                    }

                    // Update product stock
                    product.stock -= qty;

                    // Update UI
                    updateCart();
                    displayProducts(); // Refresh product list to show updated stock
                    showNotification(`Added ${qty} ${product.name} to cart`);
                });
            });
        }

        // Update cart display
        function updateCart() {
            // Update cart count
            const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
            cartCountEl.textContent = `${totalItems} item${(totalItems !== 1 ? 's' : '')}`;

            // Show/hide empty cart message
            if (cart.length === 0) {
                emptyCartEl.style.display = 'block';
            } else {
                emptyCartEl.style.display = 'none';
            }

            // Update cart items
            cartItemsEl.innerHTML = (cart.length === 0) ? '<div class="empty-cart">Your cart is empty</div>' : '';

            cart.forEach(item => {
                const cartItem = document.createElement('div');
                cartItem.className = 'cart-item';
                cartItem.innerHTML = `
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-details">ksh${item.price.toFixed(2)} x ${item.quantity}</div>
                </div>
                <div class="cart-item-actions">
                    <span>ksh${(item.price * item.quantity).toFixed(2)}</span>
                    <span class="remove-item" data-id="${item.id}">×</span>
                </div>
            `;
                cartItemsEl.appendChild(cartItem);
            });

            // Add event listeners to remove buttons
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function () {
                    const id = parseInt(this.getAttribute('data-id'));
                    removeFromCart(id);
                });
            });

            // Update totals
            updateTotals();
        }

        // Update financial totals
        function updateTotals() {
            const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            const profit = cart.reduce((total, item) => total + ((item.price - item.purchase_price) * item.quantity), 0);
            const total = subtotal; // Total is just the subtotal now since we're not adding tax

            subtotalEl.textContent = `ksh${subtotal.toFixed(2)}`;
            profitEl.textContent = `ksh${profit.toFixed(2)}`;
            totalEl.textContent = `ksh${total.toFixed(2)}`;
        }

        // Remove item from cart
        function removeFromCart(id) {
            const itemIndex = cart.findIndex(item => item.id === id);
            if (itemIndex !== -1) {
                const item = cart[itemIndex];
                const product = productsData.find(p => p.id === id);

                // Return stock
                product.stock += item.quantity;

                // Remove from cart
                cart.splice(itemIndex, 1);

                // Update UI
                updateCart();
                displayProducts(); // Refresh to show updated stock
                showNotification(`Removed ${item.name} from cart`);
            }
        }

        // Clear entire cart
        function clearCart() {
            // Return all stock
            cart.forEach(item => {
                const product = productsData.find(p => p.id === item.id);
                if (product) {
                    product.stock += item.quantity;
                }
            });

            // Empty cart
            cart = [];

            // Update UI
            updateCart();
            displayProducts();
            showNotification('Cart cleared');
        }

        // Show notification
        function showNotification(message) {
            notificationEl.textContent = message;
            notificationEl.style.display = 'block';
            setTimeout(() => {
                notificationEl.style.display = 'none';
            }, 3000);
        }

        // ========== Checkout: Post to process_sale.php ==========
        function checkout() {
            if (cart.length === 0) {
                showNotification('Cannot checkout with empty cart');
                return;
            }

            // Gather total & item count
            const totalAmount = parseFloat(totalEl.textContent.replace('ksh', ''));
            const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
            const profitAmount = parseFloat(profitEl.textContent.replace('ksh', ''));

            // Convert cart array to JSON
            const cartJSON = JSON.stringify(cart);

            // Send POST to process_sale.php
            fetch('process_sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'cartData=' + encodeURIComponent(cartJSON)
                    + '&total=' + encodeURIComponent(totalAmount)
                    + '&profit=' + encodeURIComponent(profitAmount)
            })
                .then(res => res.text())
                .then(response => {
                    if (response.trim() === 'Success') {
                        // Clear cart
                        cart = [];
                        updateCart();
                        showNotification(`Sale completed: ksh${totalAmount.toFixed(2)} for ${itemCount} items`);
                    } else {
                        showNotification('Error: ' + response);
                    }
                })
                .catch(err => {
                    console.error(err);
                    showNotification('Error processing sale');
                });
        }

        // Enhanced checkout with payment options (unchanged)
        function enhancedCheckout() {
            if (cart.length === 0) {
                showNotification('Cannot checkout with empty cart');
                return;
            }

            const totalAmount = parseFloat(totalEl.textContent.replace('ksh', ''));
            const paymentMethod = window.prompt(
                `Total: ksh${totalAmount.toFixed(2)}\nSelect payment method:\n1. Cash\n2. Card\n3. Mobile Payment`,
                "1"
            );

            if (!paymentMethod) {
                showNotification('Checkout cancelled');
                return;
            }

            let paymentMethodName;
            switch (paymentMethod) {
                case "1":
                    paymentMethodName = "Cash";
                    handleCashPayment(totalAmount);
                    break;
                case "2":
                    paymentMethodName = "Card";
                    handleCardPayment(totalAmount);
                    break;
                case "3":
                    paymentMethodName = "Mobile Payment";
                    handleMobilePayment(totalAmount);
                    break;
                default:
                    showNotification('Invalid payment method selected');
                    return;
            }

            const saleData = { paymentMethod: paymentMethodName };
            const saleId = saveSale(saleData);
            const receipt = generateReceipt(saleId);

            // Clear cart
            cart = [];
            updateCart();
            displayProducts();

            showNotification(`Sale completed: ksh${totalAmount.toFixed(2)} paid with ${paymentMethodName}`);
        }

        // Payment simulation
        function handleCashPayment(totalAmount) {
            const amountTendered = window.prompt(`Total: ksh${totalAmount.toFixed(2)}\nEnter amount tendered:`, totalAmount.toFixed(2));
            if (!amountTendered) {
                showNotification('Payment cancelled');
                return false;
            }
            const amountPaid = parseFloat(amountTendered);
            if (isNaN(amountPaid) || amountPaid < totalAmount) {
                showNotification('Insufficient payment amount');
                return false;
            }
            const change = amountPaid - totalAmount;
            if (change > 0) {
                alert(`Change due: ksh${change.toFixed(2)}`);
            }
            return true;
        }
        function handleCardPayment(totalAmount) {
            alert(`Processing card payment for ksh${totalAmount.toFixed(2)}...`);
            processPayment(totalAmount, { type: 'card' })
                .then(result => {
                    showNotification(`Card payment successful: ${result.transactionId}`);
                    return true;
                })
                .catch(error => {
                    showNotification(`Payment failed: ${error.message}`);
                    return false;
                });
            return true;
        }
        function handleMobilePayment(totalAmount) {
            alert(`Mobile payment for ksh${totalAmount.toFixed(2)}\nShow QR code to customer.`);
            setTimeout(() => {
                const isConfirmed = window.confirm('Has the mobile payment been confirmed?');
                if (isConfirmed) {
                    showNotification('Mobile payment confirmed');
                } else {
                    showNotification('Mobile payment cancelled');
                }
            }, 1000);
            return true;
        }
        function processPayment(amount, paymentDetails) {
            return new Promise((resolve, reject) => {
                setTimeout(() => {
                    const isSuccessful = Math.random() < 0.95;
                    if (isSuccessful) {
                        resolve({
                            success: true,
                            transactionId: 'TR-' + Date.now(),
                            amount: amount,
                            message: 'Payment processed successfully'
                        });
                    } else {
                        reject({
                            success: false,
                            errorCode: 'PAYMENT_FAILED',
                            message: 'Payment processing failed. Please try again.'
                        });
                    }
                }, 800);
            });
        }

        // Sales history
        let salesHistory = [];
        function saveSale(saleData) {
            const saleId = Date.now();
            const newSale = {
                id: saleId,
                timestamp: new Date(),
                items: [...cart],
                subtotal: parseFloat(subtotalEl.textContent.replace('ksh', '')),
                profit: parseFloat(profitEl.textContent.replace('ksh', '')),
                total: parseFloat(totalEl.textContent.replace('ksh', '')),
                paymentMethod: saleData.paymentMethod
            };
            salesHistory.push(newSale);
            console.log("Sale saved:", newSale);
            return saleId;
        }
        function generateReceipt(saleId) {
            const sale = salesHistory.find(s => s.id === saleId);
            if (!sale) return null;
            const receiptItems = sale.items.map(item => {
                return {
                    name: item.name,
                    quantity: item.quantity,
                    unitPrice: item.price,
                    subtotal: item.price * item.quantity
                };
            });
            const receipt = {
                shopName: "Beauty Shop",
                saleId: saleId,
                date: sale.timestamp.toLocaleDateString(),
                time: sale.timestamp.toLocaleTimeString(),
                items: receiptItems,
                subtotal: sale.subtotal,
                profit: sale.profit,
                total: sale.total,
                paymentMethod: sale.paymentMethod
            };
            return receipt;
        }
        function exportSalesData() {
            if (salesHistory.length === 0) {
                showNotification('No sales data to export');
                return;
            }
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Sale ID,Date,Time,Items,Subtotal,Profit,Total,Payment Method\n";
            salesHistory.forEach(sale => {
                const itemsCount = sale.items.reduce((total, item) => total + item.quantity, 0);
                const row = [
                    sale.id,
                    sale.timestamp.toLocaleDateString(),
                    sale.timestamp.toLocaleTimeString(),
                    itemsCount,
                    sale.subtotal.toFixed(2),
                    sale.profit.toFixed(2),
                    sale.total.toFixed(2),
                    sale.paymentMethod
                ];
                csvContent += row.join(',') + "\n";
            });
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `beauty-shop-sales-${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showNotification('Sales data exported successfully');
        }

        // Low stock alert system
        function checkLowStock() {
            const lowStockThreshold = 10;
            const lowStockItems = productsData.filter(product => product.stock <= lowStockThreshold);
            if (lowStockItems.length > 0) {
                const itemNames = lowStockItems.map(item => `${item.name} (${item.stock})`).join(', ');
                showNotification(`Low stock alert: ${itemNames}`);
                console.warn("Low stock items:", lowStockItems);
            }
        }
        setInterval(checkLowStock, 300000); // Check every 5 minutes

        // Initialize the application
        function init() {
            displayProducts();

            // Event listeners for search and filter
            searchInput.addEventListener('input', () => {
                currentPage = 1;
                displayProducts();
            });
            categoryFilter.addEventListener('change', () => {
                currentPage = 1;
                displayProducts();
            });

            // Checkout and clear cart buttons
            checkoutBtn.addEventListener('click', checkout);
            clearCartBtn.addEventListener('click', clearCart);

            // Barcode button (simulation)
            document.querySelector('.barcode-btn').addEventListener('click', function () {
                const randomIndex = Math.floor(Math.random() * productsData.length);
                const randomProduct = productsData[randomIndex];
                searchInput.value = randomProduct.name;
                categoryFilter.value = randomProduct.category;
                displayProducts();
                showNotification(`Scanned: ${randomProduct.name}`);
            });

            // Handle quantity input validation
            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('qty-input')) {
                    const id = parseInt(e.target.getAttribute('data-id'));
                    const product = productsData.find(p => p.id === id);
                    let value = parseInt(e.target.value);
                    if (isNaN(value) || value < 1) {
                        e.target.value = 1;
                    } else if (value > product.stock) {
                        e.target.value = product.stock;
                    }
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function (e) {
                // Alt + C to checkout
                if (e.altKey && e.code === 'KeyC') {
                    e.preventDefault();
                    checkout();
                }
                // Alt + S to focus search
                if (e.altKey && e.code === 'KeyS') {
                    e.preventDefault();
                    searchInput.focus();
                }
                // Alt + X to clear cart
                if (e.altKey && e.code === 'KeyX') {
                    e.preventDefault();
                    clearCart();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>

</body>

</html>