<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/quotation_invoice/mpdf/vendor/autoload.php';

include $_SERVER['DOCUMENT_ROOT'] . '/quotation_invoice/server/conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invoice ID not specified");
}

$invoice_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get invoice details
$stmt = $conn->prepare("SELECT i.*, b.name as business_name, b.address as business_address, 
                       b.contact as business_contact, c.name as client_name, c.phone as client_phone,
                       q.id as quotation_id
                       FROM invoices i
                       JOIN business b ON i.business_id = b.id
                       JOIN clients c ON i.client_id = c.id
                       LEFT JOIN quotations q ON i.quotation_id = q.id
                       WHERE i.id = ? AND i.user_id = ?");
$stmt->bind_param("ii", $invoice_id, $user_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die("Invoice not found or you don't have permission to access it");
}

// Get invoice items (from quotation if exists)
if ($invoice['quotation_id']) {
    $stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
    $stmt->bind_param("i", $invoice['quotation_id']);
} else {
    // If you have direct invoice items, you would query them here
    die("Invoice items not found");
}

$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Create PDF
$mpdf = new \Mpdf\Mpdf();

$html = '
<style>
    body { font-family: Arial, sans-serif; }
    .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
    .title { font-size: 24px; font-weight: bold; }
    .from-to { display: flex; justify-content: space-between; margin-bottom: 30px; }
    .from, .to { width: 45%; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .text-right { text-align: right; }
    .total-row { font-weight: bold; }
    .payment-info { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; }
</style>

<div class="header">
    <div class="title">INVOICE</div>
    <div>
        <div><strong>Invoice ID:</strong> ' . $invoice['id'] . '</div>
        <div><strong>Date:</strong> ' . $invoice['date_created'] . '</div>';
        
if ($invoice['quotation_id']) {
    $html .= '<div><strong>Quotation Reference:</strong> ' . $invoice['quotation_id'] . '</div>';
}

$html .= '
    </div>
</div>

<div class="from-to">
    <div class="from">
        <h3>From:</h3>
        <div>' . htmlspecialchars($invoice['business_name']) . '</div>
        <div>' . htmlspecialchars($invoice['business_address'] ?? '-') . '</div>
        <div>' . htmlspecialchars($invoice['business_contact'] ?? '-') . '</div>
    </div>
    
    <div class="to">
        <h3>To:</h3>
        <div>' . htmlspecialchars($invoice['client_name']) . '</div>
        <div>' . htmlspecialchars($invoice['client_phone'] ?? '-') . '</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>No.</th>
            <th>Item</th>
            <th>Description</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>';

foreach ($items as $index => $item) {
    $html .= '
        <tr>
            <td>' . ($index + 1) . '</td>
            <td>' . htmlspecialchars($item['item_name']) . '</td>
            <td>' . htmlspecialchars($item['description']) . '</td>
            <td>' . ($item['quantity'] ?? 1) . '</td>
            <td class="text-right">' . number_format($item['price'], 2) . '</td>
            <td class="text-right">' . number_format($item['price'] * ($item['quantity'] ?? 1), 2) . '</td>
        </tr>';
}

$html .= '
        <tr class="total-row">
            <td colspan="5">Total</td>
            <td class="text-right">' . number_format($invoice['amount'], 2) . '</td>
        </tr>
    </tbody>
</table>

<div class="payment-info">
    <p><strong>Status:</strong> ' . ucfirst($invoice['status']) . '</p>';
    
if ($invoice['status'] == 'pending') {
    $html .= '
    <p>Please make payment by [due date] to the account below:</p>
    <p>Bank: [Your Bank Name]<br>
    Account Name: [Your Business Name]<br>
    Account Number: [Your Account Number]</p>';
}

$html .= '
</div>';

$mpdf->WriteHTML($html);

// Output PDF
$mpdf->Output('Invoice_' . $invoice['id'] . '.pdf', 'D');
exit();
?>