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
    die("Quotation ID not specified");
}

$quotation_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get quotation details
$stmt = $conn->prepare("SELECT q.*, b.name as business_name, b.address as business_address, 
                       b.contact as business_contact, c.name as client_name, c.phone as client_phone
                       FROM quotations q
                       JOIN business b ON q.business_id = b.id
                       JOIN clients c ON q.client_id = c.id
                       WHERE q.id = ? AND q.user_id = ?");
$stmt->bind_param("ii", $quotation_id, $user_id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quotation) {
    die("Quotation not found or you don't have permission to access it");
}

// Get quotation items
$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt->bind_param("i", $quotation_id);
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
</style>

<div class="header">
    <div class="title">QUOTATION</div>
    <div>
        <div><strong>Quotation ID:</strong> ' . $quotation['id'] . '</div>
        <div><strong>Date:</strong> ' . $quotation['date_created'] . '</div>
    </div>
</div>

<div class="from-to">
    <div class="from">
        <h3>From:</h3>
        <div>' . htmlspecialchars($quotation['business_name']) . '</div>
        <div>' . htmlspecialchars($quotation['business_address'] ?? '-') . '</div>
        <div>' . htmlspecialchars($quotation['business_contact'] ?? '-') . '</div>
    </div>
    
    <div class="to">
        <h3>To:</h3>
        <div>' . htmlspecialchars($quotation['client_name']) . '</div>
        <div>' . htmlspecialchars($quotation['client_phone'] ?? '-') . '</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>No.</th>
            <th>' . ($quotation['type'] == 'service' ? 'Service' : 'Product') . '</th>
            <th>Description</th>';
            
if ($quotation['type'] == 'product') {
    $html .= '<th>Qty</th>';
}

$html .= '
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
            <td>' . htmlspecialchars($item['description']) . '</td>';
    
    if ($quotation['type'] == 'product') {
        $html .= '<td>' . $item['quantity'] . '</td>';
    }
    
    $amount = $item['price'] * ($item['quantity'] ?? 1);
    $html .= '
            <td class="text-right">' . number_format($item['price'], 2) . '</td>
            <td class="text-right">' . number_format($amount, 2) . '</td>
        </tr>';
}

$html .= '
        <tr class="total-row">
            <td colspan="' . ($quotation['type'] == 'service' ? 4 : 5) . '">Total</td>
            <td class="text-right">' . number_format($quotation['amount'], 2) . '</td>
        </tr>
    </tbody>
</table>';

$mpdf->WriteHTML($html);

// Output PDF
$mpdf->Output('Quotation_' . $quotation['id'] . '.pdf', 'D');
exit();
?>