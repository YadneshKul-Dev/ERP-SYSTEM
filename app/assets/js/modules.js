// ============================================
// ERP System - Module Specific JavaScript
// ============================================

// Profile photo preview
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('photo_preview');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ID Card print function
function printIDCard() {
    var content = document.querySelector('.id-card-container');
    if (content) {
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = content.outerHTML;
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    }
}

// Bulk action select all
function toggleSelectAll(source) {
    document.querySelectorAll('.select-item').forEach(function(el) {
        el.checked = source.checked;
    });
}

// Get selected items
function getSelectedItems() {
    var selected = [];
    document.querySelectorAll('.select-item:checked').forEach(function(el) {
        selected.push(el.value);
    });
    return selected;
}

// Confirm bulk action
function confirmBulkAction(action) {
    var selected = getSelectedItems();
    if (selected.length === 0) {
        alert('Please select at least one item.');
        return false;
    }
    return confirm('Are you sure you want to ' + action + ' ' + selected.length + ' item(s)?');
}

// Dynamic invoice items
function addInvoiceItem() {
    var tbody = document.getElementById('invoiceItemsBody');
    var row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="item_description[]" class="form-control" placeholder="Item description"></td>
        <td><input type="number" name="item_quantity[]" class="form-control item-qty" step="0.01" min="0" value="1" oninput="calcInvoiceTotal()"></td>
        <td><input type="number" name="item_unit_price[]" class="form-control item-price" step="0.01" min="0" value="0" oninput="calcInvoiceTotal()"></td>
        <td><input type="number" name="item_total[]" class="form-control item-total" step="0.01" readonly value="0"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();calcInvoiceTotal()">&times;</button></td>
    `;
    tbody.appendChild(row);
}

function calcInvoiceTotal() {
    var subtotal = 0;
    document.querySelectorAll('#invoiceItemsBody tr').forEach(function(row) {
        var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        var price = parseFloat(row.querySelector('.item-price').value) || 0;
        var total = qty * price;
        row.querySelector('.item-total').value = total.toFixed(2);
        subtotal += total;
    });
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    var taxPct = parseFloat(document.getElementById('tax_percentage').value) || 0;
    var discount = parseFloat(document.getElementById('discount').value) || 0;
    var taxAmt = subtotal * taxPct / 100;
    document.getElementById('tax_amount').value = taxAmt.toFixed(2);
    var total = subtotal + taxAmt - discount;
    document.getElementById('total').value = total.toFixed(2);
    var paid = parseFloat(document.getElementById('amount_paid').value) || 0;
    document.getElementById('amount_due').value = (total - paid).toFixed(2);
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Copied to clipboard!');
    });
}

function showToast(message) {
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1e293b;color:white;padding:12px 24px;border-radius:8px;font-size:14px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.2);animation:fadeIn 0.3s;';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(function() { toast.remove(); }, 300);
    }, 2500);
}

// Add CSS animation
var style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
`;
document.head.appendChild(style);
