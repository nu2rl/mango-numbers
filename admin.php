<?php
/**
 * Mango Number - Premium Owner Admin Panel
 */

require_once __DIR__ . '/config.php';
require_login();
require_admin();

$db = get_db_connection();
if (!$db) {
    die("Database connection failed. Please run <a href='db_init.php'>db_init.php</a> first.");
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch administrator profile data
$user_stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_profile = $user_stmt->fetch();
$user_name = $user_profile['name'] ?? 'Administrator';
$user_email = $user_profile['email'] ?? 'admin@mangonumbers.com';

// Action: Get Support Chat Messages (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'get-complaint-chat') {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT c.*, u.username FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.admin_deleted_at IS NULL");
    $stmt->execute([$id]);
    $complaint = $stmt->fetch();
    if (!$complaint) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Complaint not found']);
        exit;
    }
    
    // Fetch all messages
    $msg_stmt = $db->prepare("SELECT * FROM complaint_messages WHERE complaint_id = ? ORDER BY id ASC");
    $msg_stmt->execute([$id]);
    $msgs = $msg_stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode([
        'complaint' => [
            'id' => $complaint['id'],
            'username' => $complaint['username'],
            'subject' => $complaint['subject'],
            'message' => $complaint['message'],
            'created_at' => date('d M Y, h:i A', strtotime($complaint['created_at'])),
        ],
        'messages' => array_map(function($m) {
            return [
                'sender' => $m['sender'],
                'message' => $m['message'],
                'created_at' => date('d M Y, h:i A', strtotime($m['created_at']))
            ];
        }, $msgs)
    ]);
    exit;
}

// Action: Export Users as PDF
if (isset($_GET['action']) && $_GET['action'] === 'export-users-pdf') {
    $stmt = $db->query("SELECT u.*, (SELECT COALESCE(SUM(p.price_paid_inr), 0) FROM purchases p WHERE p.user_id = u.id AND p.status = 'approved') AS total_spent FROM users u WHERE u.status = 'active' ORDER BY u.id DESC");
    $users_data = $stmt->fetchAll();

    class SimplePDFWriter {
        private $objects = [];
        private $pageObjects = [];
        private $fontId;
        private $fontBoldId;
        private $resourcesId;

        public function __construct() {
            $this->fontId = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
            $this->fontBoldId = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
            $this->resourcesId = $this->addObject('<< /Font << /F1 ' . $this->fontId . ' 0 R /F2 ' . $this->fontBoldId . ' 0 R >> >>');
        }

        private function addObject($content) {
            $id = count($this->objects) + 1;
            $this->objects[$id] = $content;
            return $id;
        }

        public function addPage($streamContent) {
            $streamLen = strlen($streamContent);
            $contentId = $this->addObject('<< /Length ' . $streamLen . ' >> stream' . "\r\n" . $streamContent . "\r\n" . 'endstream');
            $pageId = $this->addObject('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents ' . $contentId . ' 0 R /Resources ' . $this->resourcesId . ' 0 R >>');
            $this->pageObjects[] = $pageId;
        }

        public function output($filename = 'export.pdf') {
            $out = "%PDF-1.4\r\n";
            $offsets = [];
            $allObjects = [];
            
            $allObjects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
            
            $kidsStr = '[';
            foreach ($this->pageObjects as $pid) {
                $kidsStr .= ($pid + 2) . ' 0 R ';
            }
            $kidsStr .= ']';
            $allObjects[2] = '<< /Type /Pages /Kids ' . $kidsStr . ' /Count ' . count($this->pageObjects) . ' >>';
            
            foreach ($this->objects as $id => $content) {
                $allObjects[$id + 2] = str_replace(
                    [' 2 0 R', ' ' . $this->resourcesId . ' 0 R'],
                    [' 2 0 R', ' ' . ($this->resourcesId + 2) . ' 0 R'],
                    $content
                );
            }
            
            foreach ($this->pageObjects as $pid) {
                $oldPageObj = $this->objects[$pid];
                preg_match('/\/Contents (\d+) 0 R/', $oldPageObj, $matches);
                $oldContentId = $matches[1];
                $allObjects[$pid + 2] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents ' . ($oldContentId + 2) . ' 0 R /Resources ' . ($this->resourcesId + 2) . ' 0 R >>';
            }
            
            foreach ($allObjects as $id => $objContent) {
                $offsets[$id] = strlen($out);
                $out .= $id . " 0 obj\r\n" . $objContent . "\r\nendobj\r\n";
            }
            
            $xrefOffset = strlen($out);
            $out .= "xref\r\n";
            $out .= "0 " . (count($allObjects) + 1) . "\r\n";
            $out .= "0000000000 65535 f\r\n";
            for ($i = 1; $i <= count($allObjects); $i++) {
                $out .= sprintf("%010d 00000 n\r\n", $offsets[$i]);
            }
            
            $out .= "trailer\r\n";
            $out .= "<< /Size " . (count($allObjects) + 1) . " /Root 1 0 R >>\r\n";
            $out .= "startxref\r\n";
            $out .= $xrefOffset . "\r\n";
            $out .= "%%EOF\r\n";
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($out));
            echo $out;
            exit;
        }
    }

    if (!function_exists('pdf_escape')) {
        function pdf_escape($str) {
            $str = str_replace('\\', '\\\\', $str ?? '');
            $str = str_replace('(', '\(', $str);
            $str = str_replace(')', '\)', $str);
            return $str;
        }
    }

    $pdf = new SimplePDFWriter();
    $pageStream = '';
    $y = 740;
    $pageNum = 1;

    $initPageHeader = function(&$stream, $num) {
        $stream .= "0.95 0.95 0.95 rg\r\n";
        $stream .= "40 765 515 45 re f\r\n";
        
        $stream .= "BT\r\n";
        $stream .= "/F2 16 Tf\r\n";
        $stream .= "0.1 0.1 0.1 rg\r\n";
        $stream .= "50 782 Td\r\n";
        $stream .= "(MANGO NUMBERS - REGISTERED USER REPORT) Tj\r\n";
        $stream .= "ET\r\n";

        $stream .= "BT\r\n";
        $stream .= "/F1 8 Tf\r\n";
        $stream .= "0.4 0.4 0.4 rg\r\n";
        $stream .= "400 786 Td\r\n";
        $stream .= "(Generated: " . date('d M Y, h:i A') . ") Tj\r\n";
        $stream .= "ET\r\n";

        $stream .= "0.9 0.4 0.1 rg\r\n";
        $stream .= "40 740 515 18 re f\r\n";

        $stream .= "BT\r\n";
        $stream .= "/F2 9 Tf\r\n";
        $stream .= "1 1 1 rg\r\n";
        $stream .= "45 745 Td\r\n"; $stream .= "(ID) Tj\r\n";
        $stream .= "40 0 Td\r\n"; $stream .= "(Name) Tj\r\n";
        $stream .= "100 0 Td\r\n"; $stream .= "(Email Address) Tj\r\n";
        $stream .= "160 0 Td\r\n"; $stream .= "(Mobile Number) Tj\r\n";
        $stream .= "90 0 Td\r\n"; $stream .= "(Total Spent) Tj\r\n";
        $stream .= "75 0 Td\r\n"; $stream .= "(Role) Tj\r\n";
        $stream .= "ET\r\n";
    };

    $initPageHeader($pageStream, $pageNum);

    $rowIndex = 1;
    foreach ($users_data as $u) {
        if ($y < 70) {
            $pageStream .= "BT\r\n";
            $pageStream .= "/F1 9 Tf\r\n";
            $pageStream .= "0.4 0.4 0.4 rg\r\n";
            $pageStream .= "275 40 Td\r\n";
            $pageStream .= "(Page $pageNum) Tj\r\n";
            $pageStream .= "ET\r\n";

            $pdf->addPage($pageStream);
            
            $pageStream = '';
            $pageNum++;
            $y = 740;
            $initPageHeader($pageStream, $pageNum);
        }

        $y -= 16;
        
        if ($rowIndex % 2 === 0) {
            $pageStream .= "0.98 0.98 0.98 rg\r\n";
            $pageStream .= "40 " . $y . " 515 16 re f\r\n";
        }
        $rowIndex++;

        $pageStream .= "BT\r\n";
        $pageStream .= "/F1 9 Tf\r\n";
        $pageStream .= "0.15 0.15 0.15 rg\r\n";
        
        // ID
        $pageStream .= "45 " . ($y + 4) . " Td\r\n";
        $pageStream .= "(#" . $u['id'] . ") Tj\r\n";
        
        // Name
        $pageStream .= "40 0 Td\r\n";
        $pageStream .= "(" . pdf_escape(substr($u['name'] ?? 'N/A', 0, 18)) . ") Tj\r\n";
        
        // Email
        $pageStream .= "100 0 Td\r\n";
        $pageStream .= "(" . pdf_escape(substr($u['email'] ?? '', 0, 30)) . ") Tj\r\n";
        
        // Mobile
        $pageStream .= "160 0 Td\r\n";
        $mobileStr = !empty($u['mobile']) ? $u['mobile'] : 'N/A';
        $pageStream .= "(" . pdf_escape($mobileStr) . ") Tj\r\n";
        
        // Total Spent
        $pageStream .= "90 0 Td\r\n";
        $pageStream .= "(INR " . number_format($u['total_spent'], 2) . ") Tj\r\n";
        
        // Role
        $pageStream .= "75 0 Td\r\n";
        $pageStream .= "(" . strtoupper($u['role']) . ") Tj\r\n";
        
        $pageStream .= "ET\r\n";
        
        $pageStream .= "0.9 0.9 0.9   RG\r\n";
        $pageStream .= "0.5 w\r\n";
        $pageStream .= "40 " . $y . " m 555 " . $y . " l S\r\n";
    }

    $pageStream .= "BT\r\n";
    $pageStream .= "/F1 9 Tf\r\n";
    $pageStream .= "0.4 0.4 0.4 rg\r\n";
    $pageStream .= "275 40 Td\r\n";
    $pageStream .= "(Page $pageNum) Tj\r\n";
    $pageStream .= "ET\r\n";

    $pdf->addPage($pageStream);
    $pdf->output('mango_numbers_users_' . date('Ymd_His') . '.pdf');
}

// Fetch active SMTP configurations from database
$smtp_settings_stmt = $db->query("SELECT * FROM smtp_settings WHERE active = 1 LIMIT 1");
$smtp_settings = $smtp_settings_stmt->fetch();
if (!$smtp_settings) {
    $smtp_settings = [
        'host' => 'smtp-relay.brevo.com',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_email' => 'no-reply@mangonumbers.com',
        'from_name' => 'Mango Numbers'
    ];
}

// Check for CSRF token validation on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = 'CSRF token verification failed.';
        $active_tab = $_POST['active_tab'] ?? 'approvals';
        header("Location: admin.php?active_tab=" . urlencode($active_tab));
        exit;
    }
}

$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

// 1. Handle Order Approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_approve'])) {
    $purchase_id = (int)$_POST['purchase_id'];
    $virtual_number = trim($_POST['virtual_number'] ?? '');
    $otp_code = trim($_POST['otp_code'] ?? '');
    $active_tab = $_POST['active_tab'] ?? 'approvals';
    
    if (empty($virtual_number) || empty($otp_code)) {
        $_SESSION['error_msg'] = 'Virtual number and SMS OTP code are required for approval.';
    } elseif (!preg_match('/^\+?[0-9]+$/', $virtual_number) || strlen($virtual_number) > 12) {
        $_SESSION['error_msg'] = 'Virtual number must only contain digits and an optional leading plus sign (+), up to 12 characters in total.';
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $otp_code) || strlen($otp_code) > 8) {
        $_SESSION['error_msg'] = 'SMS OTP code must be alphanumeric and 8 characters or less.';
    } else {
        // Fetch purchase details
        $stmt = $db->prepare("SELECT * FROM purchases WHERE id = ? AND status = 'pending'");
        $stmt->execute([$purchase_id]);
        $purchase = $stmt->fetch();
        
        if (!$purchase) {
            $_SESSION['error_msg'] = 'Pending order not found or already processed.';
        } else {
            $db->beginTransaction();
            try {
                // Update purchase
                $update = $db->prepare("UPDATE purchases SET status = 'approved', virtual_number_provided = ?, otp_provided = ?, screenshot_path = NULL WHERE id = ?");
                $update->execute([$virtual_number, $otp_code, $purchase_id]);
                
                // Decrement stock in products table
                $dec_products = $db->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - 1), availability_status = CASE WHEN stock_quantity - 1 <= 0 THEN 'out_of_stock' ELSE availability_status END WHERE id = ?");
                $dec_products->execute([$purchase['catalog_id']]);

                // Decrement stock in legacy catalog table
                $dec_catalog = $db->prepare("UPDATE catalog SET stock = GREATEST(0, stock - 1) WHERE id = ?");
                $dec_catalog->execute([$purchase['catalog_id']]);
                
                $db->commit();
                $_SESSION['success_msg'] = 'Order approved successfully! Stock decremented and virtual verification OTP sent to user dashboard.';

                // Delete screenshot proof from disk
                if (!empty($purchase['screenshot_path'])) {
                    $file_path = __DIR__ . '/' . $purchase['screenshot_path'];
                    if (is_file($file_path)) {
                        @unlink($file_path);
                    }
                }
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_msg'] = 'Approval processing failed: ' . $e->getMessage();
            }
        }
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// 2. Handle Order Rejections
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reject'])) {
    $purchase_id = (int)$_POST['purchase_id'];
    $active_tab = $_POST['active_tab'] ?? 'approvals';
    
    $stmt = $db->prepare("SELECT id, screenshot_path FROM purchases WHERE id = ? AND status = 'pending'");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch();
    
    if (!$purchase) {
        $_SESSION['error_msg'] = 'Pending order not found or already processed.';
    } else {
        $update = $db->prepare("UPDATE purchases SET status = 'rejected', screenshot_path = NULL WHERE id = ?");
        if ($update->execute([$purchase_id])) {
            $_SESSION['success_msg'] = 'Order rejected successfully.';

            // Delete screenshot proof from disk
            if (!empty($purchase['screenshot_path'])) {
                $file_path = __DIR__ . '/' . $purchase['screenshot_path'];
                if (is_file($file_path)) {
                    @unlink($file_path);
                }
            }
        } else {
            $_SESSION['error_msg'] = 'Failed to reject order.';
        }
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// 3. Handle Catalog Updates (Rates / Stock / Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_catalog_item'])) {
    $item_id = (int)$_POST['item_id'];
    $price_cost_inr = (float)$_POST['price_cost_inr'];
    $price_cost_usd = (float)$_POST['price_cost_usd'];
    $price_inr = (float)$_POST['price_inr'];
    $price_usd = (float)$_POST['price_usd'];
    $stock = (int)$_POST['stock'];
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
    $active_tab = $_POST['active_tab'] ?? 'catalog';
    
    $update = $db->prepare("UPDATE catalog SET price_cost_inr = ?, price_cost_usd = ?, price_inr = ?, price_usd = ?, stock = ?, status = ? WHERE id = ?");
    if ($update->execute([$price_cost_inr, $price_cost_usd, $price_inr, $price_usd, $stock, $status, $item_id])) {
        $_SESSION['success_msg'] = 'Catalog item rate and stock levels updated successfully.';
    } else {
        $_SESSION['error_msg'] = 'Failed to update catalog item.';
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// Helper for file uploads (Supports direct file upload & Clipboard Ctrl+V pasted image base64)
if (!function_exists('upload_catalog_icon')) {
    function upload_catalog_icon($file_input_name, $subfolder = 'houses') {
        // 1. Check if base64 image was pasted from clipboard (Ctrl+V)
        $base64_param = 'pasted_base64_' . $file_input_name;
        if (!empty($_POST[$base64_param])) {
            $base64_data = $_POST[$base64_param];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_data, $type)) {
                $data = substr($base64_data, strpos($base64_data, ',') + 1);
                $ext = strtolower($type[1]);
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $upload_dir = __DIR__ . '/uploads/' . $subfolder . '/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $filename = uniqid('pasted_', true) . '.' . $ext;
                        $destination = $upload_dir . $filename;
                        if (file_put_contents($destination, $decoded) !== false) {
                            return 'uploads/' . $subfolder . '/' . $filename;
                        }
                    }
                }
            }
        }

        // 2. Standard File Upload (also works with DataTransfer pasted file objects)
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$file_input_name];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            if (in_array($ext, $allowed)) {
                $upload_dir = __DIR__ . '/uploads/' . $subfolder . '/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $filename = uniqid('icon_', true) . '.' . $ext;
                $destination = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    return 'uploads/' . $subfolder . '/' . $filename;
                }
            }
        }
        return null;
    }
}

// 3b. Handle Manage Offers: Create Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_section'])) {
    $sec_name = trim($_POST['section_name'] ?? '');
    $sec_desc = trim($_POST['section_description'] ?? '');
    $sec_icon = trim($_POST['section_icon'] ?? 'bx-layer');

    $uploaded_icon = upload_catalog_icon('section_image_file', 'sections');
    if ($uploaded_icon) {
        $sec_icon = $uploaded_icon;
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $sec_name)));

    if (empty($sec_name)) {
        $_SESSION['error_msg'] = 'Section name is required.';
    } else {
        $stmt = $db->prepare("INSERT INTO sections (name, slug, description, icon) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sec_name, $slug, $sec_desc, $sec_icon]);
        $_SESSION['success_msg'] = "New Section '{$sec_name}' created successfully!";
    }
    header("Location: admin.php?active_tab=catalog");
    exit;
}

// 3c. Handle Manage Offers: Delete Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_section'])) {
    $sec_id = (int)$_POST['section_id'];
    $stmt = $db->prepare("DELETE FROM sections WHERE id = ?");
    $stmt->execute([$sec_id]);
    $_SESSION['success_msg'] = 'Section deleted successfully!';
    header("Location: admin.php?active_tab=catalog");
    exit;
}

// 3c-2. Handle Manage Offers: Reorder Sections (AJAX Drag & Drop)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reorder_sections'])) {
    $raw_order = $_POST['order'] ?? '';
    $order = json_decode($raw_order, true);
    if (is_array($order)) {
        $stmt = $db->prepare("UPDATE sections SET display_order = ? WHERE id = ?");
        foreach ($order as $index => $id) {
            $stmt->execute([$index + 1, (int)$id]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// 3d. Handle Manage Offers: Create House (Product)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_house'])) {
    $sec_id = (int)$_POST['section_id'];
    $house_name = trim($_POST['house_name'] ?? '');
    $country = trim($_POST['country'] ?? 'Global');
    $price_inr = (float)($_POST['price_inr'] ?? 0);
    $price_usd = (float)($_POST['price_usd'] ?? 0);
    $price_cost_inr = (float)($_POST['price_cost_inr'] ?? 0);
    $price_cost_usd = (float)($_POST['price_cost_usd'] ?? 0);
    $stock = (int)($_POST['stock_quantity'] ?? 0);
    $icon = trim($_POST['house_icon'] ?? '');
    $badge = trim($_POST['badge'] ?? '');

    $uploaded_icon = upload_catalog_icon('house_image_file', 'houses');
    if ($uploaded_icon) {
        $icon = $uploaded_icon;
    }

    if (empty($house_name) || $sec_id <= 0) {
        $_SESSION['error_msg'] = 'House name and Section are required.';
    } else {
        $status = ($stock > 0) ? 'available' : 'out_of_stock';
        $stmt = $db->prepare("INSERT INTO products (section_id, name, country, price_cost_usd, price_cost_inr, price_usd, price_inr, stock_quantity, availability_status, icon, badge) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sec_id, $house_name, $country, $price_cost_usd, $price_cost_inr, $price_usd, $price_inr, $stock, $status, $icon, $badge]);
        $_SESSION['success_msg'] = "New House '{$house_name}' added to Section!";
    }
    header("Location: admin.php?active_tab=catalog&view_section=" . $sec_id);
    exit;
}

// 3e. Handle Manage Offers: Update House
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_house'])) {
    $prod_id = (int)$_POST['product_id'];
    $sec_id = (int)$_POST['section_id'];
    $price_inr = (float)($_POST['price_inr'] ?? 0);
    $price_usd = (float)($_POST['price_usd'] ?? 0);
    $stock = (int)($_POST['stock_quantity'] ?? 0);

    $house_name = isset($_POST['house_name']) ? trim($_POST['house_name']) : '';
    $badge = isset($_POST['badge']) ? trim($_POST['badge']) : null;
    $icon = isset($_POST['house_icon']) ? trim($_POST['house_icon']) : null;

    $uploaded_icon = upload_catalog_icon('house_image_file', 'houses');
    if ($uploaded_icon) {
        $icon = $uploaded_icon;
    }

    $stmt_old = $db->prepare("SELECT name, badge, icon, status, availability_status FROM products WHERE id = ?");
    $stmt_old->execute([$prod_id]);
    $old = $stmt_old->fetch();

    if (empty($house_name) && $old) {
        $house_name = $old['name'];
    }
    if ($badge === null && $old) {
        $badge = $old['badge'];
    }
    if ($icon === null && $old) {
        $icon = $old['icon'];
    }

    if (isset($_POST['status'])) {
        $status_input = trim($_POST['status']);
        $status = ($status_input === 'inactive' || $status_input === 'disabled') ? 'inactive' : 'active';
        $availability = ($status === 'inactive') ? 'disabled' : (($stock > 0) ? 'available' : 'out_of_stock');
    } else if ($old) {
        $status = $old['status'];
        $availability = ($status === 'inactive') ? 'disabled' : (($stock > 0) ? 'available' : 'out_of_stock');
    } else {
        $status = 'active';
        $availability = ($stock > 0) ? 'available' : 'out_of_stock';
    }

    $stmt = $db->prepare("UPDATE products SET name = ?, badge = ?, icon = ?, price_inr = ?, price_usd = ?, stock_quantity = ?, status = ?, availability_status = ? WHERE id = ?");
    $stmt->execute([$house_name, $badge, $icon, $price_inr, $price_usd, $stock, $status, $availability, $prod_id]);
    $_SESSION['success_msg'] = 'House details updated successfully!';
    header("Location: admin.php?active_tab=catalog&view_section=" . $sec_id);
    exit;
}

// 3f. Handle Manage Offers: Delete House
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_house'])) {
    $prod_id = (int)$_POST['product_id'];
    $sec_id = (int)$_POST['section_id'];
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$prod_id]);
    $_SESSION['success_msg'] = 'House deleted successfully!';
    header("Location: admin.php?active_tab=catalog&view_section=" . $sec_id);
    exit;
}

// 3f-2. Handle Manage Offers: Bulk Delete Houses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_bulk_delete_houses'])) {
    $sec_id = (int)($_POST['section_id'] ?? 0);
    $ids = $_POST['selected_house_ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        $valid_ids = array_map('intval', array_filter($ids, 'is_numeric'));
        if (!empty($valid_ids)) {
            $in_clause = implode(',', array_fill(0, count($valid_ids), '?'));
            $stmt = $db->prepare("DELETE FROM products WHERE id IN ($in_clause)");
            $stmt->execute($valid_ids);
            $deleted_count = count($valid_ids);
            $_SESSION['success_msg'] = "Successfully deleted {$deleted_count} selected house(s)!";
        }
    } else {
        $_SESSION['error_msg'] = 'No houses were selected for deletion.';
    }
    header("Location: admin.php?active_tab=catalog&view_section=" . $sec_id);
    exit;
}

// 3g. Handle Manage Offers: Instant Toggle House Status (Enable/Disable)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_toggle_house_status'])) {
    $prod_id = (int)$_POST['product_id'];
    $sec_id = (int)$_POST['section_id'];

    if (isset($_POST['status'])) {
        $status_val = trim($_POST['status']);
        $new_status = ($status_val === 'disabled' || $status_val === 'inactive') ? 'inactive' : 'active';
    } else {
        $stmt_curr = $db->prepare("SELECT status, availability_status FROM products WHERE id = ?");
        $stmt_curr->execute([$prod_id]);
        $curr = $stmt_curr->fetch();
        $is_currently_disabled = ($curr && ($curr['status'] === 'inactive' || $curr['availability_status'] === 'disabled'));
        $new_status = $is_currently_disabled ? 'active' : 'inactive';
    }

    if ($new_status === 'active') {
        $stmt_st = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt_st->execute([$prod_id]);
        $st = $stmt_st->fetch();
        $stock = (int)($st['stock_quantity'] ?? 0);
        $new_availability = ($stock > 0) ? 'available' : 'out_of_stock';
        $label = 'Enabled';
    } else {
        $new_availability = 'disabled';
        $label = 'Disabled';
    }

    $stmt = $db->prepare("UPDATE products SET status = ?, availability_status = ? WHERE id = ?");
    $stmt->execute([$new_status, $new_availability, $prod_id]);

    $_SESSION['success_msg'] = "House status updated to {$label}!";
    header("Location: admin.php?active_tab=catalog&view_section=" . $sec_id);
    exit;
}

// 4. Handle Support/Complaint Ticket Response (from Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_support_response']) || isset($_POST['submit_support_response_keep_open']))) {
    $complaint_id = (int)$_POST['complaint_id'];
    $response_text = trim($_POST['response_text'] ?? '');
    $active_tab = $_POST['active_tab'] ?? 'complaints';
    
    $should_resolve = isset($_POST['submit_support_response']);
    $status_to_set = $should_resolve ? 'resolved' : 'open';
    $msg_suffix = $should_resolve ? 'Ticket resolved and closed.' : 'Ticket response sent and kept open.';

    if (empty($response_text)) {
        $_SESSION['error_msg'] = 'Response message cannot be empty.';
    } else {
        // 1. Insert message into complaint_messages
        $ins_msg = $db->prepare("INSERT INTO complaint_messages (complaint_id, sender, message) VALUES (?, 'admin', ?)");
        $ins_msg->execute([$complaint_id, $response_text]);

        // 2. Update complaint status and last admin response text
        $update = $db->prepare("UPDATE complaints SET admin_response = ?, status = ? WHERE id = ?");
        if ($update->execute([$response_text, $status_to_set, $complaint_id])) {
            $_SESSION['success_msg'] = 'Response submitted. ' . $msg_suffix;
        } else {
            $_SESSION['error_msg'] = 'Failed to update ticket status.';
        }
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// 4b. Handle Delete Support/Complaint Ticket (Soft Delete & Mark Resolved)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_delete_complaint'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $active_tab = $_POST['active_tab'] ?? 'complaints';
    $stmt = $db->prepare("UPDATE complaints SET admin_deleted_at = CURRENT_TIMESTAMP, status = 'resolved' WHERE id = ?");
    if ($stmt->execute([$complaint_id])) {
        $_SESSION['success_msg'] = 'Complaint resolved and closed successfully. It will be permanently removed from the system after 3 days.';
    } else {
        $_SESSION['error_msg'] = 'Failed to delete complaint.';
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// 5. Handle Registered User Profile Updates (Name, Email, Mobile, Password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_edit_user'])) {
    $edit_user_id = (int)$_POST['edit_user_id'];
    $edit_name = trim($_POST['edit_name'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_mobile = trim($_POST['edit_mobile'] ?? '');
    $edit_password = $_POST['edit_password'] ?? '';
    $active_tab = $_POST['active_tab'] ?? 'users';
    
    if (empty($edit_name) || empty($edit_email)) {
        $_SESSION['error_msg'] = 'Name and Email address are required.';
    } elseif (!filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_msg'] = 'Please enter a valid email address.';
    } else {
        // Verify email uniqueness (excluding current user)
        $stmt = $db->prepare("SELECT id, status FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$edit_email, $edit_user_id]);
        $existing_user = $stmt->fetch();
        if ($existing_user && $existing_user['status'] === 'active') {
            $_SESSION['error_msg'] = 'Email address is already registered by another user.';
        } else {
            $db->beginTransaction();
            try {
                if ($existing_user && $existing_user['status'] === 'deleted') {
                    // Remove old deleted record first to avoid key conflicts
                    $del_stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $del_stmt->execute([$existing_user['id']]);
                }
                // If a new password is provided, update it too
                if (!empty($edit_password)) {
                    if (strlen($edit_password) < 6) {
                        throw new Exception('New password must be at least 6 characters.');
                    }
                    $hashed_pw = password_hash($edit_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, username = ?, mobile = ?, password = ? WHERE id = ?");
                    $stmt->execute([$edit_name, $edit_email, $edit_email, $edit_mobile, $hashed_pw, $edit_user_id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, username = ?, mobile = ? WHERE id = ?");
                    $stmt->execute([$edit_name, $edit_email, $edit_email, $edit_mobile, $edit_user_id]);
                }
                $db->commit();
                $_SESSION['success_msg'] = 'User account profile details updated successfully!';
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_msg'] = 'Failed to update user details: ' . $e->getMessage();
            }
        }
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// 6. Handle Creating a New User (by Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_add_user'])) {
    $add_name = trim($_POST['add_name'] ?? '');
    $add_email = trim($_POST['add_email'] ?? '');
    $add_mobile = trim($_POST['add_mobile'] ?? '');
    $add_password = $_POST['add_password'] ?? '';
    $add_role = trim($_POST['add_role'] ?? 'user');
    $active_tab = $_POST['active_tab'] ?? 'users';

    if (empty($add_name) || empty($add_email) || empty($add_password)) {
        $_SESSION['error_msg'] = 'Name, Email address, and Password are required.';
    } elseif (!filter_var($add_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_msg'] = 'Please enter a valid email address.';
    } elseif (strlen($add_password) < 6) {
        $_SESSION['error_msg'] = 'Password must be at least 6 characters.';
    } elseif (!in_array($add_role, ['user', 'admin'])) {
        $_SESSION['error_msg'] = 'Invalid user role selected.';
    } else {
        // Verify email uniqueness
        $stmt = $db->prepare("SELECT id, status FROM users WHERE email = ?");
        $stmt->execute([$add_email]);
        $existing_user = $stmt->fetch();
        if ($existing_user && $existing_user['status'] === 'active') {
            $_SESSION['error_msg'] = 'Email address is already registered.';
        } else {
            try {
                if ($existing_user && $existing_user['status'] === 'deleted') {
                    // Remove old deleted record first to avoid key conflicts
                    $del_stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $del_stmt->execute([$existing_user['id']]);
                }
                $hashed_pw = password_hash($add_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, username, mobile, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$add_name, $add_email, $add_email, $add_mobile, $hashed_pw, $add_role]);
                $_SESSION['success_msg'] = 'New user account created successfully!';
            } catch (Exception $e) {
                $_SESSION['error_msg'] = 'Failed to create user account: ' . $e->getMessage();
            }
        }
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// 7. Handle Deleting a User (Soft-delete with reason)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_user'])) {
    $delete_user_id = (int)$_POST['delete_user_id'];
    $delete_reason = trim($_POST['delete_reason'] ?? 'No reason specified by administrator.');
    $active_tab = $_POST['active_tab'] ?? 'users';
    
    // Ensure we don't delete ourselves (the logged-in admin)
    if ($delete_user_id === $user_id) {
        $_SESSION['error_msg'] = 'You cannot delete your own logged-in administrator account!';
    } else {
        // Verify the user exists
        $stmt = $db->prepare("SELECT id, name FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$delete_user_id]);
        $user_to_delete = $stmt->fetch();
        
        if (!$user_to_delete) {
            $_SESSION['error_msg'] = 'Active user account not found.';
        } else {
            $db->beginTransaction();
            try {
                // Delete user's complaints
                $stmt = $db->prepare("DELETE FROM complaints WHERE user_id = ?");
                $stmt->execute([$delete_user_id]);
                
                // Delete user's purchases
                $stmt = $db->prepare("DELETE FROM purchases WHERE user_id = ?");
                $stmt->execute([$delete_user_id]);
                
                // Soft-delete: Mark user status as 'deleted' and save the reason
                $stmt = $db->prepare("UPDATE users SET status = 'deleted', deletion_reason = ? WHERE id = ?");
                $stmt->execute([$delete_reason, $delete_user_id]);
                
                $db->commit();
                $_SESSION['success_msg'] = 'User account "' . htmlspecialchars($user_to_delete['name']) . '" deleted successfully and their operational history has been cleared!';
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_msg'] = 'Failed to delete user account: ' . $e->getMessage();
            }
        }
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// Handle updating system settings (Allow Signups & Allow Website Usage toggles)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_system_settings'])) {
    $allow_signups = isset($_POST['allow_signups']) ? '1' : '0';
    $allow_website_usage = isset($_POST['allow_website_usage']) ? '1' : '0';
    $admin_password = $_POST['admin_password'] ?? '';
    $active_tab = $_POST['active_tab'] ?? 'settings';
    
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin_pw_hash = $stmt->fetchColumn();
    
    if (empty($admin_password) || !password_verify($admin_password, $admin_pw_hash)) {
        $_SESSION['error_msg'] = 'Incorrect administrator password! System configurations update aborted.';
    } else {
        $mail_host = trim($_POST['mail_host'] ?? '');
        $mail_port = trim($_POST['mail_port'] ?? '');
        $mail_username = trim($_POST['mail_username'] ?? '');
        $mail_password = trim($_POST['mail_password'] ?? '');
        $mail_encryption = trim($_POST['mail_encryption'] ?? 'tls');
        $mail_from_address = trim($_POST['mail_from_address'] ?? '');
        $mail_from_name = trim($_POST['mail_from_name'] ?? '');

        set_system_setting('allow_signups', $allow_signups);
        set_system_setting('allow_website_usage', $allow_website_usage);

        // Update or Insert into smtp_settings table
        $smtp_stmt = $db->query("SELECT id FROM smtp_settings WHERE active = 1 LIMIT 1");
        $existing_smtp = $smtp_stmt->fetch();

        if ($existing_smtp) {
            if (empty($mail_password)) {
                // Keep password unchanged
                $up_stmt = $db->prepare("UPDATE smtp_settings SET host = ?, port = ?, username = ?, encryption = ?, from_email = ?, from_name = ? WHERE id = ?");
                $up_stmt->execute([$mail_host, (int)$mail_port, $mail_username, $mail_encryption, $mail_from_address, $mail_from_name, $existing_smtp['id']]);
            } else {
                $up_stmt = $db->prepare("UPDATE smtp_settings SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ? WHERE id = ?");
                $up_stmt->execute([$mail_host, (int)$mail_port, $mail_username, $mail_password, $mail_encryption, $mail_from_address, $mail_from_name, $existing_smtp['id']]);
            }
        } else {
            $ins_stmt = $db->prepare("INSERT INTO smtp_settings (host, port, username, password, encryption, from_email, from_name, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $ins_stmt->execute([$mail_host, (int)$mail_port, $mail_username, $mail_password, $mail_encryption, $mail_from_address, $mail_from_name]);
        }

        // Also keep updating system settings table as fallback
        set_system_setting('mail_host', $mail_host);
        set_system_setting('mail_port', $mail_port);
        set_system_setting('mail_username', $mail_username);
        if (!empty($mail_password)) {
            set_system_setting('mail_password', $mail_password);
        }
        set_system_setting('mail_encryption', $mail_encryption);
        set_system_setting('mail_from_address', $mail_from_address);
        set_system_setting('mail_from_name', $mail_from_name);

        $_SESSION['success_msg'] = 'System settings and SMTP configurations updated successfully!';
    }
    header("Location: admin.php?active_tab=" . urlencode($active_tab));
    exit;
}

// Fetch stats for widgets
// A. Total Sales Revenue (Rate Sold)
$stmt = $db->query("SELECT SUM(price_paid_inr) FROM purchases WHERE status = 'approved'");
$total_revenue = (float)$stmt->fetchColumn();

// B. Total Cost Spend (Rate Bought)
$stmt = $db->query("SELECT SUM(price_cost_inr) FROM purchases WHERE status = 'approved'");
$total_spend = (float)$stmt->fetchColumn();

// C. Net Profit (Revenue - Spend)
$net_profit = $total_revenue - $total_spend;

// D. Pending Transactions
$stmt = $db->query("SELECT COUNT(*) FROM purchases WHERE status = 'pending'");
$pending_orders_count = (int)$stmt->fetchColumn();

// E. Total Active Users
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active'");
$users_count = (int)$stmt->fetchColumn();

// F. Unresolved Complaints
$stmt = $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'open' AND admin_deleted_at IS NULL");
$unresolved_complaints_count = (int)$stmt->fetchColumn();

// Fetch Pending Purchases with user details
$stmt = $db->query("SELECT p.*, u.username FROM purchases p JOIN users u ON p.user_id = u.id WHERE p.status = 'pending' ORDER BY p.id ASC");
$pending_purchases = $stmt->fetchAll();

// Fetch Catalog list
$stmt = $db->query("SELECT * FROM catalog ORDER BY service_type DESC, country ASC, name ASC");
$catalog_list = $stmt->fetchAll();

// Fetch Complaints with user details
$stmt = $db->query("SELECT c.*, u.username FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.admin_deleted_at IS NULL ORDER BY c.status ASC, c.id DESC");
$complaints_list = $stmt->fetchAll();

// Fetch all registered users with their total approved spending
$stmt = $db->query("SELECT u.*, (SELECT COALESCE(SUM(p.price_paid_inr), 0) FROM purchases p WHERE p.user_id = u.id AND p.status = 'approved') AS total_spent FROM users u WHERE u.status = 'active' ORDER BY u.id DESC");
$all_users = $stmt->fetchAll();

// Preserve active tab state across form submissions
$active_tab = $_GET['active_tab'] ?? 'approvals';

// Revenue Chart Data
// Daily: last 14 days
$daily_stmt = $db->query("
    SELECT DATE(created_at) as day, 
           SUM(price_paid_inr) as revenue, 
           SUM(price_cost_inr) as cost,
           COUNT(*) as orders
    FROM purchases 
    WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(created_at) ORDER BY day ASC
");
$daily_raw = $daily_stmt->fetchAll();
// Build a full 14-day array with 0s for missing days
$daily_labels = $daily_revenue = $daily_profit = $daily_orders = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($d));
    $found = array_filter($daily_raw, fn($r) => $r['day'] === $d);
    $row = $found ? array_values($found)[0] : null;
    $daily_revenue[] = $row ? (float)$row['revenue'] : 0;
    $daily_profit[]  = $row ? round((float)$row['revenue'] - (float)$row['cost'], 2) : 0;
    $daily_orders[]  = $row ? (int)$row['orders'] : 0;
}
// Weekly: last 8 weeks
$weekly_stmt = $db->query("
    SELECT YEARWEEK(created_at, 1) as yw,
           MIN(DATE(created_at)) as week_start,
           SUM(price_paid_inr) as revenue,
           SUM(price_cost_inr) as cost,
           COUNT(*) as orders
    FROM purchases
    WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
    GROUP BY yw ORDER BY yw ASC
");
$weekly_raw = $weekly_stmt->fetchAll();
$weekly_labels = $weekly_revenue = $weekly_profit = $weekly_orders = [];
foreach ($weekly_raw as $row) {
    $weekly_labels[]  = 'Wk ' . date('d M', strtotime($row['week_start']));
    $weekly_revenue[] = (float)$row['revenue'];
    $weekly_profit[]  = round((float)$row['revenue'] - (float)$row['cost'], 2);
    $weekly_orders[]  = (int)$row['orders'];
}
// Monthly: last 6 months
$monthly_stmt = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as mo,
           SUM(price_paid_inr) as revenue,
           SUM(price_cost_inr) as cost,
           COUNT(*) as orders
    FROM purchases
    WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mo ORDER BY mo ASC
");
$monthly_raw = $monthly_stmt->fetchAll();
$monthly_labels = $monthly_revenue = $monthly_profit = $monthly_orders = [];
foreach ($monthly_raw as $row) {
    $monthly_labels[]  = date('M Y', strtotime($row['mo'] . '-01'));
    $monthly_revenue[] = (float)$row['revenue'];
    $monthly_profit[]  = round((float)$row['revenue'] - (float)$row['cost'], 2);
    $monthly_orders[]  = (int)$row['orders'];
}
// Fetch Sections & Products for Manage Offers
$sections_list = [];
try {
    $sections_list = $db->query("SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.section_id = s.id) as house_count FROM sections s ORDER BY s.display_order ASC, s.id DESC")->fetchAll();
} catch (Exception $e) {}

$view_section_id = isset($_GET['view_section']) ? (int)$_GET['view_section'] : 0;
$active_section_data = null;
$houses_list = [];

if ($view_section_id > 0) {
    $sec_stmt = $db->prepare("SELECT * FROM sections WHERE id = ?");
    $sec_stmt->execute([$view_section_id]);
    $active_section_data = $sec_stmt->fetch();
    
    if ($active_section_data) {
        $house_stmt = $db->prepare("SELECT * FROM products WHERE section_id = ? ORDER BY id DESC");
        $house_stmt->execute([$view_section_id]);
        $houses_list = $house_stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Admin Panel - Mango Number</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png" />
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>

    <style>
        :root {
            --primary-bg: #fffbf5;
            --accent-orange: #ff5e36;
            --accent-yellow: #fca834;
            --gradient-accent: linear-gradient(135deg, var(--accent-orange), var(--accent-yellow));
            --text-dark: #231b15;
            --text-light: #6e5e54;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg) !important;
        }

        .brand-text {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Sidebar custom coloring */
        .bg-menu-theme {
            background-color: #1e1b19 !important;
            color: #d1c5bc !important;
        }

        .bg-menu-theme .menu-item.active > .menu-link {
            background: var(--gradient-accent) !important;
            color: #ffffff !important;
            font-weight: 600;
        }

        .bg-menu-theme .menu-link {
            color: #9c8e85 !important;
        }

        .bg-menu-theme .menu-item:not(.active) .menu-link:hover {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.04) !important;
        }

        .layout-navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.8) !important;
            border-bottom: 1px solid rgba(220, 200, 190, 0.4);
        }

        /* Widgets stats */
        .stat-card {
            border: 1px solid rgba(220, 200, 190, 0.4);
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 4px 15px rgba(25, 10, 5, 0.01);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 94, 54, 0.04);
        }

        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        /* Admin Action Tabs */
        .admin-section {
            display: none;
        }
        .admin-section.active {
            display: block;
        }

        /* Section Management & Cards Redesign */
        .mn-section-card {
            background: #ffffff;
            border-radius: 22px;
            border: 1.5px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .mn-section-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(255, 94, 54, 0.14);
            border-color: rgba(255, 94, 54, 0.3);
        }

        .mn-section-card .mn-card-header-bar {
            height: 4px;
            width: 100%;
            background: linear-gradient(90deg, #ff5e36, #fca834);
            opacity: 0;
            transition: opacity 0.3s ease;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
        }

        .mn-section-card:hover .mn-card-header-bar {
            opacity: 1;
        }

        .mn-icon-box {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 26px;
            transition: transform 0.3s ease;
            overflow: hidden;
        }

        .mn-section-card:hover .mn-icon-box {
            transform: scale(1.08) rotate(-3deg);
        }

        .mn-btn-glow {
            background: linear-gradient(135deg, #ff5e36, #fca834);
            color: #ffffff !important;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            box-shadow: 0 6px 18px rgba(255, 94, 54, 0.3);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .mn-btn-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(255, 94, 54, 0.45);
            color: #ffffff !important;
        }

        .mn-btn-open-section {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #ffffff !important;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            padding: 11px 18px;
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .mn-btn-open-section:hover {
            background: linear-gradient(135deg, #ff5e36, #fca834);
            box-shadow: 0 8px 22px rgba(255, 94, 54, 0.35);
            color: #ffffff !important;
            transform: translateY(-2px);
        }

        .mn-btn-delete-section {
            width: 44px !important;
            height: 44px !important;
            background: #fef2f2 !important;
            color: #dc2626 !important;
            border: 1.5px solid #fecaca !important;
            border-radius: 14px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.12) !important;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
            cursor: pointer !important;
            flex-shrink: 0 !important;
        }

        .mn-btn-delete-section:hover {
            background: #dc2626 !important;
            color: #ffffff !important;
            border-color: #dc2626 !important;
            transform: translateY(-2px) scale(1.05) !important;
            box-shadow: 0 6px 18px rgba(220, 38, 38, 0.4) !important;
        }

        .mn-btn-delete-section i {
            color: #dc2626 !important;
            font-size: 19px !important;
            line-height: 1 !important;
            transition: color 0.2s ease !important;
        }

        .mn-btn-delete-section:hover i {
            color: #ffffff !important;
        }

        /* Drag & Drop Reordering Styles */
        .mn-section-col {
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .mn-section-col.is-dragging {
            opacity: 0.45;
            transform: scale(0.96);
        }

        .mn-section-col.drag-over-target .mn-section-card {
            border: 2.5px dashed #ff5e36 !important;
            box-shadow: 0 0 24px rgba(255, 94, 54, 0.35) !important;
            background: #fff8f5 !important;
        }

        .mn-drag-handle {
            cursor: grab;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 5px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .mn-drag-handle:hover {
            background: #fff7ed;
            border-color: #ffedd5;
            color: #ea580c;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(234, 88, 12, 0.18);
        }

        .mn-drag-handle:active {
            cursor: grabbing;
            transform: scale(0.96);
        }

        /* Screenshot light-box modal */
        .screenshot-thumb {
            width: 80px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(220, 200, 190, 0.5);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .screenshot-thumb:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .lightbox-overlay {
            position: fixed;
            top: 0; bottom: 0; left: 0; right: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            z-index: 1100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .lightbox-card {
            background: #ffffff;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            padding: 20px;
            position: relative;
            text-align: center;
        }

        .lightbox-img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
            border: 1px solid rgba(220, 200, 190, 0.4);
            margin-bottom: 15px;
        }

        /* Flexbox sidebar layout to keep profile widget at bottom */
        aside#layout-menu {
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            height: 100vh !important;
        }
        
        aside#layout-menu .menu-inner {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
        }

        /* Sidebar styling override for dark aesthetics */
        .bg-menu-theme {
            background-color: #18120e !important;
            color: #d1c5bc !important;
        }
        
        .bg-menu-theme .menu-inner-shadow {
            background: linear-gradient(#18120e, rgba(24, 18, 14, 0));
        }

        .bg-menu-theme .menu-link {
            color: #8c7e75 !important;
        }

        .bg-menu-theme .menu-item.active > .menu-link {
            background: var(--gradient-accent) !important;
            color: #ffffff !important;
            font-weight: 600;
        }

        .bg-menu-theme .menu-item:not(.active) .menu-link:hover {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.05) !important;
        }

        /* Profile Widget styling at the bottom of sidebar */
        .sidebar-profile-card {
            background: linear-gradient(135deg, rgba(46, 125, 50, 0.15) 0%, rgba(255, 140, 0, 0.15) 100%);
            border: 1.5px solid rgba(255, 140, 0, 0.3);
            border-radius: 20px;
            padding: 16px;
            margin: 15px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            position: relative;
            z-index: 10;
        }

        .sidebar-profile-name {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
            text-transform: capitalize;
            letter-spacing: -0.2px;
        }

        .sidebar-profile-email-box {
            background: rgba(24, 18, 14, 0.6);
            border: 1.5px dashed rgba(46, 125, 50, 0.45);
            border-radius: 12px;
            padding: 6px 10px;
            margin-bottom: 12px;
            word-break: break-all;
            display: inline-block;
            width: 100%;
        }

        .sidebar-profile-email {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
        }

        .sidebar-profile-spend-box {
            font-size: 11.5px;
            color: rgba(209, 197, 188, 0.85);
            margin-bottom: 14px;
            font-weight: 600;
        }

        .sidebar-profile-spend-val {
            font-family: 'Outfit', sans-serif;
            font-size: 13.5px;
            font-weight: 800;
            color: #4caf50;
            display: inline-block;
            margin-left: 4px;
        }

        .sidebar-profile-logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #FF8C00 0%, #FFA726 100%);
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(255, 140, 0, 0.25);
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .sidebar-profile-logout-btn:hover {
            transform: translateY(-1.5px);
            box-shadow: 0 6px 16px rgba(255, 140, 0, 0.4);
            background: linear-gradient(135deg, #e65100 0%, #ff8f00 100%);
        }

        /* Responsive Mobile Layout Adjustments */
        @media (max-width: 1199px) {
            /* Enable toggling transition and keep profile pinned at bottom on mobile/tablet */
            aside#layout-menu {
                transition: transform 0.3s ease-in-out !important;
                height: 100dvh !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                overflow: hidden !important;
            }
            aside#layout-menu .menu-inner {
                flex: 1 1 auto !important;
                overflow-y: auto !important;
            }
            /* Shift navbar title to make space for the floating hamburger button */
            #navbar-title-text {
                padding-left: 45px !important;
            }
            /* Compact profile widget on mobile to prevent cutoffs and scrolling */
            .sidebar-profile-card {
                margin: 10px 12px min(25px, env(safe-area-inset-bottom)) 12px !important;
                padding: 10px 12px !important;
                border-radius: 14px !important;
            }
            .sidebar-profile-name {
                font-size: 13.5px !important;
                margin-bottom: 4px !important;
            }
            .sidebar-profile-email-box {
                padding: 4px 8px !important;
                margin-bottom: 6px !important;
                border-radius: 8px !important;
            }
            .sidebar-profile-email {
                font-size: 10px !important;
            }
            .sidebar-profile-spend-box {
                font-size: 11px !important;
                margin-bottom: 8px !important;
            }
            .sidebar-profile-spend-val {
                font-size: 11.5px !important;
            }
            .sidebar-profile-logout-btn {
                padding: 8px !important;
                font-size: 11.5px !important;
                border-radius: 8px !important;
            }
        }

        @media (max-width: 767px) {
            /* Table formatting and container optimizations for mobile */
            .container-xxl {
                padding-left: 14px !important;
                padding-right: 14px !important;
            }
            .stat-card {
                margin-bottom: 14px;
            }
            .table th, .table td {
                padding: 12px 14px !important;
                font-size: 13px !important;
            }
            /* Modals safety padding */
            .lightbox-overlay {
                padding: 14px !important;
            }
            .lightbox-overlay .card {
                padding: 20px !important;
                max-height: 90vh;
                overflow-y: auto;
            }
        }

        .chart-period-btn {
            padding: 6px 16px;
            border-radius: 20px;
            border: 1.5px solid rgba(220,200,190,0.6);
            background: transparent;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .chart-period-btn:hover { border-color: var(--accent-orange); color: var(--accent-orange); }
        .chart-period-btn.active {
            background: var(--gradient-accent);
            border-color: transparent;
            color: #fff;
        }
    </style>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu Sidebar -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo" style="justify-content: space-between; padding: 25px 20px;">
                    <a href="index.php" class="brand" style="text-decoration:none; display:flex; align-items:center; gap:8px;">
                        <img src="assets/img/logo.png" alt="Mango Number Logo" style="width: 32px; height: 32px; object-fit: contain; border-radius: 6px;">
                        <span class="brand-text">Mango Admin</span>
                    </a>
                    <!-- Mobile Close Sidebar button -->
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none" style="padding: 0;">
                        <i class="bx bx-chevron-left bx-sm align-middle" style="color: #8c7e75; font-size: 26px;"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Pending Approvals -->
                    <li class="menu-item <?= $active_tab === 'approvals' ? 'active' : '' ?>" id="menu-approvals">
                        <a href="javascript:void(0);" onclick="switchSection('approvals')" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-check-circle"></i>
                            <div data-i18n="Approvals">Pending Verifications (<?= $pending_orders_count ?>)</div>
                        </a>
                    </li>

                    <!-- Catalog Management -->
                    <li class="menu-item <?= $active_tab === 'catalog' ? 'active' : '' ?>" id="menu-catalog">
                        <a href="javascript:void(0);" onclick="switchSection('catalog')" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-edit"></i>
                            <div data-i18n="Catalog">Manage Offers</div>
                        </a>
                    </li>

                    <!-- Support tickets -->
                    <li class="menu-item <?= $active_tab === 'complaints' ? 'active' : '' ?>" id="menu-complaints">
                        <a href="javascript:void(0);" onclick="switchSection('complaints')" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-message-error"></i>
                            <div data-i18n="Complaints">Unresolved Complaints (<?= $unresolved_complaints_count ?>)</div>
                        </a>
                    </li>

                    <!-- Registered Users -->
                    <li class="menu-item <?= $active_tab === 'users' ? 'active' : '' ?>" id="menu-users">
                        <a href="javascript:void(0);" onclick="switchSection('users')" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Users">Registered Users</div>
                        </a>
                    </li>

                    <!-- Revenue Analytics -->
                    <li class="menu-item <?= $active_tab === 'revenue' ? 'active' : '' ?>" id="menu-revenue">
                        <a href="javascript:void(0);" onclick="switchSection('revenue')" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Revenue">Revenue Analytics</div>
                        </a>
                    </li>

                    <!-- System Settings -->
                    <li class="menu-item <?= $active_tab === 'settings' ? 'active' : '' ?>" id="menu-settings">
                        <a href="javascript:void(0);" onclick="switchSection('settings')" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cog"></i>
                            <div data-i18n="Settings">System Settings</div>
                        </a>
                    </li>

                    <!-- Go to Landing Page -->
                    <li class="menu-item mt-4">
                        <a href="index.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home"></i>
                            <div data-i18n="Landing">Go To Homepage</div>
                        </a>
                    </li>
                </ul>

                <!-- User Profile Card Widget -->
                <div class="sidebar-profile-card">
                    <div class="sidebar-profile-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="sidebar-profile-email-box">
                        <span class="sidebar-profile-email"><?= htmlspecialchars($user_email) ?></span>
                    </div>
                    <div class="sidebar-profile-spend-box">
                        Role: <span class="sidebar-profile-spend-val">System Admin</span>
                    </div>
                    <a href="logout.php" class="sidebar-profile-logout-btn">
                        <i class="bx bx-power-off"></i> Log Out
                    </a>
                </div>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center" id="layout-navbar" style="position: relative;">
                    <!-- Mobile Menu Toggle Button (Floating styled for absolute reliability) -->
                    <div class="d-xl-none" onclick="toggleMobileSidebar()" style="position: absolute; left: 15px; top: 12px; z-index: 1050; cursor: pointer; display: flex; flex-direction: column; gap: 4px; align-items: center; justify-content: center; background: var(--gradient-accent); width: 40px; height: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(255, 94, 54, 0.3); padding: 10px;">
                        <span style="display: block; width: 20px; height: 2px; background: #ffffff; border-radius: 2px;"></span>
                        <span style="display: block; width: 20px; height: 2px; background: #ffffff; border-radius: 2px;"></span>
                        <span style="display: block; width: 20px; height: 2px; background: #ffffff; border-radius: 2px;"></span>
                    </div>
                    
                    <div class="navbar-nav-right d-flex align-items-center justify-content-between" id="navbar-collapse" style="width: 100%;">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <span class="fw-semibold" id="navbar-title-text" style="font-size: 15px; color: var(--text-dark); transition: padding 0.2s;">
                                    🛠️ Owner Dashboard
                                </span>
                            </div>
                        </div>
                        <!-- Telegram Support Button -->
                        <div class="navbar-nav align-items-center">
                            <a href="https://t.me/nu9rl" target="_blank" class="tg-btn" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: linear-gradient(135deg, #0088cc 0%, #00a8ff 100%); border: 1px solid rgba(255,255,255,0.2); border-radius: 99px; font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; box-shadow: 0 4px 16px rgba(0, 136, 204, 0.35); transition: all 0.25s ease;">
                                <i class="bx bxl-telegram" style="font-size: 18px;"></i>
                                <span>Customer Support</span>
                            </a>
                        </div>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <!-- Success / Error notification bar -->
                        <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success alert-dismissible" role="alert">
                                <?= $success_msg ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger alert-dismissible" role="alert">
                                <?= $error_msg ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Premium Financial Analytics Summary Panel & Widgets Wrapper -->
                        <div id="admin-dashboard-stats" style="<?= $active_tab === 'approvals' ? '' : 'display: none;' ?>">
                            <!-- Premium Financial Analytics Summary Panel -->
                            <div class="row mb-4">
                                <!-- 1. Total Revenue -->
                                <div class="col-md-4 mb-4">
                                    <div class="card stat-card" style="border-left: 5px solid #28a745; height: 100%;">
                                        <div class="card-body d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="d-block mb-1 text-muted" style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Total Revenue (Rate Sold)</span>
                                                <h2 class="card-title mb-0 fw-bold" style="color:#1e7e44; font-family:'Outfit', sans-serif;">₹<?= number_format($total_revenue, 2) ?></h2>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#e5f6ed; color:#1e7e44; font-size:24px; width:50px; height:50px;">📈</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 2. Total Cost / Spend -->
                                <div class="col-md-4 mb-4">
                                    <div class="card stat-card" style="border-left: 5px solid #dc3545; height: 100%;">
                                        <div class="card-body d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="d-block mb-1 text-muted" style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Total Cost (Rate Bought)</span>
                                                <h2 class="card-title mb-0 fw-bold" style="color:#d12e00; font-family:'Outfit', sans-serif;">₹<?= number_format($total_spend, 2) ?></h2>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#ffebe5; color:#d12e00; font-size:24px; width:50px; height:50px;">📉</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 3. Net Profit (Glow / Highlighted) -->
                                <div class="col-md-4 mb-4">
                                    <div class="card stat-card" style="border-left: 5px solid #fca834; background: linear-gradient(135deg, #fffcf5, #ffedd5); height: 100%;">
                                        <div class="card-body d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="d-block mb-1" style="font-size:12px; font-weight:700; color: #a35d00; text-transform:uppercase; letter-spacing:0.5px;">Net Profit Made 🥭</span>
                                                <h2 class="card-title mb-0 fw-bold" style="color:#e05b00; font-family:'Outfit', sans-serif;">₹<?= number_format($net_profit, 2) ?></h2>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:rgba(252, 168, 52, 0.2); color:#e05b00; font-size:24px; width:50px; height:50px;">🔥</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- System Status Widgets -->
                            <div class="row mb-4">
                                <!-- Active Users -->
                                <div class="col-md-4 mb-3">
                                    <div class="card stat-card">
                                        <div class="card-body d-flex align-items-center justify-content-between" style="padding: 15px 25px;">
                                            <div>
                                                <span class="d-block text-muted" style="font-size:12px;">Active Clients</span>
                                                <h4 class="mb-0 fw-bold" style="color:var(--text-dark);"><?= $users_count ?> Users</h4>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#e5f1f6; color:#1e6a7e; width:38px; height:38px; font-size:18px;">👥</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pending Verifications -->
                                <div class="col-md-4 mb-3">
                                    <div class="card stat-card">
                                        <div class="card-body d-flex align-items-center justify-content-between" style="padding: 15px 25px;">
                                            <div>
                                                <span class="d-block text-muted" style="font-size:12px;">Pending Verifications</span>
                                                <h4 class="mb-0 fw-bold" style="color:var(--text-dark);"><?= $pending_orders_count ?> Receipts</h4>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#fff5e6; color:#b36b00; width:38px; height:38px; font-size:18px;">🕒</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Open Complaints -->
                                <div class="col-md-4 mb-3">
                                    <div class="card stat-card">
                                        <div class="card-body d-flex align-items-center justify-content-between" style="padding: 15px 25px;">
                                            <div>
                                                <span class="d-block text-muted" style="font-size:12px;">Open Complaints</span>
                                                <h4 class="mb-0 fw-bold" style="color:var(--text-dark);"><?= $unresolved_complaints_count ?> Tickets</h4>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#ffebe5; color:#d12e00; width:38px; height:38px; font-size:18px;">⚠️</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 1: PENDING TRANSACTION VERIFICATIONS -->
                        <div id="section-approvals" class="admin-section <?= $active_tab === 'approvals' ? 'active' : '' ?>">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-3 mb-2">
                                <h4 class="fw-bold mb-0" style="font-family:'Outfit', sans-serif;">Verify Pending Receipts & UTRs</h4>
                                <div style="max-width: 320px; width: 100%;">
                                    <input type="text" id="utr-search-input" class="form-control form-control-sm" placeholder="🔍 Search UTR or Username..." onkeyup="filterPendingRows()" style="border-radius:10px; border: 1.5px solid rgba(220, 200, 190, 0.4); padding: 8px 14px;">
                                </div>
                            </div>
                            
                            <div class="card" style="border: 1px solid rgba(220, 200, 190, 0.4); border-radius: 16px;">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Client User</th>
                                                <th>Item Requested</th>
                                                <th>Price (INR)</th>
                                                <th>Submitted UTR ID</th>
                                                <th>Proof Screenshot</th>
                                                <th>Verification Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (!empty($pending_purchases)): ?>
                                                <?php foreach ($pending_purchases as $p): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($p['username']) ?></strong></td>
                                                        <td><strong>[<?= htmlspecialchars($p['service_type']) ?>]</strong> <?= htmlspecialchars($p['item_name']) ?></td>
                                                        <td>₹<?= number_format($p['price_paid_inr'], 0) ?></td>
                                                        <td><code style="font-size:14px; font-weight:700; color:var(--text-dark);"><?= htmlspecialchars($p['utr_number']) ?></code></td>
                                                        <td>
                                                            <?php if (!empty($p['screenshot_path'])): ?>
                                                                <img class="screenshot-thumb" src="<?= htmlspecialchars($p['screenshot_path']) ?>" alt="payment receipt proof" onclick="openLightbox('<?= htmlspecialchars($p['screenshot_path']) ?>', '<?= htmlspecialchars($p['utr_number']) ?>')">
                                                            <?php else: ?>
                                                                <span class="text-danger" style="font-size:12px;">No receipt uploaded</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <!-- Quick Action Inline Form for Approving Number -->
                                                            <form action="admin.php" method="POST" class="d-flex align-items-center gap-2" style="max-width:400px;">
                                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                                <input type="hidden" name="active_tab" value="approvals">
                                                                <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                                                                <input class="form-control form-control-sm" type="text" name="virtual_number" placeholder="Enter Virtual Number" style="width: 140px;" required maxlength="12" pattern="^\+?[0-9]+$" oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                                                                <input class="form-control form-control-sm" type="text" name="otp_code" placeholder="Enter SMS OTP" style="width: 100px;" required maxlength="8" pattern="^[a-zA-Z0-9]+$" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')">
                                                                
                                                                <button type="submit" name="action_approve" class="btn btn-sm btn-success">Approve</button>
                                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmOrderRejection(this.form)">Reject</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4 text-muted">No pending receipts to verify. Fantastic!</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: MANAGE OFFERS (SECTIONS & HOUSES) -->
                        <div id="section-catalog" class="admin-section <?= $active_tab === 'catalog' ? 'active' : '' ?>">
                            <?php if ($active_section_data): ?>
                                <!-- SECTION HOUSE DETAIL VIEW -->
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 py-3 mb-3" style="border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 16px !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <a href="admin.php?active_tab=catalog" class="btn btn-sm" style="background: #ffffff; border: 1.5px solid #e2e8f0; color: #475569; font-weight: 600; border-radius: 10px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: all 0.2s ease;">
                                            <i class="bx bx-left-arrow-alt fs-5"></i> Back to Sections
                                        </a>
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <h3 class="fw-bold mb-0" style="font-family:'Outfit', sans-serif; color: #0f172a; font-size: 22px;">
                                                    Houses in <?= htmlspecialchars($active_section_data['name']) ?>
                                                </h3>
                                                <span class="badge" style="background: rgba(255, 94, 54, 0.12); color: var(--accent-orange); font-size: 12px; font-weight: 700; border-radius: 99px; padding: 4px 10px;"><?= count($houses_list) ?> Items</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm px-3 py-2" style="background: linear-gradient(135deg, #ff5e36, #fca834); color: #ffffff; border:none; border-radius:10px; font-weight: 700; font-size: 13px; box-shadow: 0 4px 14px rgba(255, 94, 54, 0.35); display: inline-flex; align-items: center; gap: 6px;" data-bs-toggle="collapse" data-bs-target="#newHouseCollapse">
                                        <i class="bx bx-plus fs-5"></i> New House
                                    </button>
                                </div>

                                <!-- Create New House Form Collapse -->
                                <div class="collapse mb-4" id="newHouseCollapse">
                                    <div class="card p-4" style="border: 1px solid rgba(255, 94, 54, 0.25); border-radius: 16px; background: #ffffff; box-shadow: 0 10px 30px rgba(255,94,54,0.06);">
                                        <h6 class="fw-bold mb-3" style="color: var(--accent-orange); font-family: 'Outfit', sans-serif; font-size: 16px;">
                                            <i class="bx bx-plus-circle me-1"></i> Add New House / Service under <?= htmlspecialchars($active_section_data['name']) ?>
                                        </h6>
                                        <form action="admin.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="active_tab" value="catalog">
                                            <input type="hidden" name="section_id" value="<?= $active_section_data['id'] ?>">
                                            <input type="hidden" name="action_create_house" value="1">
                                            
                                            <div class="row g-3">
                                                <div class="col-md-5">
                                                    <label class="form-label font-weight-bold" style="font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">House / Service Name *</label>
                                                    <input type="text" name="house_name" class="form-control" placeholder="e.g. Canva Premium Lifetime" style="border-radius: 10px; border: 1.5px solid #cbd5e1; padding: 9px 13px;" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label" style="font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Selling Price (INR ₹) *</label>
                                                    <input type="number" step="0.01" name="price_inr" class="form-control" placeholder="150.00" style="border-radius: 10px; border: 1.5px solid #cbd5e1; padding: 9px 13px;" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" style="font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Initial Stock Quantity *</label>
                                                    <input type="number" name="stock_quantity" class="form-control" placeholder="10" value="10" style="border-radius: 10px; border: 1.5px solid #cbd5e1; padding: 9px 13px;" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label" style="font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Badge Tag</label>
                                                    <input type="text" name="badge" class="form-control" placeholder="e.g. HOT, INSTANT, POPULAR" style="border-radius: 10px; border: 1.5px solid #cbd5e1; padding: 9px 13px;">
                                                </div>
                                                <div class="col-md-9">
                                                    <label class="form-label font-weight-bold" style="font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Upload Custom House Image/Icon (Choose, Drag & Drop or Ctrl+V)</label>
                                                    <div class="mn-image-dropzone-wrap">
                                                        <div class="mn-image-dropzone p-3 text-center" style="border: 2px dashed #cbd5e1; border-radius: 14px; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; position: relative;" onclick="this.querySelector('input[type=file]').click()">
                                                            <input type="file" name="house_image_file" accept="image/*" class="d-none" onchange="mnHandleFileSelect(this)">
                                                            <input type="hidden" name="pasted_base64_house_image_file" value="">
                                                            
                                                            <div class="mn-dropzone-prompt">
                                                                <div class="d-inline-flex align-items-center justify-content-center mb-1" style="width: 32px; height: 32px; border-radius: 10px; background: rgba(255,94,54,0.1); color: #ff5e36;">
                                                                    <i class="bx bx-cloud-upload fs-5"></i>
                                                                </div>
                                                                <div style="font-size: 12px; color: #334155; font-weight: 600;">
                                                                    Choose file, Drag & Drop, or press <span class="badge bg-warning text-dark border fw-bold" style="font-size: 10px;">Ctrl + V / Cmd + V to Paste</span>
                                                                </div>
                                                            </div>

                                                            <div class="mn-dropzone-preview d-none align-items-center justify-content-center gap-2">
                                                                <img class="mn-preview-img" src="" style="width: 42px; height: 42px; object-fit: contain; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; padding: 2px;">
                                                                <div class="text-start">
                                                                    <div class="fw-bold text-truncate mn-preview-filename" style="max-width: 180px; font-size: 12px; color: #0f172a;">Image Selected</div>
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold mn-preview-source" style="font-size: 10px;">Ready</span>
                                                                </div>
                                                                <button type="button" class="btn btn-sm btn-outline-danger ms-auto p-0 px-1.5" style="border-radius: 6px;" onclick="event.stopPropagation(); mnClearDropzone(this.closest('.mn-image-dropzone-wrap'))">
                                                                    <i class="bx bx-x fs-5"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-3 text-end">
                                                    <button type="submit" class="btn btn-primary px-4 py-2" style="background: var(--gradient-accent); border:none; border-radius:10px; font-weight: 700;">Save New House</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Houses List Table -->
                                <div class="card" style="border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 18px; background: #ffffff; box-shadow: 0 12px 36px rgba(0, 0, 0, 0.04); overflow: hidden;">
                                    <!-- Table Top Bar with Quick Search & Bulk Actions -->
                                    <div class="px-4 py-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bx bx-list-ul fs-4 text-primary"></i>
                                            <span class="fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">Manage Service Houses</span>
                                            <span class="badge rounded-pill bg-label-primary ms-1"><?= count($houses_list) ?> Total</span>
                                        </div>

                                        <!-- Bulk Delete Action Form & Button -->
                                        <form id="form-bulk-delete-houses" action="admin.php" method="POST" onsubmit="return mnConfirmBulkDeleteHouses(event);" style="margin:0;" class="d-inline-block me-auto ms-2">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="active_tab" value="catalog">
                                            <input type="hidden" name="section_id" value="<?= $active_section_data['id'] ?>">
                                            <input type="hidden" name="action_bulk_delete_houses" value="1">
                                            
                                            <button type="submit" id="btnBulkDeleteHouses" class="btn btn-sm px-3 py-2 d-none" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: #ffffff; border: none; border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35); transition: all 0.2s ease;">
                                                <img src="assets/img/delete_icon.png" style="width: 16px; height: 16px; object-fit: contain;"> 
                                                Delete Selected (<span id="selectedHousesCount">0</span>)
                                            </button>
                                        </form>

                                        <div style="max-width: 280px; width: 100%;">
                                            <div class="input-group input-group-sm" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
                                                <span class="input-group-text bg-white border-0"><i class="bx bx-search text-muted"></i></span>
                                                <input type="text" id="houseSearchInput" onkeyup="filterHousesTable()" class="form-control border-0 shadow-none" placeholder="Search house by name..." style="font-size: 13.5px;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive text-nowrap">
                                        <table class="table table-hover align-middle mb-0" id="housesTable" style="border-collapse: separate; border-spacing: 0;">
                                            <thead>
                                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                                    <th style="width: 44px; padding: 16px 12px 16px 20px; text-align: center;">
                                                        <input type="checkbox" id="selectAllHousesCb" class="form-check-input" style="width: 18px; height: 18px; cursor: pointer; border-color: #cbd5e1;" title="Select All Houses" onchange="toggleSelectAllHouses(this)">
                                                    </th>
                                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; padding: 16px 20px;">House / Service Name</th>
                                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; padding: 16px 20px;">Selling Price (₹)</th>
                                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; padding: 16px 20px;">Available Stock</th>
                                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; padding: 16px 20px;">Status</th>
                                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; padding: 16px 20px; text-align: right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($houses_list)): ?>
                                                    <?php foreach ($houses_list as $h): ?>
                                                        <?php
                                                            $sec_name_lower = strtolower($active_section_data['name'] ?? '');
                                                            $icon_bg = 'rgba(255, 94, 54, 0.1)';
                                                            $icon_color = '#ff5e36';
                                                            $default_icon = 'bx-package';
                                                            if (str_contains($sec_name_lower, 'telegram')) {
                                                                $icon_bg = 'rgba(0, 136, 204, 0.12)'; $icon_color = '#0088cc'; $default_icon = 'bxl-telegram';
                                                            } elseif (str_contains($sec_name_lower, 'whatsapp')) {
                                                                $icon_bg = 'rgba(37, 211, 102, 0.12)'; $icon_color = '#25D366'; $default_icon = 'bxl-whatsapp';
                                                            } elseif (str_contains($sec_name_lower, 'canva')) {
                                                                $icon_bg = 'rgba(125, 42, 232, 0.12)'; $icon_color = '#7d2ae8'; $default_icon = 'bx-paint';
                                                            }
                                                            $icon_val = !empty($h['icon']) ? $h['icon'] : $default_icon;
                                                            $has_custom_image = (!empty($h['icon']) && (str_contains($h['icon'], 'uploads/') || str_contains($h['icon'], 'http')));
                                                        ?>
                                                        <tr class="house-row" style="border-bottom: 1px solid #f1f5f9;">
                                                            <td style="width: 44px; padding: 16px 12px 16px 20px; text-align: center;">
                                                                <input type="checkbox" name="selected_house_ids[]" value="<?= $h['id'] ?>" form="form-bulk-delete-houses" class="form-check-input house-cb" style="width: 18px; height: 18px; cursor: pointer; border-color: #cbd5e1;" onchange="updateBulkDeleteState()">
                                                            </td>
                                                            <td style="padding: 16px 20px;">
                                                                <form id="form-update-house-<?= $h['id'] ?>" action="admin.php" method="POST" enctype="multipart/form-data" style="display:none;">
                                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                                    <input type="hidden" name="active_tab" value="catalog">
                                                                    <input type="hidden" name="section_id" value="<?= $active_section_data['id'] ?>">
                                                                    <input type="hidden" name="product_id" value="<?= $h['id'] ?>">
                                                                    <input type="hidden" name="action_update_house" value="1">
                                                                </form>
                                                                
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <div data-bs-toggle="modal" data-bs-target="#editHouseModal-<?= $h['id'] ?>" style="width: 46px; height: 46px; border-radius: 12px; background: <?= $icon_bg ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1.5px solid rgba(0,0,0,0.06); cursor: pointer; position: relative; overflow: hidden;" title="Click to change house icon/name">
                                                                        <?php if ($has_custom_image): ?>
                                                                            <img src="<?= htmlspecialchars($h['icon']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                                                        <?php else: ?>
                                                                            <i class="bx <?= htmlspecialchars($icon_val) ?> fs-3" style="color: <?= $icon_color ?>;"></i>
                                                                        <?php endif; ?>
                                                                        <div style="position: absolute; inset:0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s;" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0">
                                                                            <i class="bx bx-camera text-white fs-5"></i>
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <strong class="d-block house-title" style="font-size:15px; color: #0f172a; font-family: 'Outfit', sans-serif; font-weight: 700; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#editHouseModal-<?= $h['id'] ?>" title="Click to edit name/icon">
                                                                            <?= htmlspecialchars($h['name']) ?>
                                                                            <i class="bx bx-pencil text-muted ms-1 fs-6" style="opacity: 0.6;"></i>
                                                                        </strong>
                                                                        <?php if (!empty($h['badge'])): ?>
                                                                            <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; font-size:10.5px; font-weight: 800; padding: 3px 8px; border-radius: 5px; text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($h['badge']) ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>

                                                                <!-- EDIT HOUSE MODAL -->
                                                                <div class="modal fade" id="editHouseModal-<?= $h['id'] ?>" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                                                    <div class="modal-dialog modal-dialog-centered">
                                                                        <div class="modal-content" style="border-radius: 18px; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
                                                                            <div class="modal-header border-bottom py-3 px-4">
                                                                                <h5 class="modal-title fw-bold" style="font-family:'Outfit', sans-serif; color: #0f172a;">
                                                                                    <i class="bx bx-edit text-primary me-2"></i> Edit House: <?= htmlspecialchars($h['name']) ?>
                                                                                </h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <form action="admin.php" method="POST" enctype="multipart/form-data">
                                                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                                                <input type="hidden" name="active_tab" value="catalog">
                                                                                <input type="hidden" name="section_id" value="<?= $active_section_data['id'] ?>">
                                                                                <input type="hidden" name="product_id" value="<?= $h['id'] ?>">
                                                                                <input type="hidden" name="action_update_house" value="1">
                                                                                
                                                                                <div class="modal-body p-4">
                                                                                    <div class="mb-3">
                                                                                        <label class="form-label fw-bold small text-uppercase">House / Service Name *</label>
                                                                                        <input type="text" name="house_name" class="form-control" value="<?= htmlspecialchars($h['name']) ?>" required style="border-radius: 10px;">
                                                                                    </div>
                                                                                    <div class="row g-3 mb-3">
                                                                                        <div class="col-6">
                                                                                            <label class="form-label fw-bold small text-uppercase">Selling Price (INR ₹) *</label>
                                                                                            <input type="number" step="0.01" name="price_inr" class="form-control" value="<?= htmlspecialchars($h['price_inr']) ?>" required style="border-radius: 10px;">
                                                                                        </div>
                                                                                        <div class="col-6">
                                                                                            <label class="form-label fw-bold small text-uppercase">Stock Quantity *</label>
                                                                                            <input type="number" name="stock_quantity" class="form-control" value="<?= (int)$h['stock_quantity'] ?>" required style="border-radius: 10px;">
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="row g-3 mb-3">
                                                                                        <div class="col-6">
                                                                                            <label class="form-label fw-bold small text-uppercase">Badge Tag (e.g. HOT, POPULAR)</label>
                                                                                            <input type="text" name="badge" class="form-control" value="<?= htmlspecialchars($h['badge'] ?? '') ?>" placeholder="e.g. INSTANT" style="border-radius: 10px;">
                                                                                        </div>
                                                                                        <div class="col-6">
                                                                                            <label class="form-label fw-bold small text-uppercase">Service Status</label>
                                                                                            <?php $modal_disabled = ($h['status'] === 'inactive' || $h['availability_status'] === 'disabled'); ?>
                                                                                            <select name="status" class="form-select fw-bold" style="border-radius: 10px;">
                                                                                                <option value="active" <?= !$modal_disabled ? 'selected' : '' ?>>🟢 Enabled</option>
                                                                                                <option value="disabled" <?= $modal_disabled ? 'selected' : '' ?>>🔴 Disabled</option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="mb-3">
                                                                                        <label class="form-label fw-bold small text-uppercase">Upload New House Icon / Photo (Choose, Drag & Drop or Ctrl+V)</label>
                                                                                        <div class="mn-image-dropzone-wrap">
                                                                                            <div class="mn-image-dropzone p-3 text-center" style="border: 2px dashed #cbd5e1; border-radius: 14px; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; position: relative;" onclick="this.querySelector('input[type=file]').click()">
                                                                                                <input type="file" name="house_image_file" accept="image/*" class="d-none" onchange="mnHandleFileSelect(this)">
                                                                                                <input type="hidden" name="pasted_base64_house_image_file" value="">
                                                                                                
                                                                                                <div class="mn-dropzone-prompt">
                                                                                                    <div class="d-inline-flex align-items-center justify-content-center mb-1" style="width: 32px; height: 32px; border-radius: 10px; background: rgba(255,94,54,0.1); color: #ff5e36;">
                                                                                                        <i class="bx bx-cloud-upload fs-5"></i>
                                                                                                    </div>
                                                                                                    <div style="font-size: 12px; color: #334155; font-weight: 600;">
                                                                                                        Choose file, Drag & Drop, or press <span class="badge bg-warning text-dark border fw-bold" style="font-size: 10px;">Ctrl + V / Cmd + V to Paste</span>
                                                                                                    </div>
                                                                                                </div>

                                                                                                <div class="mn-dropzone-preview d-none align-items-center justify-content-center gap-2">
                                                                                                    <img class="mn-preview-img" src="" style="width: 42px; height: 42px; object-fit: contain; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; padding: 2px;">
                                                                                                    <div class="text-start">
                                                                                                        <div class="fw-bold text-truncate mn-preview-filename" style="max-width: 180px; font-size: 12px; color: #0f172a;">Image Selected</div>
                                                                                                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold mn-preview-source" style="font-size: 10px;">Ready</span>
                                                                                                    </div>
                                                                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto p-0 px-1.5" style="border-radius: 6px;" onclick="event.stopPropagation(); mnClearDropzone(this.closest('.mn-image-dropzone-wrap'))">
                                                                                                        <i class="bx bx-x fs-5"></i>
                                                                                                    </button>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="modal-footer border-top py-3 px-4">
                                                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Cancel</button>
                                                                                    <button type="submit" class="btn btn-primary" style="background: var(--gradient-accent); border: none; border-radius: 10px; font-weight: 700;">Save Changes</button>
                                                                                </div>
                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td style="padding: 16px 20px;">
                                                                <div class="input-group input-group-sm" style="max-width:140px; border-radius: 10px; overflow: hidden; border: 1.5px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                                                    <span class="input-group-text fw-bold" style="background: #f8fafc; border: none; color: #0f172a; font-size: 14px; padding-right: 6px;">₹</span>
                                                                    <input type="number" step="0.01" name="price_inr" form="form-update-house-<?= $h['id'] ?>" class="form-control form-control-sm fw-bold" value="<?= htmlspecialchars($h['price_inr']) ?>" style="border: none; color: #0f172a; font-size: 14px; padding: 7px 10px;" required>
                                                                </div>
                                                            </td>
                                                            <td style="padding: 16px 20px;">
                                                                <input type="number" name="stock_quantity" form="form-update-house-<?= $h['id'] ?>" class="form-control form-control-sm text-center fw-bold" style="max-width:90px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 14.5px; color: #0f172a; padding: 7px 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);" value="<?= (int)$h['stock_quantity'] ?>" required>
                                                            </td>
                                                            <td style="padding: 16px 20px;">
                                                                <?php 
                                                                    $is_disabled = ($h['status'] === 'inactive' || $h['availability_status'] === 'disabled');
                                                                ?>
                                                                <form action="admin.php" method="POST" style="margin: 0; display: inline-block;">
                                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                                    <input type="hidden" name="active_tab" value="catalog">
                                                                    <input type="hidden" name="section_id" value="<?= $active_section_data['id'] ?>">
                                                                    <input type="hidden" name="product_id" value="<?= $h['id'] ?>">
                                                                    <input type="hidden" name="action_toggle_house_status" value="1">
                                                                    
                                                                    <select name="status" onchange="this.form.submit()" class="form-select form-select-sm fw-bold" style="max-width: 140px; border-radius: 10px; border: 1.5px solid <?= $is_disabled ? 'rgba(239, 68, 68, 0.4)' : 'rgba(16, 185, 129, 0.4)' ?>; background-color: <?= $is_disabled ? 'rgba(239, 68, 68, 0.08)' : 'rgba(16, 185, 129, 0.08)' ?>; color: <?= $is_disabled ? '#b91c1c' : '#047857' ?>; font-size: 13px; padding: 6px 10px; cursor: pointer;">
                                                                        <option value="active" <?= !$is_disabled ? 'selected' : '' ?>>🟢 Enabled</option>
                                                                        <option value="disabled" <?= $is_disabled ? 'selected' : '' ?>>🔴 Disabled</option>
                                                                    </select>
                                                                </form>
                                                            </td>
                                                            <td style="padding: 16px 20px; text-align: right;">
                                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                                    <button type="button" class="btn btn-sm px-2.5 py-2 btn-light" data-bs-toggle="modal" data-bs-target="#editHouseModal-<?= $h['id'] ?>" style="border-radius: 10px; font-weight: 600; font-size: 13px;" title="Edit house details/photo">
                                                                        <i class="bx bx-pencil text-primary"></i> Edit
                                                                    </button>

                                                                    <button type="submit" form="form-update-house-<?= $h['id'] ?>" class="btn btn-sm px-3 py-2" style="background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; border: none; border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 4px 12px rgba(16,185,129,0.25); transition: all 0.2s ease;" title="Save price and stock changes">
                                                                        <i class="bx bx-check fs-5"></i> Save
                                                                    </button>

                                                                    <form action="admin.php" method="POST" onsubmit="return mnConfirmAction(event, 'Are you sure you want to delete <?= htmlspecialchars(addslashes($h['name'])) ?>?');" style="margin: 0;">
                                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                                        <input type="hidden" name="active_tab" value="catalog">
                                                                        <input type="hidden" name="section_id" value="<?= $active_section_data['id'] ?>">
                                                                        <input type="hidden" name="product_id" value="<?= $h['id'] ?>">
                                                                        <input type="hidden" name="action_delete_house" value="1">
                                                                        <button type="submit" class="btn btn-sm px-3 py-2" style="background: rgba(239, 68, 68, 0.08); color: #dc2626; border: 1.5px solid rgba(239, 68, 68, 0.25); border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease;" title="Delete this house item">
                                                                            <img src="assets/img/delete_icon.png" style="width: 16px; height: 16px; object-fit: contain;"> Delete
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-5">
                                                            <i class="bx bx-folder-open display-4 mb-2 text-muted opacity-50"></i>
                                                            <p class="mb-0 fw-bold">No Houses / Services found in this Section.</p>
                                                            <small>Click <strong>+ New House</strong> above to create your first service!</small>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <script>
                                function filterHousesTable() {
                                    const input = document.getElementById('houseSearchInput');
                                    if (!input) return;
                                    const filter = input.value.toLowerCase().trim();
                                    const rows = document.querySelectorAll('.house-row');
                                    rows.forEach(row => {
                                        const title = row.querySelector('.house-title')?.textContent.toLowerCase() || '';
                                        if (title.includes(filter)) {
                                            row.style.display = '';
                                        } else {
                                            row.style.display = 'none';
                                        }
                                    });
                                }
                                </script>

                            <?php else: ?>
                                <!-- OVERVIEW SECTIONS LIST VIEW -->
                                <div class="card p-4 mb-4" style="border: 1px solid rgba(255, 94, 54, 0.15); border-radius: 20px; background: linear-gradient(135deg, #ffffff 0%, #fff7f2 100%); box-shadow: 0 10px 30px rgba(255, 94, 54, 0.05); position: relative; overflow: hidden;">
                                    <div style="position: absolute; right: -20px; top: -20px; width: 140px; height: 140px; background: radial-gradient(circle, rgba(255,94,54,0.12) 0%, rgba(255,255,255,0) 70%); border-radius: 50%; pointer-events: none;"></div>
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 position-relative" style="z-index: 1;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div style="width: 58px; height: 58px; border-radius: 18px; background: #ffffff; border: 1.5px solid rgba(255, 94, 54, 0.25); display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 22px rgba(255, 94, 54, 0.15); flex-shrink: 0; overflow: hidden; padding: 4px;">
                                                <img src="assets/img/offers_icon.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 14px;">
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <h3 class="fw-bold mb-0" style="font-family:'Outfit', sans-serif; color: #0f172a; font-size: 22px; letter-spacing: -0.3px;">Manage Offers & Sections</h3>
                                                    <span class="badge" style="background: rgba(255, 94, 54, 0.12); color: #ff5e36; font-size: 11px; font-weight: 800; border-radius: 20px; padding: 4px 10px; border: 1px solid rgba(255,94,54,0.2);">
                                                        <?= count($sections_list ?? []) ?> CATEGORIES
                                                    </span>
                                                </div>
                                                <p class="text-muted small mb-0" style="font-size: 13.5px; color: #64748b;">Create, organize, and customize multi-level service categories for your customers.</p>
                                            </div>
                                        </div>
                                        <button class="btn px-3.5 py-2.5 mn-btn-glow" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px;" data-bs-toggle="collapse" data-bs-target="#newSectionCollapse">
                                            <i class="bx bx-plus-circle fs-5"></i> New Section
                                        </button>
                                    </div>
                                </div>

                                <!-- Create New Section Form Collapse -->
                                <div class="collapse mb-4" id="newSectionCollapse">
                                    <div class="card p-4" style="border: 1.5px solid rgba(255, 94, 54, 0.25); border-radius: 20px; background: #ffffff; box-shadow: 0 14px 40px rgba(255, 94, 54, 0.08); position: relative; overflow: hidden;">
                                        <div style="height: 4px; background: linear-gradient(90deg, #ff5e36, #fca834); position: absolute; top:0; left:0; right:0;"></div>
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h6 class="fw-bold mb-0" style="color: var(--accent-orange); font-family: 'Outfit', sans-serif; font-size: 16.5px; display: inline-flex; align-items: center; gap: 8px;">
                                                <i class="bx bx-folder-plus fs-4"></i> Create New Section Category
                                            </h6>
                                            <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#newSectionCollapse" aria-label="Close"></button>
                                        </div>
                                        <form action="admin.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="active_tab" value="catalog">
                                            <input type="hidden" name="action_create_section" value="1">
                                            
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label font-weight-bold" style="font-size: 11.5px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Section Name *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text" style="background: #f8fafc; border-color: #cbd5e1; border-top-left-radius: 12px; border-bottom-left-radius: 12px;"><i class="bx bx-tag text-muted"></i></span>
                                                        <input type="text" name="section_name" class="form-control" placeholder="e.g. WhatsApp Numbers, Canva Premium" style="border-top-right-radius: 12px; border-bottom-right-radius: 12px; border-color: #cbd5e1; padding: 10px 14px;" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label font-weight-bold" style="font-size: 11.5px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Section Icon Photo Upload (Choose, Drag & Drop or Ctrl+V)</label>
                                                    <div class="mn-image-dropzone-wrap">
                                                        <div class="mn-image-dropzone p-3 text-center" style="border: 2px dashed #cbd5e1; border-radius: 14px; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; position: relative;" onclick="this.querySelector('input[type=file]').click()">
                                                            <input type="file" id="section_image_file" name="section_image_file" accept="image/*" class="d-none" onchange="mnHandleFileSelect(this)">
                                                            <input type="hidden" name="pasted_base64_section_image_file" value="">
                                                            
                                                            <div class="mn-dropzone-prompt">
                                                                <div class="d-inline-flex align-items-center justify-content-center mb-1" style="width: 32px; height: 32px; border-radius: 10px; background: rgba(255,94,54,0.1); color: #ff5e36;">
                                                                    <i class="bx bx-cloud-upload fs-5"></i>
                                                                </div>
                                                                <div style="font-size: 12px; color: #334155; font-weight: 600;">
                                                                    Choose file, Drag & Drop, or press <span class="badge bg-warning text-dark border fw-bold" style="font-size: 10px;">Ctrl + V / Cmd + V to Paste</span>
                                                                </div>
                                                            </div>

                                                            <div class="mn-dropzone-preview d-none align-items-center justify-content-center gap-2">
                                                                <img class="mn-preview-img" src="" style="width: 42px; height: 42px; object-fit: contain; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; padding: 2px;">
                                                                <div class="text-start">
                                                                    <div class="fw-bold text-truncate mn-preview-filename" style="max-width: 180px; font-size: 12px; color: #0f172a;">Image Selected</div>
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold mn-preview-source" style="font-size: 10px;">Ready</span>
                                                                </div>
                                                                <button type="button" class="btn btn-sm btn-outline-danger ms-auto p-0 px-1.5" style="border-radius: 6px;" onclick="event.stopPropagation(); mnClearDropzone(this.closest('.mn-image-dropzone-wrap'))">
                                                                    <i class="bx bx-x fs-5"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label" style="font-size: 11.5px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Description (Optional)</label>
                                                    <input type="text" name="section_description" class="form-control" placeholder="Short summary of items available in this section" style="border-radius: 12px; border: 1.5px solid #cbd5e1; padding: 10px 14px;">
                                                </div>
                                                <div class="col-md-4 align-self-end">
                                                    <button type="submit" class="btn mn-btn-glow w-100 py-2.5" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px;">
                                                        <i class="bx bx-check-circle fs-5"></i> Create Section
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Sections Cards Grid with Drag & Drop -->
                                <div class="row g-4" id="sectionsGrid">
                                    <?php if (!empty($sections_list)): ?>
                                        <?php foreach ($sections_list as $sec): ?>
                                            <?php
                                                $sec_has_image = (!empty($sec['icon']) && (str_contains($sec['icon'], 'uploads/') || str_contains($sec['icon'], 'http')));
                                                $sec_name_lower = strtolower($sec['name'] ?? '');
                                                
                                                $brand_icon_bg = 'linear-gradient(135deg, #ff5e36, #ff8a1f)';
                                                $brand_shadow = 'rgba(255, 94, 54, 0.28)';
                                                $brand_icon = 'bx-layer';
                                                $brand_badge_bg = 'rgba(255, 94, 54, 0.08)';
                                                $brand_badge_color = '#e04f26';
                                                $brand_png = null;
                                                
                                                if (str_contains($sec_name_lower, 'telegram')) {
                                                    $brand_icon_bg = 'linear-gradient(135deg, #0088cc, #00a8ff)';
                                                    $brand_shadow = 'rgba(0, 136, 204, 0.3)';
                                                    $brand_icon = 'bxl-telegram';
                                                    $brand_badge_bg = 'rgba(0, 136, 204, 0.09)';
                                                    $brand_badge_color = '#0088cc';
                                                    if (file_exists(__DIR__ . '/assets/img/telegram_icon.png')) {
                                                        $brand_png = 'assets/img/telegram_icon.png';
                                                    }
                                                } elseif (str_contains($sec_name_lower, 'whatsapp')) {
                                                    $brand_icon_bg = 'linear-gradient(135deg, #25D366, #128C7E)';
                                                    $brand_shadow = 'rgba(37, 211, 102, 0.3)';
                                                    $brand_icon = 'bxl-whatsapp';
                                                    $brand_badge_bg = 'rgba(37, 211, 102, 0.09)';
                                                    $brand_badge_color = '#059669';
                                                    if (file_exists(__DIR__ . '/assets/img/whatsapp_icon.png')) {
                                                        $brand_png = 'assets/img/whatsapp_icon.png';
                                                    }
                                                } elseif (str_contains($sec_name_lower, 'canva')) {
                                                    $brand_icon_bg = 'linear-gradient(135deg, #7d2ae8, #00c4cc)';
                                                    $brand_shadow = 'rgba(125, 42, 232, 0.3)';
                                                    $brand_icon = 'bx-paint';
                                                    $brand_badge_bg = 'rgba(125, 42, 232, 0.09)';
                                                    $brand_badge_color = '#7d2ae8';
                                                    if (file_exists(__DIR__ . '/assets/img/canva_icon.png')) {
                                                        $brand_png = 'assets/img/canva_icon.png';
                                                    }
                                                } elseif (str_contains($sec_name_lower, 'otp') || str_contains($sec_name_lower, 'number')) {
                                                    $brand_icon_bg = 'linear-gradient(135deg, #ff5e36, #ff8a1f)';
                                                    $brand_shadow = 'rgba(255, 94, 54, 0.3)';
                                                    $brand_icon = 'bx-phone-call';
                                                }

                                                $icon_val = !empty($sec['icon']) ? $sec['icon'] : $brand_icon;
                                            ?>
                                            <div class="col-md-6 col-lg-4 mn-section-col" draggable="true" data-section-id="<?= $sec['id'] ?>">
                                                <div class="mn-section-card h-100 p-4">
                                                    <div class="mn-card-header-bar"></div>
                                                    <div class="d-flex align-items-start justify-content-between mb-3 pt-1">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <?php if ($sec_has_image): ?>
                                                                <div class="mn-icon-box" style="background: #ffffff; border: 1.5px solid rgba(15, 23, 42, 0.08); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);">
                                                                    <img src="<?= htmlspecialchars($sec['icon']) ?>" style="width:100%; height:100%; object-fit:cover; border-radius: 16px;">
                                                                </div>
                                                            <?php elseif (!empty($brand_png)): ?>
                                                                <div class="mn-icon-box" style="background: #ffffff; border: 1.5px solid rgba(15, 23, 42, 0.08); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);">
                                                                    <img src="<?= $brand_png ?>" style="width:100%; height:100%; object-fit:contain; padding: 4px;">
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="mn-icon-box" style="background: <?= $brand_icon_bg ?>; box-shadow: 0 8px 20px <?= $brand_shadow ?>;">
                                                                    <i class="bx <?= htmlspecialchars($icon_val) ?> text-white fs-2"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <h5 class="fw-bold mb-1" style="font-size: 17.5px; font-family:'Outfit', sans-serif; color: #0f172a; line-height: 1.2;"><?= htmlspecialchars($sec['name']) ?></h5>
                                                                <span class="badge" style="background: <?= $brand_badge_bg ?>; color: <?= $brand_badge_color ?>; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; text-transform: uppercase; letter-spacing: 0.3px;">
                                                                    <i class="bx bx-package"></i> <?= (int)$sec['house_count'] ?> Houses / Services
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="mn-drag-handle" title="Click and drag to reorder section">
                                                            <i class="bx bx-grid-vertical fs-5" style="margin-right: -2px; opacity: 0.7;"></i>
                                                            <span>Reorder</span>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($sec['description'])): ?>
                                                        <p class="text-muted small mb-4" style="font-size: 13.5px; line-height: 1.55; color: #64748b; min-height: 40px; flex-grow: 1;"><?= htmlspecialchars($sec['description']) ?></p>
                                                    <?php else: ?>
                                                        <div class="flex-grow-1 mb-3"></div>
                                                    <?php endif; ?>

                                                    <div class="d-flex align-items-center justify-content-between pt-3 border-top gap-2" style="border-top-color: rgba(0,0,0,0.06) !important;">
                                                        <a href="admin.php?active_tab=catalog&view_section=<?= $sec['id'] ?>" class="mn-btn-open-section flex-grow-1 text-decoration-none">
                                                            <i class="bx bx-folder-open text-warning fs-5"></i>
                                                            <span>Open Section</span>
                                                            <i class="bx bx-chevron-right fs-5 ms-auto opacity-75"></i>
                                                        </a>

                                                        <form action="admin.php" method="POST" onsubmit="return mnConfirmAction(event, 'Deleting this Section will delete all houses inside it! Continue?');" style="margin: 0;">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="active_tab" value="catalog">
                                                            <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                                                            <input type="hidden" name="action_delete_section" value="1">
                                                            <button type="submit" class="btn mn-btn-delete-section" title="Delete Section">
                                                                <img src="assets/img/delete_icon.png" style="width: 24px; height: 24px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(220, 38, 38, 0.2));">
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <div class="card p-5 text-center text-muted" style="border: 2px dashed rgba(255, 94, 54, 0.25); border-radius: 20px; background: #ffffff; box-shadow: 0 10px 30px rgba(0,0,0,0.02);">
                                                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(255,94,54,0.1); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                                                    <i class="bx bx-folder-plus text-warning display-5"></i>
                                                </div>
                                                <h5 class="fw-bold" style="color: #0f172a; font-family: 'Outfit', sans-serif;">No Offer Sections Created Yet</h5>
                                                <p class="mb-3 text-muted" style="max-width: 480px; margin: 0 auto;">Click on <strong>+ New Section</strong> above to create your first category (e.g., Buy Numbers, Canva Premium, Telegram Services).</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Reorder Toast Notification -->
                                <div id="reorderToast" class="toast align-items-center text-white bg-success border-0 position-fixed bottom-0 end-0 m-4" role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 9999; border-radius: 14px; box-shadow: 0 10px 30px rgba(16,185,129,0.3);">
                                    <div class="d-flex">
                                        <div class="toast-body d-flex align-items-center gap-2 font-weight-bold" style="font-size: 14px;">
                                            <i class="bx bx-check-circle fs-4"></i> Section order saved successfully!
                                        </div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                    </div>
                                </div>

                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const grid = document.getElementById('sectionsGrid');
                                    if (!grid) return;

                                    let draggedItem = null;

                                    grid.addEventListener('dragstart', function(e) {
                                        const target = e.target.closest('.mn-section-col');
                                        if (!target) return;
                                        draggedItem = target;
                                        target.classList.add('is-dragging');
                                        e.dataTransfer.effectAllowed = 'move';
                                        e.dataTransfer.setData('text/plain', target.getAttribute('data-section-id'));
                                    });

                                    grid.addEventListener('dragover', function(e) {
                                        e.preventDefault();
                                        e.dataTransfer.dropEffect = 'move';
                                        const target = e.target.closest('.mn-section-col');
                                        if (target && target !== draggedItem) {
                                            document.querySelectorAll('.mn-section-col').forEach(col => col.classList.remove('drag-over-target'));
                                            target.classList.add('drag-over-target');
                                        }
                                    });

                                    grid.addEventListener('dragleave', function(e) {
                                        const target = e.target.closest('.mn-section-col');
                                        if (target) {
                                            target.classList.remove('drag-over-target');
                                        }
                                    });

                                    grid.addEventListener('drop', function(e) {
                                        e.preventDefault();
                                        document.querySelectorAll('.mn-section-col').forEach(col => col.classList.remove('drag-over-target'));
                                        const target = e.target.closest('.mn-section-col');
                                        if (target && draggedItem && target !== draggedItem) {
                                            const cols = Array.from(grid.querySelectorAll('.mn-section-col'));
                                            const draggedIdx = cols.indexOf(draggedItem);
                                            const targetIdx = cols.indexOf(target);

                                            if (draggedIdx < targetIdx) {
                                                target.after(draggedItem);
                                            } else {
                                                target.before(draggedItem);
                                            }

                                            saveSectionOrder();
                                        }
                                    });

                                    grid.addEventListener('dragend', function(e) {
                                        if (draggedItem) {
                                            draggedItem.classList.remove('is-dragging');
                                            draggedItem = null;
                                        }
                                        document.querySelectorAll('.mn-section-col').forEach(col => col.classList.remove('drag-over-target'));
                                    });

                                    function saveSectionOrder() {
                                        const cols = grid.querySelectorAll('.mn-section-col');
                                        const order = Array.from(cols).map(c => c.getAttribute('data-section-id'));
                                        const csrfToken = "<?= $_SESSION['csrf_token'] ?>";

                                        fetch('admin.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                            },
                                            body: new URLSearchParams({
                                                action_reorder_sections: 1,
                                                csrf_token: csrfToken,
                                                order: JSON.stringify(order)
                                            })
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.status === 'success') {
                                                const toastEl = document.getElementById('reorderToast');
                                                if (toastEl) {
                                                    toastEl.classList.add('show');
                                                    setTimeout(() => { toastEl.classList.remove('show'); }, 3000);
                                                }
                                            }
                                        })
                                        .catch(err => console.error('Error saving section order:', err));
                                    }
                                });
                                </script>
                            <?php endif; ?>
                        </div>

                        <!-- SECTION 3: COMPLAINTS / SUPPORT TICKET RESOLUTIONS -->
                        <div id="section-complaints" class="admin-section <?= $active_tab === 'complaints' ? 'active' : '' ?>">
                            <h4 class="fw-bold py-3 mb-2" style="font-family:'Outfit', sans-serif;">Unresolved Support Complaints</h4>
                            
                            <div class="card" style="border: 1px solid rgba(220, 200, 190, 0.4); border-radius: 16px;">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                             <tr>
                                                 <th>Client</th>
                                                 <th>Subject</th>
                                                 <th>Message Detail</th>
                                                 <th>Filed Date</th>
                                                 <th>Resolution Status</th>
                                                 <th>Chat & Reply</th>
                                                 <th>Actions</th>
                                             </tr>
                                         </thead>
                                         <tbody>
                                             <?php if (!empty($complaints_list)): ?>
                                                 <?php foreach ($complaints_list as $c): ?>
                                                     <tr>
                                                         <td><strong><?= htmlspecialchars($c['username']) ?></strong></td>
                                                         <td><?= htmlspecialchars($c['subject']) ?></td>
                                                         <td style="max-width: 250px; white-space:normal; font-size:13px;">
                                                             <?= nl2br(htmlspecialchars($c['message'])) ?>
                                                         </td>
                                                         <td><?= date('d M Y, h:i A', strtotime($c['created_at'])) ?></td>
                                                         <td>
                                                             <?php if ($c['status'] === 'open'): ?>
                                                                 <span class="badge bg-warning">Open Support Ticket</span>
                                                             <?php else: ?>
                                                                 <span class="badge bg-success">Resolved & Closed</span>
                                                             <?php endif; ?>
                                                         </td>
                                                         <td>
                                                              <button type="button" class="btn btn-sm btn-primary d-flex align-items-center gap-1" onclick="openComplaintChat(<?= $c['id'] ?>)" style="border-radius:10px; font-weight:700; background:var(--gradient-accent); border:none; box-shadow: 0 4px 10px rgba(255, 94, 54, 0.2);">
                                                                  <i class="bx bx-chat"></i> Open Chat
                                                              </button>
                                                         </td>
                                                         <td>
                                                             <button type="button" onclick="confirmDeleteComplaint(<?= $c['id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
                                                         </td>
                                                     </tr>
                                                 <?php endforeach; ?>
                                             <?php else: ?>
                                                 <tr>
                                                     <td colspan="7" class="text-center py-4 text-muted">No unresolved customer tickets found. Good job!</td>
                                                 </tr>
                                             <?php endif; ?>
                                         </tbody>
                                    </table>
                                                       <!-- Render Chat Modal (Dynamic Single Modal) -->
                             <div class="modal fade" id="chatModal" tabindex="-1" aria-hidden="true">
                                 <div class="modal-dialog modal-dialog-centered modal-lg">
                                     <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden;">
                                         <div class="modal-header" style="background: var(--gradient-accent); border: none; padding: 20px;">
                                             <h5 class="modal-title text-white fw-bold d-flex align-items-center gap-2" style="font-family: 'Outfit', sans-serif;">
                                                 <i class="bx bx-chat" style="font-size: 24px;"></i> Support Chat - Ticket #<span id="chat-ticket-id"></span>
                                             </h5>
                                             <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                         </div>
                                         <div class="modal-body" style="padding: 24px; background: #fffbf5;">
                                             <div class="mb-4" style="background: #ffffff; border-radius: 12px; padding: 16px; border: 1px solid rgba(220, 200, 190, 0.4);">
                                                 <div style="font-size: 13px; color: #6e5e54; margin-bottom: 4px;"><strong>Client Username:</strong> @<span id="chat-client-username"></span></div>
                                                 <div style="font-size: 14px; color: #231b15; line-height: 1.4;"><strong>Subject:</strong> <span id="chat-ticket-subject"></span></div>
                                             </div>

                                             <h6 class="fw-bold mb-3" style="color: var(--text-dark); font-family: 'Outfit', sans-serif;">Conversation History</h6>
                                             
                                             <!-- Chat History container -->
                                             <div id="chat-history-container" style="max-height: 320px; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 15px; border: 1.5px solid rgba(220, 200, 190, 0.3); border-radius: 12px; background: #ffffff;" class="chat-history-scroll">
                                                 <!-- Dynamic chat bubbles injected here -->
                                             </div>

                                             <!-- Form to send reply -->
                                             <form action="admin.php" method="POST" class="mt-4">
                                                 <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                 <input type="hidden" name="active_tab" value="complaints">
                                                 <input type="hidden" name="complaint_id" id="chat-form-complaint-id" value="">

                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold" style="font-size:13.5px; color: var(--text-dark);">Write Response</label>
                                                     <textarea class="form-control" name="response_text" rows="3" placeholder="Write reply to customer here..." style="border-radius: 12px; border: 1.5px solid rgba(220,200,190,0.5); font-size: 14px; padding: 10px 14px;" required></textarea>
                                                 </div>

                                                 <div class="d-flex justify-content-end gap-2">
                                                     <button type="submit" name="submit_support_response_keep_open" class="btn btn-outline-primary" style="border-radius: 10px; font-weight: 700; border: 1.5px solid;">
                                                         Send & Keep Open
                                                     </button>
                                                     <button type="submit" name="submit_support_response" class="btn btn-success" style="border-radius: 10px; font-weight: 700; background: var(--gradient-accent); border: none; box-shadow: 0 4px 12px rgba(255, 94, 54, 0.25);">
                                                         Send & Resolve Ticket
                                                     </button>
                                                 </div>
                                             </form>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>
                        </div>
                        </div>
                        <!-- 4. REGISTERED USERS SECTION -->
                        <div id="section-users" class="admin-section <?= $active_tab === 'users' ? 'active' : '' ?>">
                            <div class="d-flex align-items-center justify-content-between py-3 mb-2">
                                <h4 class="fw-bold mb-0" style="font-family:'Outfit', sans-serif;">Registered User Base</h4>
                                <div class="d-flex gap-2">
                                    <a href="admin.php?action=export-users-pdf" class="btn btn-outline-danger" style="border: 1.5px solid; border-radius:12px; font-weight:700; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="bx bxs-file-pdf me-1"></i> Export PDF
                                    </a>
                                    <button class="btn btn-primary" style="background:var(--gradient-accent); border:none; border-radius:12px; font-weight:700;" onclick="openAddUserModal()">
                                        <i class="bx bx-plus-circle me-1"></i> Add New User
                                    </button>
                                </div>
                            </div>
                            <div class="card" style="border: 1px solid rgba(220, 200, 190, 0.4); border-radius:16px; padding: 25px;">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User ID</th>
                                                <th>Full Name</th>
                                                <th>Email Address</th>
                                                <th>Mobile Number</th>
                                                <th>Total Spent</th>
                                                <th>Role</th>
                                                <th>Registered At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php foreach ($all_users as $u): ?>
                                                <tr>
                                                    <td><strong>#<?= $u['id'] ?></strong></td>
                                                    <td><?= htmlspecialchars($u['name'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                                    <td>
                                                        <?php if (!empty($u['mobile'])): ?>
                                                            <span class="badge bg-label-primary"><?= htmlspecialchars($u['mobile']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted" style="font-size:12px;">Not Provided</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-label-success fw-bold text-success" style="font-size: 13px; border: 1.5px solid rgba(40, 167, 69, 0.25); background-color: rgba(40, 167, 69, 0.08) !important; padding: 6px 12px; border-radius: 8px;">
                                                            ₹<?= number_format($u['total_spent'], 2) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-success' ?>">
                                                            <?= strtoupper($u['role']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d M Y, h:i A', strtotime($u['created_at'])) ?></td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-xs btn-outline-primary" style="border-radius:10px;" onclick="openEditUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['mobile'] ?? '', ENT_QUOTES) ?>')">
                                                                <i class="bx bx-edit-alt"></i> Edit
                                                            </button>
                                                            
                                                            <?php if ($u['id'] !== $user_id): ?>
                                                                <button type="button" onclick="openDeleteUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?>')" class="btn btn-xs btn-outline-danger" style="border-radius:10px;">
                                                                    <i class="bx bx-trash-alt"></i> Delete
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 6. REVENUE ANALYTICS SECTION -->
                        <div id="section-revenue" class="admin-section <?= $active_tab === 'revenue' ? 'active' : '' ?>">
                            <div class="d-flex align-items-center justify-content-between py-3 mb-4">
                                <h4 class="fw-bold mb-0" style="font-family:'Outfit', sans-serif;">Revenue Analytics</h4>
                                <!-- Period Tabs -->
                                <div style="display:flex; gap:8px;">
                                    <button id="tab-daily"   onclick="showChart('daily')"   class="chart-period-btn active">Daily</button>
                                    <button id="tab-weekly"  onclick="showChart('weekly')"  class="chart-period-btn">Weekly</button>
                                    <button id="tab-monthly" onclick="showChart('monthly')" class="chart-period-btn">Monthly</button>
                                </div>
                            </div>

                            <!-- KPI summary row -->
                            <div class="row mb-4">
                                <div class="col-md-4 mb-3">
                                    <div class="card stat-card" style="border-left:5px solid #28a745;">
                                        <div class="card-body d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="d-block text-muted" style="font-size:12px;font-weight:600;text-transform:uppercase;">Total Revenue</span>
                                                <h3 class="fw-bold mb-0" style="font-family:'Outfit',sans-serif;color:#1e7e44;">₹<?= number_format($total_revenue, 2) ?></h3>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#e8f5e9;color:#1e7e44;font-size:24px;width:50px;height:50px;">💰</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card stat-card" style="border-left:5px solid #fca834;">
                                        <div class="card-body d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="d-block text-muted" style="font-size:12px;font-weight:600;text-transform:uppercase;">Net Profit</span>
                                                <h3 class="fw-bold mb-0" style="font-family:'Outfit',sans-serif;color:#e05b00;">₹<?= number_format($net_profit, 2) ?></h3>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#fff5e0;color:#e05b00;font-size:24px;width:50px;height:50px;">🔥</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card stat-card" style="border-left:5px solid #0088cc;">
                                        <div class="card-body d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="d-block text-muted" style="font-size:12px;font-weight:600;text-transform:uppercase;">Total Orders</span>
                                                <?php $total_orders_count = $db->query("SELECT COUNT(*) FROM purchases WHERE status='approved'")->fetchColumn(); ?>
                                                <h3 class="fw-bold mb-0" style="font-family:'Outfit',sans-serif;color:#0088cc;"><?= number_format($total_orders_count) ?></h3>
                                            </div>
                                            <div class="stat-icon-wrapper" style="background:#e5f4fb;color:#0088cc;font-size:24px;width:50px;height:50px;">📦</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Revenue Chart -->
                            <div class="card mb-4" style="border:1px solid rgba(220,200,190,0.4);border-radius:16px;">
                                <div class="card-body" style="padding:24px;">
                                    <h6 class="fw-bold mb-1" style="color:var(--text-dark);">Revenue vs Profit</h6>
                                    <p class="text-muted mb-3" style="font-size:12px;">Approved orders only. Amounts in ₹ INR.</p>
                                    <div style="position:relative;height:300px;">
                                        <canvas id="revenueChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Orders Chart -->
                            <div class="card" style="border:1px solid rgba(220,200,190,0.4);border-radius:16px;">
                                <div class="card-body" style="padding:24px;">
                                    <h6 class="fw-bold mb-1" style="color:var(--text-dark);">Order Volume</h6>
                                    <p class="text-muted mb-3" style="font-size:12px;">Number of approved orders per period.</p>
                                    <div style="position:relative;height:220px;">
                                        <canvas id="ordersChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. SYSTEM SETTINGS SECTION -->
                        <div id="section-settings" class="admin-section <?= $active_tab === 'settings' ? 'active' : '' ?>">
                            <div class="d-flex align-items-center justify-content-between py-3 mb-2">
                                <h4 class="fw-bold mb-0" style="font-family:'Outfit', sans-serif;">System Settings</h4>
                            </div>
                            <div class="card" style="border: 1px solid rgba(220, 200, 190, 0.4); border-radius:16px; padding: 30px;">
                                <form action="admin.php" id="system-settings-form" method="POST" onsubmit="handleSettingsSubmit(event)">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="active_tab" value="settings">
                                    <input type="hidden" name="action_update_system_settings" value="1">
                                    <input type="hidden" name="admin_password" id="settings-admin-password" value="">
                                    
                                    <!-- Allow New Signups Toggle Switch -->
                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-9 col-sm-12">
                                            <h5 class="fw-bold mb-1" style="color: var(--text-dark); font-size: 16px;">Allow User Registrations / Sign-ups</h5>
                                            <p class="text-muted m-0" style="font-size: 13.5px; line-height: 1.6;">If disabled, new users will be prevented from registering or requesting OTP codes. A premium, glassmorphic contact page directing them to your Telegram (@nu9rl) will be shown instead.</p>
                                        </div>
                                        <div class="col-md-3 col-sm-12 text-md-end text-sm-start mt-md-0 mt-3">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox" name="allow_signups" id="allow_signups" style="width: 55px; height: 28px; cursor: pointer; border-radius: 20px;" <?= get_system_setting('allow_signups', '1') === '1' ? 'checked' : '' ?>>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr style="border-color: rgba(0,0,0,0.08); margin: 25px 0;">

                                    <!-- Allow Website Usage Toggle Switch -->
                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-9 col-sm-12">
                                            <h5 class="fw-bold mb-1" style="color: var(--text-dark); font-size: 16px;">Allow Users to Use Website (Public Access)</h5>
                                            <p class="text-muted m-0" style="font-size: 13.5px; line-height: 1.6;">If disabled, the website goes into lockdown/maintenance mode. All normal users (non-admins) will be logged out instantly and prevented from logging in or using any services. System administrators can still log in normally to toggle this back on.</p>
                                        </div>
                                        <div class="col-md-3 col-sm-12 text-md-end text-sm-start mt-md-0 mt-3">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox" name="allow_website_usage" id="allow_website_usage" style="width: 55px; height: 28px; cursor: pointer; border-radius: 20px;" <?= get_system_setting('allow_website_usage', '1') === '1' ? 'checked' : '' ?>>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr style="border-color: rgba(0,0,0,0.08); margin: 30px 0;">

                                    <!-- SMTP Mailer Settings Section -->
                                    <h5 class="fw-bold mb-2" style="color: var(--text-dark); font-size: 16px; font-family:'Outfit', sans-serif;">
                                        <i class="bx bx-envelope me-1"></i> SMTP Configuration (OTP Mail Service)
                                    </h5>
                                    <p class="text-muted mb-4" style="font-size: 13.5px; line-height: 1.6;">
                                        Mango Numbers uses SMTP to deliver verification codes (OTPs) to users. Each account gets free credits. If you run out of credits, enter the login details of another SMTP account here to dynamically rotate the SMTP server.
                                    </p>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label fw-bold" for="mail_host">SMTP Host</label>
                                            <input type="text" class="form-control" name="mail_host" id="mail_host" value="<?= htmlspecialchars($smtp_settings['host'] ?? '') ?>" placeholder="e.g. smtp-relay.brevo.com" style="border-radius: 10px; padding: 10px 14px;" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label fw-bold" for="mail_port">SMTP Port</label>
                                            <input type="number" class="form-control" name="mail_port" id="mail_port" value="<?= htmlspecialchars($smtp_settings['port'] ?? '') ?>" placeholder="e.g. 587" style="border-radius: 10px; padding: 10px 14px;" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label fw-bold" for="mail_encryption">Encryption</label>
                                            <select class="form-select" name="mail_encryption" id="mail_encryption" style="border-radius: 10px; padding: 10px 14px;">
                                                <option value="tls" <?= (strtolower($smtp_settings['encryption'] ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                                                <option value="ssl" <?= (strtolower($smtp_settings['encryption'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold" for="mail_username">SMTP Login Email (Username)</label>
                                            <input type="text" class="form-control" name="mail_username" id="mail_username" value="<?= htmlspecialchars($smtp_settings['username'] ?? '') ?>" placeholder="e.g. your-username@gmail.com" style="border-radius: 10px; padding: 10px 14px;" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold" for="mail_password">SMTP Password / API Key</label>
                                            <div class="input-group input-group-merge form-password-toggle">
                                                <input type="password" class="form-control" name="mail_password" id="mail_password" value="" placeholder="••••••••••••" style="border-radius: 10px 0 0 10px; padding: 10px 14px;">
                                                <span class="input-group-text cursor-pointer" style="border-radius: 0 10px 10px 0; border-left: 0;"><i class="bx bx-hide"></i></span>
                                            </div>
                                            <div class="form-text text-muted" style="font-size: 12px; margin-top: 4px;">
                                                Leave blank to keep the current password stored in the database.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold" for="mail_from_address">Sender Email Address</label>
                                            <input type="email" class="form-control" name="mail_from_address" id="mail_from_address" value="<?= htmlspecialchars($smtp_settings['from_email'] ?? '') ?>" placeholder="e.g. no-reply@mangonumbers.com" style="border-radius: 10px; padding: 10px 14px;" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold" for="mail_from_name">Sender Display Name</label>
                                            <input type="text" class="form-control" name="mail_from_name" id="mail_from_name" value="<?= htmlspecialchars($smtp_settings['from_name'] ?? '') ?>" placeholder="e.g. Mango Numbers" style="border-radius: 10px; padding: 10px 14px;" required>
                                        </div>
                                    </div>

                                    <hr style="border-color: rgba(0,0,0,0.08); margin: 30px 0;">
                                    
                                    <!-- Save Button -->
                                    <div class="text-end">
                                        <button type="submit" name="action_update_system_settings" class="btn btn-primary" style="background: linear-gradient(135deg, var(--accent-orange) 0%, var(--accent-yellow) 100%); border: none; padding: 12px 30px; border-radius: 12px; font-weight: 700; box-shadow: 0 4px 14px rgba(255,140,0,0.3);">
                                            <i class="bx bx-save me-1"></i> Save Configuration
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->
                </div>
                <!-- / Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- LIGHTBOX MODAL OVERLAY -->
    <div class="lightbox-overlay" id="lightboxModal">
        <div class="lightbox-card">
            <button class="modal-close" onclick="closeLightbox()" style="position:absolute; top:15px; right:15px; background:none; border:none; font-size:24px; color:#5c4f47; cursor:pointer;">&times;</button>
            <h5 class="fw-semibold mb-3">Receipt Image Proof (UTR: <span id="lightbox-utr-id" style="color:var(--accent-orange);"></span>)</h5>
            <img class="lightbox-img" id="lightbox-img" src="" alt="Payment screenshot proof">
            <div>
                <button onclick="closeLightbox()" class="btn btn-secondary">Close View</button>
            </div>
        </div>
    </div>

    <!-- EDIT USER MODAL OVERLAY -->
    <div class="lightbox-overlay" id="editUserModal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: rgba(24, 18, 14, 0.6); backdrop-filter: blur(8px); z-index: 1050; padding: 20px;">
        <div class="card" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 30px; box-shadow: 0 15px 35px rgba(24, 18, 14, 0.15); position: relative; animation: modalFadeIn 0.3s ease;">
            <button class="modal-close" onclick="closeEditUserModal()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; color:var(--text-light); cursor:pointer;">&times;</button>
            <h4 class="fw-bold mb-3" style="font-family:'Outfit', sans-serif; text-align: center;">Edit User Profile</h4>
            
            <form action="admin.php" id="editUserForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="active_tab" value="users">
                <input type="hidden" name="edit_user_id" id="edit-user-id">
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="edit-name" style="font-weight:600; color:var(--text-dark);">Full Name</label>
                    <input class="form-control" type="text" name="edit_name" id="edit-name" required>
                </div>
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="edit-email" style="font-weight:600; color:var(--text-dark);">Email Address</label>
                    <input class="form-control" type="email" name="edit_email" id="edit-email" required>
                </div>
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="edit-mobile" style="font-weight:600; color:var(--text-dark);">Mobile Number</label>
                    <input class="form-control" type="tel" name="edit_mobile" id="edit-mobile">
                </div>
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="edit-password" style="font-weight:600; color:var(--text-dark);">Reset Password (Optional)</label>
                    <input class="form-control" type="text" name="edit_password" id="edit-password" placeholder="Leave blank to keep current password">
                    <small class="text-muted">Minimum 6 characters, will be securely hashed.</small>
                </div>
                
                <button type="button" onclick="confirmEditUser(this.form)" class="btn btn-primary w-100" style="background:var(--gradient-accent); border:none; padding:12px; font-weight:700;">Save Profile Changes</button>
            </form>
        </div>
    </div>

    <!-- ADD USER MODAL OVERLAY -->
    <div class="lightbox-overlay" id="addUserModal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: rgba(24, 18, 14, 0.6); backdrop-filter: blur(8px); z-index: 1050; padding: 20px;">
        <div class="card" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 30px; box-shadow: 0 15px 35px rgba(24, 18, 14, 0.15); position: relative; animation: modalFadeIn 0.3s ease;">
            <button class="modal-close" onclick="closeAddUserModal()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; color:var(--text-light); cursor:pointer;">&times;</button>
            <h4 class="fw-bold mb-3" style="font-family:'Outfit', sans-serif; text-align: center;">Add New User</h4>
            
            <form action="admin.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="active_tab" value="users">
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="add-name" style="font-weight:600; color:var(--text-dark);">Full Name</label>
                    <input class="form-control" type="text" name="add_name" id="add-name" placeholder="John Doe" required>
                </div>
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="add-email" style="font-weight:600; color:var(--text-dark);">Email Address</label>
                    <input class="form-control" type="email" name="add_email" id="add-email" placeholder="john@example.com" required>
                </div>
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="add-mobile" style="font-weight:600; color:var(--text-dark);">Mobile Number (Optional)</label>
                    <input class="form-control" type="tel" name="add_mobile" id="add-mobile" placeholder="+919999999999">
                </div>
                
                <div class="mb-3 text-start">
                    <label class="form-label" for="add-password" style="font-weight:600; color:var(--text-dark);">Password</label>
                    <input class="form-control" type="text" name="add_password" id="add-password" placeholder="Enter secure password" required>
                    <small class="text-muted">Minimum 6 characters, will be securely hashed.</small>
                </div>

                <div class="mb-3 text-start">
                    <label class="form-label" for="add-role" style="font-weight:600; color:var(--text-dark);">Role</label>
                    <select class="form-select" name="add_role" id="add-role" required>
                        <option value="user" selected>User (Standard Customer)</option>
                        <option value="admin">Admin (System Administrator)</option>
                    </select>
                </div>
                
                <button type="submit" name="submit_add_user" class="btn btn-primary w-100" style="background:var(--gradient-accent); border:none; padding:12px; font-weight:700;">Create User Account</button>
            </form>
        </div>
    </div>

    <!-- DELETE USER MODAL OVERLAY -->
    <div class="lightbox-overlay" id="deleteUserModal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: rgba(24, 18, 14, 0.6); backdrop-filter: blur(8px); z-index: 1050; padding: 20px;">
        <div class="card" style="background: #ffffff; border-radius: 20px; max-width: 450px; width: 100%; padding: 30px; box-shadow: 0 15px 35px rgba(24, 18, 14, 0.15); position: relative; animation: modalFadeIn 0.3s ease; border-top: 4px solid var(--accent-orange);">
            <button class="modal-close" onclick="closeDeleteUserModal()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; color:var(--text-light); cursor:pointer;">&times;</button>
            <h4 class="fw-bold mb-3" style="font-family:'Outfit', sans-serif; text-align: center;">Delete User Account</h4>
            <p class="text-muted mb-4" style="font-size: 13.5px; line-height: 1.6; text-align: center;">Are you sure you want to completely delete the user account "<strong id="delete-user-name" style="color:var(--text-dark);"></strong>" and clear their operational logs? This action cannot be undone.</p>
            
            <form action="admin.php" id="deleteUserForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="active_tab" value="users">
                <input type="hidden" name="delete_user_id" id="delete-user-id">
                
                <div class="mb-4 text-start">
                    <label class="form-label" for="delete-reason" style="font-weight:600; color:var(--text-dark);">Reason for Account Deletion</label>
                    <textarea class="form-control" name="delete_reason" id="delete-reason" placeholder="Enter reason (e.g. Terms violation, Fraudulent deposit, etc.)" rows="3" required></textarea>
                </div>
                
                <div class="d-flex gap-3">
                    <button type="button" class="btn btn-secondary w-50" onclick="closeDeleteUserModal()" style="border-radius:12px;">Cancel</button>
                    <button type="submit" name="action_delete_user" class="btn btn-danger w-50" style="border-radius:12px; background:linear-gradient(135deg, #dc3545 0%, #bd2130 100%); border:none;">Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CUSTOM CONFIRMATION MODAL -->
    <div class="lightbox-overlay" id="customConfirmModal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: rgba(24, 18, 14, 0.65); backdrop-filter: blur(8px); z-index: 2000; padding: 20px;">
        <div class="card" style="background: #ffffff; border-radius: 20px; max-width: 420px; width: 100%; padding: 25px 30px; box-shadow: 0 15px 35px rgba(24, 18, 14, 0.2); position: relative; text-align: center; border: 1.5px solid rgba(255, 94, 54, 0.15); animation: modalFadeIn 0.3s ease;">
            <div style="font-size: 45px; margin-bottom: 15px;">⚠️</div>
            <h4 class="fw-bold mb-2" id="confirm-modal-title" style="font-family:'Outfit', sans-serif; color: var(--text-dark);">Confirm Action</h4>
            <p class="text-muted mb-4" id="confirm-modal-message" style="font-size: 14px; line-height: 1.6;"></p>
            
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-secondary w-50" onclick="closeCustomConfirm()" style="border-radius:12px; font-weight:600; padding: 10px;">Cancel</button>
                <button type="button" class="btn btn-danger w-50" id="confirm-modal-btn" style="border-radius:12px; font-weight:700; padding: 10px; background: linear-gradient(135deg, #ff5e36 0%, #fca834 100%); border: none; color: #ffffff;">Confirm</button>
            </div>
        </div>
    </div>

    <!-- LOCKDOWN CONFIRMATION MODAL 1 -->
    <div class="lightbox-overlay" id="lockdownModal1" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: rgba(24, 18, 14, 0.6); backdrop-filter: blur(8px); z-index: 1050; padding: 20px;">
        <div class="card" style="background: #ffffff; border-radius: 20px; max-width: 450px; width: 100%; padding: 30px; box-shadow: 0 15px 35px rgba(24, 18, 14, 0.15); position: relative; text-align: center; border-top: 3px solid #ff5e36; border-radius: 20px; animation: modalFadeIn 0.3s ease;">
            <button class="modal-close" onclick="closeLockdownModal1()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; color:var(--text-light); cursor:pointer;">&times;</button>
            <div style="font-size: 50px; margin-bottom: 15px;">⚠️</div>
            <h4 class="fw-bold mb-3" style="font-family:'Outfit', sans-serif; color: var(--text-dark);">Confirm Settings Update (Step 1 of 2)</h4>
            <p class="text-muted mb-4" style="font-size: 14px; line-height: 1.6;">Are you sure you want to update the system configurations? This will apply the new signup and website access rules immediately.</p>
            
            <div class="d-flex gap-3">
                <button type="button" class="btn btn-secondary w-50" onclick="closeLockdownModal1()" style="border-radius:12px; font-weight:600; padding: 10px;">Cancel</button>
                <button type="button" class="btn btn-danger w-50" onclick="proceedToLockdownAuth()" style="border-radius:12px; font-weight:700; padding: 10px; background: linear-gradient(135deg, #ff5e36 0%, #d32f2f 100%); border: none; color: white;">Proceed to Auth</button>
            </div>
        </div>
    </div>

    <!-- LOCKDOWN PASSWORD AUTHENTICATION MODAL 2 -->
    <div class="lightbox-overlay" id="lockdownModal2" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: rgba(24, 18, 14, 0.6); backdrop-filter: blur(8px); z-index: 1050; padding: 20px;">
        <div class="card" style="background: #ffffff; border-radius: 20px; max-width: 450px; width: 100%; padding: 30px; box-shadow: 0 15px 35px rgba(24, 18, 14, 0.15); position: relative; border-top: 3px solid #ff5e36; border-radius: 20px; animation: modalFadeIn 0.3s ease;">
            <button class="modal-close" onclick="closeLockdownModal2()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; color:var(--text-light); cursor:pointer;">&times;</button>
            <h4 class="fw-bold mb-2 text-center" style="font-family:'Outfit', sans-serif; color: var(--text-dark);">Admin Authentication (Step 2 of 2)</h4>
            <p class="text-muted text-center mb-4" style="font-size: 13.5px; line-height: 1.5;">Please enter your administrator account password to authorize these changes.</p>
            
            <div class="mb-4 text-start">
                <label class="form-label fw-bold" for="lockdown-admin-password" style="color:var(--text-dark);">Administrator Password</label>
                <input class="form-control" type="password" id="lockdown-admin-password" placeholder="Enter your password" required style="border-radius:10px;">
            </div>
            
            <div class="d-flex gap-3">
                <button type="button" class="btn btn-secondary w-50" onclick="closeLockdownModal2()" style="border-radius:12px; font-weight:600; padding: 10px;">Cancel</button>
                <button type="button" class="btn btn-danger w-50" onclick="submitLockdownWithPassword()" style="border-radius:12px; font-weight:700; padding: 10px; background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); border: none; color: white;">Confirm Changes</button>
            </div>
        </div>
    </div>

    <!-- Core Scripts -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>

    <!-- Admin UI JS -->
    <script>
        const csrfToken = '<?= $_SESSION['csrf_token'] ?>';

        // Escapes HTML tags to prevent XSS in chat messages
        function escapeHTML(str) {
            if (!str) return '';
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Dynamically loads complaint chat messages and opens the modal
        function openComplaintChat(complaintId) {
            document.getElementById('chat-form-complaint-id').value = complaintId;
            document.getElementById('chat-ticket-id').innerText = complaintId;

            const container = document.getElementById('chat-history-container');
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem; color: var(--accent-orange) !important;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0" style="font-size: 13px;">Loading conversation history...</p>
                </div>
            `;

            document.getElementById('chat-client-username').innerText = 'Loading...';
            document.getElementById('chat-ticket-subject').innerText = 'Loading...';

            const chatModalEl = document.getElementById('chatModal');
            let modalInstance = bootstrap.Modal.getInstance(chatModalEl);
            if (!modalInstance) {
                modalInstance = new bootstrap.Modal(chatModalEl);
            }
            modalInstance.show();

            fetch('admin.php?action=get-complaint-chat&id=' + complaintId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-danger p-2 m-2" style="font-size: 13px;">${data.error}</div>`;
                        return;
                    }

                    const c = data.complaint;
                    document.getElementById('chat-client-username').innerText = c.username;
                    document.getElementById('chat-ticket-subject').innerText = c.subject;

                    let html = '';
                    // Original ticket message from client
                    html += `
                        <div style="align-self: flex-start; max-width: 80%; display: flex; flex-direction: column;">
                            <div style="background: rgba(255, 94, 54, 0.08); border: 1.5px solid rgba(255,140,0,0.25); border-radius: 14px 14px 14px 0px; padding: 12px 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.01);">
                                <div style="font-weight: 700; font-size: 11px; color: var(--accent-orange); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Client (Original Ticket Message)</div>
                                <div style="font-size: 13.5px; color: var(--text-dark); line-height: 1.5; white-space: pre-wrap; word-break: break-word;">${escapeHTML(c.message)}</div>
                                <div style="font-size: 10px; color: var(--text-light); text-align: right; margin-top: 6px;">${c.created_at}</div>
                            </div>
                        </div>
                    `;

                    // Message history loop
                    data.messages.forEach(m => {
                        if (m.sender === 'admin') {
                            html += `
                                <div style="align-self: flex-end; max-width: 80%; display: flex; justify-content: flex-end; width: 100%;">
                                    <div style="background: rgba(46, 125, 50, 0.08); border: 1.5px solid rgba(46, 125, 50, 0.25); border-radius: 14px 14px 0px 14px; padding: 12px 16px; text-align: left; box-shadow: 0 2px 6px rgba(0,0,0,0.01);">
                                        <div style="font-weight: 700; font-size: 11px; color: #2e7d32; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Support Team (You)</div>
                                        <div style="font-size: 13.5px; color: var(--text-dark); line-height: 1.5; white-space: pre-wrap; word-break: break-word;">${escapeHTML(m.message)}</div>
                                        <div style="font-size: 10px; color: var(--text-light); text-align: right; margin-top: 6px;">${m.created_at}</div>
                                    </div>
                                </div>
                            `;
                        } else {
                            html += `
                                <div style="align-self: flex-start; max-width: 80%;">
                                    <div style="background: rgba(255, 94, 54, 0.08); border: 1.5px solid rgba(255,140,0,0.25); border-radius: 14px 14px 14px 0px; padding: 12px 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.01);">
                                        <div style="font-weight: 700; font-size: 11px; color: var(--accent-orange); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Client (@${escapeHTML(c.username)})</div>
                                        <div style="font-size: 13.5px; color: var(--text-dark); line-height: 1.5; white-space: pre-wrap; word-break: break-word;">${escapeHTML(m.message)}</div>
                                        <div style="font-size: 10px; color: var(--text-light); text-align: right; margin-top: 6px;">${m.created_at}</div>
                                    </div>
                                </div>
                            `;
                        }
                    });

                    container.innerHTML = html;
                    setTimeout(() => {
                        container.scrollTop = container.scrollHeight;
                    }, 50);
                })
                .catch(err => {
                    container.innerHTML = `<div class="alert alert-danger p-2 m-2" style="font-size: 13px;">Error loading history: ${escapeHTML(err.message)}</div>`;
                });
        }

        function switchSection(section) {
            document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
            document.querySelectorAll('.admin-section').forEach(sec => sec.classList.remove('active'));
            
            document.getElementById('menu-' + section).classList.add('active');
            document.getElementById('section-' + section).classList.add('active');

            // Dynamically show the summary stats panel only on the approvals/revenue tab
            const statsContainer = document.getElementById('admin-dashboard-stats');
            if (statsContainer) {
                if (section === 'approvals' || section === 'revenue') {
                    statsContainer.style.display = 'block';
                } else {
                    statsContainer.style.display = 'none';
                }
            }

            // Init charts when switching to revenue tab
            if (section === 'revenue') {
                setTimeout(initCharts, 50);
            }

            // Automatically collapse the sidebar menu on mobile devices when tab is changed
            if (window.Helpers && window.Helpers.isSmallScreen && window.Helpers.isSmallScreen()) {
                window.Helpers.setCollapsed(true);
            }
        }

        // Toggle mobile sidebar menu
        function toggleMobileSidebar() {
            if (window.Helpers) {
                window.Helpers.toggleCollapsed();
            } else {
                document.documentElement.classList.toggle('layout-menu-expanded');
            }
        }

        // Open Lightbox Screenshot
        function openLightbox(imgSrc, utr) {
            document.getElementById('lightbox-img').src = imgSrc;
            document.getElementById('lightbox-utr-id').innerText = utr;
            document.getElementById('lightboxModal').style.display = 'flex';
        }

        // Close Lightbox
        function closeLightbox() {
            document.getElementById('lightboxModal').style.display = 'none';
        }

        // Open Edit User Profile Modal
        function openEditUserModal(id, name, email, mobile) {
            document.getElementById('edit-user-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-mobile').value = mobile;
            document.getElementById('edit-password').value = ''; // Reset password field

            document.getElementById('editUserModal').style.display = 'flex';
        }

        // Close Edit User Modal
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // Open Add User Modal
        function openAddUserModal() {
            document.getElementById('add-name').value = '';
            document.getElementById('add-email').value = '';
            document.getElementById('add-mobile').value = '';
            document.getElementById('add-password').value = '';
            document.getElementById('add-role').value = 'user';

            document.getElementById('addUserModal').style.display = 'flex';
        }

        // Close Add User Modal
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        // Clicking outside closes modals
        window.onclick = function(event) {
            const lbModal = document.getElementById('lightboxModal');
            const euModal = document.getElementById('editUserModal');
            const auModal = document.getElementById('addUserModal');
            const ccModal = document.getElementById('customConfirmModal');
            const duModal = document.getElementById('deleteUserModal');
            if (event.target == lbModal) {
                lbModal.style.display = "none";
            }
            if (event.target == euModal) {
                euModal.style.display = "none";
            }
            if (event.target == auModal) {
                auModal.style.display = "none";
            }
            if (event.target == ccModal) {
                ccModal.style.display = "none";
            }
            if (event.target == duModal) {
                duModal.style.display = "none";
            }
        }

        // Custom Confirm JS Logic
        let activeConfirmCallback = null;

        function showCustomConfirm(title, message, confirmBtnText, isDestructive, onConfirm) {
            document.getElementById('confirm-modal-title').innerText = title;
            document.getElementById('confirm-modal-message').innerText = message;
            
            const confirmBtn = document.getElementById('confirm-modal-btn');
            confirmBtn.innerText = confirmBtnText || 'Confirm';
            
            if (isDestructive) {
                confirmBtn.style.background = 'linear-gradient(135deg, #dc3545 0%, #bd2130 100%)';
            } else {
                confirmBtn.style.background = 'var(--gradient-accent)';
            }
            
            activeConfirmCallback = onConfirm;
            document.getElementById('customConfirmModal').style.display = 'flex';
        }

        function closeCustomConfirm() {
            document.getElementById('customConfirmModal').style.display = 'none';
            activeConfirmCallback = null;
        }

        document.getElementById('confirm-modal-btn').onclick = function() {
            if (activeConfirmCallback) {
                activeConfirmCallback();
            }
            closeCustomConfirm();
        };

        // Modal Action Helpers
        function confirmDeleteComplaint(complaintId) {
            showCustomConfirm(
                "Delete Support Ticket?",
                "Are you sure you want to delete this customer ticket? It will be immediately hidden and permanently deleted from the database in 3 days.",
                "Delete Ticket",
                true,
                function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin.php';
                    
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);
                    
                    const tabInput = document.createElement('input');
                    tabInput.type = 'hidden';
                    tabInput.name = 'active_tab';
                    tabInput.value = 'complaints';
                    form.appendChild(tabInput);
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'complaint_id';
                    idInput.value = complaintId;
                    form.appendChild(idInput);
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'submit_delete_complaint';
                    actionInput.value = '1';
                    form.appendChild(actionInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        function confirmOrderRejection(form) {
            showCustomConfirm(
                'Reject UTR Receipt',
                'Are you sure you want to reject this transaction receipt? The client will see a rejected status on their dashboard.',
                'Reject Order',
                true,
                function() {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'action_reject';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            );
        }

        function confirmEditUser(form) {
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            showCustomConfirm(
                'Update User Profile',
                'Are you sure you want to save the new profile changes for this user account?',
                'Save Changes',
                false,
                function() {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'submit_edit_user';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            );
        }

        function openDeleteUserModal(id, username) {
            document.getElementById('delete-user-id').value = id;
            document.getElementById('delete-user-name').innerText = username;
            document.getElementById('delete-reason').value = '';
            document.getElementById('deleteUserModal').style.display = 'flex';
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'none';
        }

        let lockdownSubmitting = false;

        function handleSettingsSubmit(event) {
            // Check if we are programmatically submitting after password validation
            if (lockdownSubmitting) {
                return true;
            }
            
            event.preventDefault();
            openLockdownModal1();
            return false;
        }

        function openLockdownModal1() {
            document.getElementById('lockdownModal1').style.display = 'flex';
        }

        function closeLockdownModal1() {
            document.getElementById('lockdownModal1').style.display = 'none';
        }

        function proceedToLockdownAuth() {
            document.getElementById('lockdownModal1').style.display = 'none';
            document.getElementById('lockdown-admin-password').value = '';
            document.getElementById('lockdownModal2').style.display = 'flex';
        }

        function closeLockdownModal2() {
            document.getElementById('lockdownModal2').style.display = 'none';
        }

        function submitLockdownWithPassword() {
            const password = document.getElementById('lockdown-admin-password').value;
            if (!password || password.trim() === '') {
                showCustomConfirm(
                    'Validation Error',
                    'Please enter your password to authorize changes.',
                    'OK',
                    false,
                    function() {}
                );
                return;
            }
            
            // Set password and submit form programmatically
            document.getElementById('settings-admin-password').value = password;
            document.getElementById('lockdownModal2').style.display = 'none';
            
            lockdownSubmitting = true;
            document.getElementById('system-settings-form').submit();
        }

        // Bidirectional Real-time Price calculations based on Exchange Rate
        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.querySelector('#section-catalog tbody');
            const usdToInr = document.getElementById('usd_to_inr');
            if (!tableBody || !usdToInr) return;

            // Helper to get exchange rate
            const getExchangeRate = () => parseFloat(usdToInr.value) || 83.50;

            // Recalculate all USD columns if global exchange rate changes
            usdToInr.addEventListener('input', () => {
                const rate = getExchangeRate();
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    const costInrInput = row.querySelector('input[name="price_cost_inr"]');
                    const costUsdInput = row.querySelector('input[name="price_cost_usd"]');
                    if (costInrInput && costUsdInput && document.activeElement !== costUsdInput) {
                        const inr = parseFloat(costInrInput.value) || 0;
                        costUsdInput.value = (inr / rate).toFixed(2);
                    }

                    const sellInrInput = row.querySelector('input[name="price_inr"]');
                    const sellUsdInput = row.querySelector('input[name="price_usd"]');
                    if (sellInrInput && sellUsdInput && document.activeElement !== sellUsdInput) {
                        const inr = parseFloat(sellInrInput.value) || 0;
                        sellUsdInput.value = (inr / rate).toFixed(2);
                    }
                });
            });

            // Handle typing changes (bidirectional)
            tableBody.addEventListener('input', (e) => {
                const target = e.target;
                const rate = getExchangeRate();

                if (target.name === 'price_cost_inr') {
                    const row = target.closest('tr');
                    const usdInput = row.querySelector('input[name="price_cost_usd"]');
                    if (usdInput) {
                        const val = parseFloat(target.value) || 0;
                        usdInput.value = (val / rate).toFixed(2);
                    }
                }
                else if (target.name === 'price_cost_usd') {
                    const row = target.closest('tr');
                    const inrInput = row.querySelector('input[name="price_cost_inr"]');
                    if (inrInput) {
                        const val = parseFloat(target.value) || 0;
                        inrInput.value = Math.round(val * rate);
                    }
                }
                else if (target.name === 'price_inr') {
                    const row = target.closest('tr');
                    const usdInput = row.querySelector('input[name="price_usd"]');
                    if (usdInput) {
                        const val = parseFloat(target.value) || 0;
                        usdInput.value = (val / rate).toFixed(2);
                    }
                }
                else if (target.name === 'price_usd') {
                    const row = target.closest('tr');
                    const inrInput = row.querySelector('input[name="price_inr"]');
                    if (inrInput) {
                        const val = parseFloat(target.value) || 0;
                        inrInput.value = Math.round(val * rate);
                    }
                }
            });
        });

        // Real-time catalog table row filter
        function filterCatalogRows() {
            const query = document.getElementById('catalog-search-input').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.catalog-item-row');
            rows.forEach(row => {
                const country = row.getAttribute('data-country').toLowerCase();
                const service = row.getAttribute('data-service').toLowerCase();
                const name = row.getAttribute('data-name').toLowerCase();
                if (country.includes(query) || service.includes(query) || name.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Real-time pending approvals filter by UTR / Username
        function filterPendingRows() {
            const query = document.getElementById('utr-search-input').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#section-approvals tbody tr');
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
            });
        }

        // Quick stock level adjuster (+5, -1)
        function adjustStock(btn, amount) {
            const input = btn.closest('td').querySelector('.stock-input');
            if (input) {
                let current = parseInt(input.value) || 0;
                input.value = Math.max(0, current + amount);
            }
        }
    </script>
    <!-- Chart.js for Revenue Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const chartData = {
            daily:   { labels: <?= json_encode($daily_labels) ?>,   revenue: <?= json_encode($daily_revenue) ?>,   profit: <?= json_encode($daily_profit) ?>,   orders: <?= json_encode($daily_orders) ?> },
            weekly:  { labels: <?= json_encode($weekly_labels) ?>,  revenue: <?= json_encode($weekly_revenue) ?>,  profit: <?= json_encode($weekly_profit) ?>,  orders: <?= json_encode($weekly_orders) ?> },
            monthly: { labels: <?= json_encode($monthly_labels) ?>, revenue: <?= json_encode($monthly_revenue) ?>, profit: <?= json_encode($monthly_profit) ?>, orders: <?= json_encode($monthly_orders) ?> }
        };
        let activePeriod = 'daily';
        let revenueChart = null, ordersChart = null;

        function initCharts() {
            showChart(activePeriod);
        }

        function showChart(period) {
            activePeriod = period;
            // Update period button active state
            ['daily','weekly','monthly'].forEach(p => {
                const btn = document.getElementById('tab-' + p);
                if (btn) btn.classList.toggle('active', p === period);
            });

            const d = chartData[period];

            // Destroy previous instances
            if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
            if (ordersChart)  { ordersChart.destroy();  ordersChart  = null; }

            const revenueCtx = document.getElementById('revenueChart');
            const ordersCtx  = document.getElementById('ordersChart');
            if (!revenueCtx || !ordersCtx) return;

            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    x: { grid: { color: 'rgba(0,0,0,0.05)' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' },
                         ticks: { callback: v => '\u20b9' + v.toLocaleString('en-IN') } }
                }
            };

            revenueChart = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        { label: 'Revenue (\u20b9)', data: d.revenue, backgroundColor: 'rgba(40,167,69,0.7)', borderColor: '#28a745', borderWidth: 1.5, borderRadius: 4 },
                        { label: 'Profit (\u20b9)',  data: d.profit,  backgroundColor: 'rgba(252,168,52,0.7)', borderColor: '#fca834', borderWidth: 1.5, borderRadius: 4 }
                    ]
                },
                options: commonOptions
            });

            ordersChart = new Chart(ordersCtx, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        { label: 'Orders', data: d.orders, backgroundColor: 'rgba(0,136,204,0.65)', borderColor: '#0088cc', borderWidth: 1.5, borderRadius: 4 }
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        x: { grid: { color: 'rgba(0,0,0,0.05)' } },
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // Auto-init if page loads directly on revenue tab
        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('section-revenue')?.classList.contains('active')) {
                initCharts();
            }
        });
    </script>
    <!-- In-Website Dialog Confirmation Modal (No Browser Native Popups) -->
    <div id="mnCustomConfirmModal" class="modal fade" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px); background: rgba(15, 23, 42, 0.5);">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content" style="border-radius: 24px; border: 1.5px solid rgba(255, 94, 54, 0.2); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; background: #ffffff;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 d-inline-flex align-items-center justify-content-center" style="width: 72px; height: 72px; border-radius: 22px; background: #fef2f2; border: 1.5px solid #fecaca; box-shadow: 0 10px 25px rgba(220, 38, 38, 0.15);">
                        <img id="mnConfirmIcon" src="assets/img/delete_icon.png" style="width: 42px; height: 42px; object-fit: contain;">
                    </div>
                    <h5 id="mnConfirmTitle" class="fw-bold mb-2" style="font-family: 'Outfit', sans-serif; color: #0f172a; font-size: 20px;">Confirm Action</h5>
                    <p id="mnConfirmMessage" class="text-muted mb-4" style="font-size: 14px; line-height: 1.5; color: #64748b;">Are you sure you want to proceed with this action?</p>
                    
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <button type="button" class="btn py-2.5 px-4 fw-semibold flex-grow-1" data-bs-dismiss="modal" style="border-radius: 14px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 14px;">
                            Cancel
                        </button>
                        <button type="button" id="mnConfirmProceedBtn" class="btn py-2.5 px-4 fw-bold flex-grow-1" style="border-radius: 14px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #ffffff; border: none; box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35); font-size: 14px;">
                            Yes, Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // In-Website Dialog Confirmation Handler (Replaces native browser popups)
        let mnPendingSubmitForm = null;

        function mnConfirmAction(event, message, iconUrl = 'assets/img/delete_icon.png', buttonText = 'Yes, Delete') {
            if (event) {
                event.preventDefault();
                mnPendingSubmitForm = event.target.closest('form');
            }
            
            const msgEl = document.getElementById('mnConfirmMessage');
            const iconEl = document.getElementById('mnConfirmIcon');
            const proceedBtn = document.getElementById('mnConfirmProceedBtn');
            
            if (msgEl) msgEl.textContent = message || 'Are you sure you want to continue?';
            if (iconEl && iconUrl) iconEl.src = iconUrl;
            if (proceedBtn && buttonText) proceedBtn.textContent = buttonText;
            
            const confirmModalEl = document.getElementById('mnCustomConfirmModal');
            if (confirmModalEl && typeof bootstrap !== 'undefined') {
                const bsModal = new bootstrap.Modal(confirmModalEl);
                bsModal.show();
            } else {
                if (mnPendingSubmitForm) mnPendingSubmitForm.submit();
            }
            return false;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const proceedBtn = document.getElementById('mnConfirmProceedBtn');
            if (proceedBtn) {
                proceedBtn.addEventListener('click', function() {
                    if (mnPendingSubmitForm) {
                        const formToSubmit = mnPendingSubmitForm;
                        mnPendingSubmitForm = null;
                        const confirmModalEl = document.getElementById('mnCustomConfirmModal');
                        if (confirmModalEl && typeof bootstrap !== 'undefined') {
                            const bsModal = bootstrap.Modal.getInstance(confirmModalEl);
                            if (bsModal) bsModal.hide();
                        }
                        formToSubmit.submit();
                    }
                });
            }
        });
    </script>

    <!-- Global Image Clipboard Paste (Ctrl+V / Cmd+V) & Drag-Drop Engine -->
    <style>
        .mn-image-dropzone:hover,
        .mn-image-dropzone.drag-over {
            border-color: #ff5e36 !important;
            background: #fff7ed !important;
            box-shadow: 0 0 0 4px rgba(255, 94, 54, 0.12);
        }
        .mn-image-dropzone.has-file {
            border-color: #10b981 !important;
            background: #f0fdf4 !important;
        }
    </style>
    <script>
        function mnAttachImageToDropzone(wrap, file, sourceLabel = 'File Selected') {
            if (!wrap || !file) return;
            const fileInput = wrap.querySelector('input[type="file"]');
            const base64Input = wrap.querySelector('input[type="hidden"]');
            const promptEl = wrap.querySelector('.mn-dropzone-prompt');
            const previewEl = wrap.querySelector('.mn-dropzone-preview');
            const previewImg = wrap.querySelector('.mn-preview-img');
            const filenameEl = wrap.querySelector('.mn-preview-filename');
            const sourceEl = wrap.querySelector('.mn-preview-source');
            const dropzone = wrap.querySelector('.mn-image-dropzone');

            if (fileInput && window.DataTransfer) {
                try {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fileInput.files = dt.files;
                } catch (err) {
                    console.warn('DataTransfer error:', err);
                }
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const base64Data = e.target.result;
                if (base64Input) base64Input.value = base64Data;
                if (previewImg) previewImg.src = base64Data;

                if (promptEl) promptEl.classList.add('d-none');
                if (previewEl) {
                    previewEl.classList.remove('d-none');
                    previewEl.classList.add('d-flex');
                }
                if (filenameEl) filenameEl.textContent = file.name || 'clipboard_image.png';
                if (sourceEl) sourceEl.textContent = sourceLabel;

                if (dropzone) {
                    dropzone.classList.add('has-file');
                }
            };
            reader.readAsDataURL(file);
        }

        function mnHandleFileSelect(input) {
            if (input.files && input.files[0]) {
                const wrap = input.closest('.mn-image-dropzone-wrap');
                if (wrap) {
                    mnAttachImageToDropzone(wrap, input.files[0], 'File Selected');
                }
            }
        }

        function mnClearDropzone(wrap) {
            if (!wrap) return;
            const fileInput = wrap.querySelector('input[type="file"]');
            const base64Input = wrap.querySelector('input[type="hidden"]');
            const promptEl = wrap.querySelector('.mn-dropzone-prompt');
            const previewEl = wrap.querySelector('.mn-dropzone-preview');
            const dropzone = wrap.querySelector('.mn-image-dropzone');

            if (fileInput) fileInput.value = '';
            if (base64Input) base64Input.value = '';
            if (promptEl) promptEl.classList.remove('d-none');
            if (previewEl) {
                previewEl.classList.add('d-none');
                previewEl.classList.remove('d-flex');
            }
            if (dropzone) {
                dropzone.classList.remove('has-file');
            }
        }

        // Listen for Ctrl+V / Cmd+V image paste events
        document.addEventListener('paste', function(e) {
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData || !clipboardData.items) return;

            let imageItem = null;
            for (let i = 0; i < clipboardData.items.length; i++) {
                if (clipboardData.items[i].type.indexOf('image') !== -1) {
                    imageItem = clipboardData.items[i];
                    break;
                }
            }

            if (!imageItem) return;

            const file = imageItem.getAsFile();
            if (!file) return;

            const activeHover = document.querySelector('.mn-image-dropzone-wrap:hover');
            const activeModal = document.querySelector('.modal.show .mn-image-dropzone-wrap');
            const activeCollapse = document.querySelector('.collapse.show .mn-image-dropzone-wrap');
            const firstDropzone = document.querySelector('.mn-image-dropzone-wrap');

            const targetWrap = activeHover || activeModal || activeCollapse || firstDropzone;

            if (targetWrap) {
                e.preventDefault();
                mnAttachImageToDropzone(targetWrap, file, '📋 Pasted from Clipboard!');
            }
        });

        // Setup Drag & Drop listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.mn-image-dropzone').forEach(dropzone => {
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropzone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.add('drag-over');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropzone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('drag-over');
                    }, false);
                });

                dropzone.addEventListener('drop', (e) => {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    if (files && files[0] && files[0].type.startsWith('image/')) {
                        const wrap = dropzone.closest('.mn-image-dropzone-wrap');
                        mnAttachImageToDropzone(wrap, files[0], '📁 Dropped Image');
                    }
                }, false);
            });
        });

        // Select All & Bulk Delete Functions for Houses Table
        function toggleSelectAllHouses(masterCb) {
            const visibleRowCbs = Array.from(document.querySelectorAll('.house-row'))
                .filter(tr => tr.style.display !== 'none')
                .map(tr => tr.querySelector('.house-cb'))
                .filter(Boolean);
                
            visibleRowCbs.forEach(cb => {
                cb.checked = masterCb.checked;
            });
            updateBulkDeleteState();
        }

        function updateBulkDeleteState() {
            const checkedCbs = document.querySelectorAll('.house-cb:checked');
            const count = checkedCbs.length;
            const btn = document.getElementById('btnBulkDeleteHouses');
            const countSpan = document.getElementById('selectedHousesCount');
            const masterCb = document.getElementById('selectAllHousesCb');
            
            const visibleRowCbs = Array.from(document.querySelectorAll('.house-row'))
                .filter(tr => tr.style.display !== 'none')
                .map(tr => tr.querySelector('.house-cb'))
                .filter(Boolean);

            if (countSpan) countSpan.textContent = count;
            
            if (btn) {
                if (count > 0) {
                    btn.classList.remove('d-none');
                    btn.classList.add('d-inline-flex');
                } else {
                    btn.classList.add('d-none');
                    btn.classList.remove('d-inline-flex');
                }
            }

            if (masterCb) {
                if (visibleRowCbs.length > 0 && checkedCbs.length === visibleRowCbs.length) {
                    masterCb.checked = true;
                    masterCb.indeterminate = false;
                } else if (checkedCbs.length > 0) {
                    masterCb.checked = false;
                    masterCb.indeterminate = true;
                } else {
                    masterCb.checked = false;
                    masterCb.indeterminate = false;
                }
            }
        }

        function mnConfirmBulkDeleteHouses(event) {
            if (event) event.preventDefault();
            const checkedCbs = document.querySelectorAll('.house-cb:checked');
            const count = checkedCbs.length;
            if (count === 0) {
                return false;
            }
            const msg = `Are you sure you want to delete ${count} selected house(s)? This action cannot be undone.`;
            mnConfirmAction(event, msg, 'assets/img/delete_icon.png', `Yes, Delete (${count})`);
            return false;
        }
    </script>
    <script src="assets/js/anti-devtools.js"></script>
</body>
</html>
