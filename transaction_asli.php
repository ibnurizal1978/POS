<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
session_start();

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: index");
    exit;
}

// Generate invoice number
function generateInvoiceNumber() {
    $prefix = date('Ymd');
    $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    return $prefix . $random;
}

// Get product by barcode
function getProductByBarcode($barcode) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT p.*, u.name as unit_name 
        FROM products p
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE p.barcode = ? AND p.store_id = ?
    ");
    $stmt->execute([$barcode, $_SESSION['store_id']]);
    return $stmt->fetch();
}

// Search products
function searchProducts($term) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT p.*, u.name as unit_name 
        FROM products p
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE p.store_id = ? AND (p.name LIKE ? OR p.barcode LIKE ?)
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['store_id'], "%$term%", "%$term%"]);
    return $stmt->fetchAll();
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_product':
            $barcode = $_GET['barcode'];
            $product = getProductByBarcode($barcode);
            
            if ($product) {
                echo json_encode($product);
            } else {
                echo json_encode(null);
            }
            exit;
            
        case 'search_products':
            $term = $_GET['term'] ?? '';
            $products = searchProducts($term);
            echo json_encode($products);
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .select2-container {
            width: 100% !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex justify-between items-center">
                    <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 no-print">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">Transaksi Baru</h1>
                    <div class="w-8"></div>
                </div>
            </div>
        </div>

        <!-- Transaction Form -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom Kiri: Scanner dan Input -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                        <!-- Scanner Compact -->
                        <div id="scanner-container" class="w-48 h-36 mx-auto rounded-lg overflow-hidden bg-gray-100 mb-4">
                            <video id="scanner" class="w-full h-full object-cover"></video>
                        </div>
                        
                        <!-- Product Search -->
                        <div>
                            <label for="product_search" class="block text-sm font-medium text-gray-700">
                                Cari Produk / Barcode
                            </label>
                            <select id="product_search" class="mt-1 block w-full rounded-lg border-gray-300">
                                <option value="">Ketik nama atau barcode produk...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Items List Container -->
                    <div class="bg-white rounded-lg shadow-sm">
                        <div id="items_container" class="divide-y divide-gray-200">
                            <!-- Items will be added here -->
                        </div>
                        <!-- Empty State -->
                        <div id="empty_state" class="p-8 text-center">
                            <div class="mx-auto mb-4">
                                <i class="fas fa-shopping-cart text-2xl text-gray-400"></i>
                            </div>
                            <h3 class="text-gray-500 font-medium">Belum ada item</h3>
                            <p class="text-sm text-gray-400">Scan barcode atau pilih produk untuk mulai</p>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Total Panel -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm p-6 space-y-4">
                        <div class="text-2xl font-bold text-gray-800" id="total_display">
                            Rp 0
                        </div>
                        
                        <div class="pt-4">
                            <button onclick="showPaymentModal()" 
                                    class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                                    id="pay_button"
                                    disabled>
                                Selesai (F8)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 m-4 max-w-sm w-full">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Pembayaran</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Total</label>
                    <div class="mt-1 text-2xl font-bold text-gray-800" id="modal_total">
                        Rp 0
                    </div>
                </div>

                <div>
                    <label for="paid_amount" class="block text-sm font-medium text-gray-700">Uang Diterima</label>
                    <div class="mt-1 relative rounded-lg shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">Rp</span>
                        </div>
                        <input type="text" 
                               id="paid_amount" 
                               class="block w-full pl-12 pr-12 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                               onkeyup="calculateChange(this)">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Kembalian</label>
                    <div class="mt-1 text-xl font-bold text-gray-800" id="change_amount">
                        Rp 0
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button onclick="hidePaymentModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                        Batal (Esc)
                    </button>
                    <button onclick="processTransaction()" 
                            id="process_button"
                            class="px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                            disabled>
                        Proses (Enter)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Template (hidden) -->
    <div id="receiptTemplate" class="hidden print-only">
        <div class="text-center p-4">
            <h2 class="text-xl font-bold mb-2"><?php echo $_SESSION['store_name']; ?></h2>
            <p class="text-sm mb-4" id="receipt_datetime"></p>
            <p class="text-sm mb-2" id="receipt_invoice"></p>
            <p class="text-sm mb-4" id="receipt_cashier"></p>
            
            <div class="border-t border-b border-gray-300 py-4 my-4">
                <div id="receipt_items"></div>
            </div>
            
            <div class="text-right space-y-1">
                <p class="font-bold">Total: <span id="receipt_total"></span></p>
                <p>Tunai: <span id="receipt_paid"></span></p>
                <p>Kembali: <span id="receipt_change"></span></p>
            </div>
            
            <div class="mt-8 text-sm">
                <p>Terima kasih atas kunjungan Anda</p>
                <p>Barang yang sudah dibeli tidak dapat dikembalikan</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <script>
        let items = [];
        let total = 0;
        let codeReader = null;
        
        // Initialize Select2
        $('#product_search').select2({
            ajax: {
                url: 'transaction.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'search_products',
                        term: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.map(function(item) {
                            return {
                                id: JSON.stringify(item),
                                text: item.name
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 3,
            placeholder: 'Ketik nama produk...'
        }).on('select2:select', function (e) {
            const product = JSON.parse(e.params.data.id);
            addItem(product);
            $(this).val(null).trigger('change');
        });

        // Fungsi untuk memulai scanner
        async function initScanner() {
            try {
                if (!codeReader) {
                    codeReader = new ZXing.BrowserMultiFormatReader();
                }

                const videoInputDevices = await codeReader.listVideoInputDevices();
                
                // Pilih kamera belakang jika ada
                let selectedDeviceId = videoInputDevices[0].deviceId;
                for (const device of videoInputDevices) {
                    if (device.label.toLowerCase().includes('back') || 
                        device.label.toLowerCase().includes('belakang')) {
                        selectedDeviceId = device.deviceId;
                        break;
                    }
                }

                // Langsung mulai scanning
                await codeReader.decodeFromVideoDevice(
                    selectedDeviceId, 
                    'scanner', 
                    (result, err) => {
                        if (result) {
                            // Play beep sound
                            new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZSA0PVqzn77BdGAg+ltryxnMpBSl+zPLaizsIGGS57OihUBELTKXh8bllHgU2jdXzzn0vBSF1xe/glEILElyx6OyrWBUIQ5zd8sFuJAUuhM/z1YU2Bhxqvu7mnEoODlOq5O+zYBoGPJPY88p2KwUme8rx3I4+CRZiturqpVITC0mi4PK8aB8GM4nU8tGAMQYfcsLu45ZFDBFYr+ftrVoXCECY3PLEcSYELIHO8diJOQcZaLvt559NEAxPqOPwtmMcBjiP1/PMeS0GI3fH8N2RQAoUXrTp66hVFApGnt/yvmwhBTCG0fPTgjQGHm/A7eSaRw0PVqzl77BeGQc9ltvyxnUoBSh+zPDaizsIGGS56+mjTxELTKXh8bllHgU1jdT0z3wvBSJ0xe/glEILElyx6OyrWRUIRJzd8sFuJAUug8/z1YU2BRxqvu3mnEoPDlOq5O+zYRsGPJPY88p3KgUme8rx3I4+CRVht+rqpVMSC0mh4PK8aiAFM4nU8tGAMQYfccPu45ZFDBFYr+ftrVwWCECY3PLEcSYGK4DN8tiIOQcZZ7zs56BODwxPpuPxtmQcBjiP1/PMeS0FI3fH8N+RQAoUXrTp66hWEwlGnt/yv2wiBDCG0fPTgzQGHm/A7eSaSQ0PVqvm77BeGQc9ltrzxnUoBSh9y/HajDsIF2W56+mjUREKTKPi8blnHgU1jdT0z3wvBSJ0xe/glEILElyx6OyrWRUIRJzd8sFuJAUug8/z1YU2BRxqvu3mnEoPDlOq5O+zYRsGOpPY88p3KgUmfMrx3I4+CRVht+rqpVMSC0mh4PK8aiAFM4nU8tGAMQYfccLu45ZGCxFYr+ftrVwWCA==').play();
                            
                            // Proses barcode
                            getProduct(result.text);
                        }
                    }
                );

                console.log('Scanner aktif dengan kamera:', selectedDeviceId);
            } catch (error) {
                console.error('Error scanner:', error);
                document.getElementById('scanner-container').innerHTML = `
                    <div class="p-4 text-center text-red-500">
                        <p>Tidak dapat mengakses kamera</p>
                        <p class="text-sm">Silakan gunakan pencarian manual</p>
                    </div>
                `;
            }
        }

        // Langsung mulai scanner saat halaman dimuat
        document.addEventListener('DOMContentLoaded', initScanner);

        // Cleanup saat halaman ditutup
        window.addEventListener('beforeunload', () => {
            if (codeReader) {
                codeReader.reset();
            }
        });

        // Get product by barcode
        function getProduct(barcode) {
            fetch(`transaction.php?action=get_product&barcode=${encodeURIComponent(barcode)}`)
                .then(response => response.json())
                .then(product => {
                    if (product) {
                        addItem(product);
                    } else {
                        // Tampilkan toast error jika produk tidak ditemukan
                        showToast('Produk tidak ditemukan', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Terjadi kesalahan saat mencari produk', 'error');
                });
        }

        // Add item to list
        function addItem(product) {
            // Check if item already exists
            const existingItem = items.find(item => item.id === product.id);
            if (existingItem) {
                existingItem.quantity++;
                existingItem.subtotal = existingItem.quantity * existingItem.price;
            } else {
                items.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.selling_price),
                    quantity: 1,
                    subtotal: parseFloat(product.selling_price),
                    unit_name: product.unit_name
                });
            }
            
            updateDisplay();
        }

        // Update quantity
        function updateQuantity(index, increment) {
            const item = items[index];
            if (increment) {
                item.quantity++;
            } else if (item.quantity > 1) {
                item.quantity--;
            }
            item.subtotal = item.quantity * item.price;
            updateDisplay();
        }

        // Remove item
        function removeItem(index) {
            items.splice(index, 1);
            updateDisplay();
        }

        // Update display
        function updateDisplay() {
            const container = document.getElementById('items_container');
            const emptyState = document.getElementById('empty_state');
            const payButton = document.getElementById('pay_button');
            
            if (items.length === 0) {
                container.innerHTML = '';
                emptyState.classList.remove('hidden');
                payButton.disabled = true;
                total = 0;
            } else {
                emptyState.classList.add('hidden');
                payButton.disabled = false;
                
                let html = '';
                total = 0;
                
                items.forEach((item, index) => {
                    total += item.subtotal;
                    html += `
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-800">${item.name}</h3>
                                    <div class="text-sm text-gray-500">
                                        Rp ${item.price.toLocaleString()} × ${item.quantity} ${item.unit_name}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-medium text-gray-800">
                                        Rp ${item.subtotal.toLocaleString()}
                                    </div>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <button onclick="updateQuantity(${index}, false)" 
                                                class="text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <span class="text-sm">${item.quantity}</span>
                                        <button onclick="updateQuantity(${index}, true)" 
                                                class="text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button onclick="removeItem(${index})" 
                                                class="text-red-400 hover:text-red-600 ml-2">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            }
            
            document.getElementById('total_display').textContent = `Rp ${total.toLocaleString()}`;
        }

        // Payment modal
        function showPaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            document.getElementById('modal_total').textContent = `Rp ${total.toLocaleString()}`;
            document.getElementById('paid_amount').value = '';
            document.getElementById('change_amount').textContent = 'Rp 0';
            document.getElementById('process_button').disabled = true;
            
            document.getElementById('paid_amount').focus();
        }

        function hidePaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function calculateChange(input) {
            // Remove non-digits
            let value = input.value.replace(/\D/g, '');
            // Format with thousand separator
            input.value = new Intl.NumberFormat('id-ID').format(value);
            
            const paid = parseFloat(value) || 0;
            const change = paid - total;
            
            document.getElementById('change_amount').textContent = `Rp ${Math.max(0, change).toLocaleString()}`;
            document.getElementById('process_button').disabled = paid < total;
        }

        // Process transaction
        function processTransaction() {
            const paid = parseFloat(document.getElementById('paid_amount').value.replace(/\D/g, ''));
            const change = paid - total;
            
            // Prepare data
            const data = {
                items: items,
                total: total,
                paid_amount: paid,
                change_amount: change
            };
            
            // Send to server
            fetch('transaction-process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    printReceipt(result.invoice_number, paid, change);
                    resetTransaction();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Print receipt
        function printReceipt(invoice_number, paid, change) {
            // Set receipt data
            document.getElementById('receipt_datetime').textContent = new Date().toLocaleString('id-ID');
            document.getElementById('receipt_invoice').textContent = `No: ${invoice_number}`;
            document.getElementById('receipt_cashier').textContent = `Kasir: <?php echo $_SESSION['name']; ?>`;
            
            let itemsHtml = '';
            items.forEach(item => {
                itemsHtml += `
                    <div class="flex justify-between text-sm mb-1">
                        <div>${item.name}</div>
                        <div>${item.quantity} ${item.unit_name} × ${item.price.toLocaleString()}</div>
                        <div>Rp ${item.subtotal.toLocaleString()}</div>
                    </div>
                `;
            });
            document.getElementById('receipt_items').innerHTML = itemsHtml;
            
            document.getElementById('receipt_total').textContent = `Rp ${total.toLocaleString()}`;
            document.getElementById('receipt_paid').textContent = `Rp ${paid.toLocaleString()}`;
            document.getElementById('receipt_change').textContent = `Rp ${change.toLocaleString()}`;
            
            // Print
            window.print();
        }

        // Reset transaction
        function resetTransaction() {
            items = [];
            total = 0;
            updateDisplay();
            hidePaymentModal();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F8') {
                e.preventDefault();
                if (!document.getElementById('pay_button').disabled) {
                    showPaymentModal();
                }
            } else if (e.key === 'Escape') {
                hidePaymentModal();
                stopScanner();
            } else if (e.key === 'Enter' && document.getElementById('paymentModal').classList.contains('flex')) {
                if (!document.getElementById('process_button').disabled) {
                    processTransaction();
                }
            }
        });

        // Barcode input
        document.getElementById('barcode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcode = this.value.trim();
                if (barcode) {
                    getProduct(barcode);
                }
            }
        });

        // Close modals when clicking outside
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hidePaymentModal();
            }
        });

        document.getElementById('scannerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                stopScanner();
            }
        });

        // Tambahkan fungsi untuk menampilkan toast
        function showToast(message, type = 'error') {
            // Hapus toast yang sudah ada (jika ada)
            const existingToast = document.getElementById('toast');
            if (existingToast) {
                existingToast.remove();
            }

            // Buat element toast baru
            const toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = `fixed top-4 right-4 z-50 rounded-lg px-4 py-3 shadow-lg transform transition-all duration-300 ${
                type === 'error' ? 'bg-red-500' : 'bg-green-500'
            } text-white`;
            toast.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;

            // Tambahkan ke body
            document.body.appendChild(toast);

            // Hilangkan toast setelah 3 detik
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>