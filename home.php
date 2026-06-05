<?php
// Enhanced File Manager for Cybersecurity Education
// Lab Environment Only - Use Responsibly

// Error reporting for debugging (remove in production)
error_reporting(0);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Basic authentication (customize for your lab)
$valid_passwords = array("admin" => "lab123");
$valid_users = array_keys($valid_passwords);
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if (!isset($valid_passwords[$user]) || $valid_passwords[$user] != $pass) {
    header('WWW-Authenticate: Basic realm="Lab Environment"');
    header('HTTP/1.0 401 Unauthorized');
    die('Authentication required');
}

// Get current directory path - FIXED NAVIGATION
$current_path = $_GET['path'] ?? '.';
$current_path = str_replace(array('../', '..\\'), '', $current_path);
$current_path = trim($current_path, '/\\');

// Get absolute path
if ($current_path === '' || $current_path === '.') {
    $full_path = realpath('.');
    $current_path = '.';
} else {
    $full_path = realpath($current_path);
    if ($full_path === false) {
        // If path doesn't exist, fallback to current directory
        $full_path = realpath('.');
        $current_path = '.';
    }
}

// Handle file operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostOperations($full_path, $current_path);
}

// Handle GET operations (download, delete)
handleGetOperations($full_path, $current_path);

function handlePostOperations($full_path, $current_path) {
    if (isset($_POST['new_dir']) && !empty($_POST['new_dir'])) {
        $new_dir = sanitizeFilename($_POST['new_dir']);
        mkdir($full_path . DIRECTORY_SEPARATOR . $new_dir, 0755, true);
    }
    
    if (isset($_POST['new_file']) && !empty($_POST['new_file'])) {
        $new_file = sanitizeFilename($_POST['new_file']);
        touch($full_path . DIRECTORY_SEPARATOR . $new_file);
    }
    
    if (isset($_FILES['uploaded_file'])) {
        $upload_file = $full_path . DIRECTORY_SEPARATOR . sanitizeFilename($_FILES['uploaded_file']['name']);
        move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $upload_file);
    }
    
    if (isset($_POST['file_content']) && isset($_POST['file_name'])) {
        $file_name = sanitizeFilename($_POST['file_name']);
        file_put_contents($full_path . DIRECTORY_SEPARATOR . $file_name, $_POST['file_content']);
    }
    
    if (isset($_POST['permissions']) && isset($_POST['perm_file'])) {
        $perm_file = sanitizeFilename($_POST['perm_file']);
        chmod($full_path . DIRECTORY_SEPARATOR . $perm_file, octdec($_POST['permissions']));
    }
    
    if (isset($_POST['new_name']) && isset($_POST['old_name'])) {
        $old_name = sanitizeFilename($_POST['old_name']);
        $new_name = sanitizeFilename($_POST['new_name']);
        rename($full_path . DIRECTORY_SEPARATOR . $old_name, $full_path . DIRECTORY_SEPARATOR . $new_name);
    }
    
    // Redirect to avoid form resubmission
    header("Location: ?path=" . urlencode($current_path));
    exit;
}

function handleGetOperations($full_path, $current_path) {
    if (isset($_GET['download']) && !empty($_GET['download'])) {
        $file_to_download = sanitizeFilename($_GET['download']);
        $file_path = $full_path . DIRECTORY_SEPARATOR . $file_to_download;
        
        if (file_exists($file_path) && is_file($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }
    
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $file_to_delete = sanitizeFilename($_GET['delete']);
        $file_path = $full_path . DIRECTORY_SEPARATOR . $file_to_delete;
        
        if (file_exists($file_path)) {
            if (is_dir($file_path)) {
                // Recursive directory deletion
                deleteDirectory($file_path);
            } else {
                unlink($file_path);
            }
            header("Location: ?path=" . urlencode($current_path));
            exit;
        }
    }
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9\.\_\-]/', '', $filename);
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Get directory listing
$files = scandir($full_path);
$files = array_diff($files, array('.', '..'));

// Separate directories and files
$directories = array();
$file_list = array();

foreach ($files as $file) {
    $file_path = $full_path . DIRECTORY_SEPARATOR . $file;
    if (is_dir($file_path)) {
        $directories[] = $file;
    } else {
        $file_list[] = $file;
    }
}

// Sort alphabetically
sort($directories);
sort($file_list);

// Combine directories first, then files
$files = array_merge($directories, $file_list);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lab File Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <style>
        :root {
            --neon-green: #0f0;
            --matrix-bg: #000;
            --text-white: #fff;
        }
        body {
            background: var(--matrix-bg);
            color: var(--text-white);
            font-family: 'Courier New', monospace;
        }
        .CodeMirror {
            border: 1px solid var(--neon-green);
            height: 500px;
            font-family: 'Courier New';
            font-size: 14px;
        }
        .folder { color: #4faaff; }
        .file { color: #ffffff; }
        .folder::before { content: "📁 "; }
        .file::before { content: "📄 "; }
        .alert-matrix {
            background: #001100;
            border: 1px solid var(--neon-green);
        }
        a { color: var(--text-white); text-decoration: none; }
        a:hover { color: var(--neon-green); }
        .btn-outline-success {
            color: var(--neon-green);
            border-color: var(--neon-green);
        }
        .btn-outline-success:hover {
            background: var(--neon-green);
            color: var(--matrix-bg);
        }
        .list-group-item {
            background: #111 !important;
            color: var(--text-white) !important;
        }
        .breadcrumb {
            background: #222;
        }
        .nav-link { color: #0f0; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🔐 Lab File Manager</h1>
        
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="?path=." class="text-success">Root</a>
                </li>
                <?php
                $path_parts = array_filter(explode('/', $current_path));
                $current_breadcrumb = '';
                foreach ($path_parts as $part) {
                    $current_breadcrumb .= '/' . $part;
                    echo '<li class="breadcrumb-item"><a href="?path=' . urlencode(ltrim($current_breadcrumb, '/')) . '" class="text-success">' . htmlspecialchars($part) . '</a></li>';
                }
                ?>
            </ol>
        </nav>

        <div class="mb-4">
            <p>Current Directory: <code class="text-success"><?php echo htmlspecialchars($current_path); ?></code></p>
            <p>Full Path: <code class="text-info"><?php echo htmlspecialchars($full_path); ?></code></p>
        </div>

        <!-- Navigation to Parent Directory -->
        <?php if ($current_path !== '.'): ?>
        <div class="mb-3">
            <a href="?path=<?php echo urlencode(dirname($current_path) === '.' ? '.' : dirname($current_path)); ?>" 
               class="btn btn-outline-success btn-sm">
                📂 Go to Parent Directory
            </a>
        </div>
        <?php endif; ?>

        <!-- Action Toolbar -->
        <div class="mb-4 border-bottom pb-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <form method="post">
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($current_path); ?>">
                        <div class="input-group">
                            <input type="text" name="new_dir" placeholder="New folder" class="form-control bg-dark text-white">
                            <button class="btn btn-outline-success">Create Folder</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <form method="post">
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($current_path); ?>">
                        <div class="input-group">
                            <input type="text" name="new_file" placeholder="New file" class="form-control bg-dark text-white">
                            <button class="btn btn-outline-success">Create File</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($current_path); ?>">
                        <div class="input-group">
                            <input type="file" name="uploaded_file" class="form-control bg-dark text-white">
                            <button class="btn btn-outline-success">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- File List -->
        <div class="list-group">
            <?php if (empty($files)): ?>
                <div class="list-group-item text-center text-muted">
                    This directory is empty
                </div>
            <?php endif; ?>

            <?php foreach ($files as $file): 
                $file_path = $full_path . DIRECTORY_SEPARATOR . $file;
                $is_dir = is_dir($file_path);
                $file_size = $is_dir ? '' : formatSize(filesize($file_path));
                $perms = substr(sprintf('%o', fileperms($file_path)), -4);
                $modified = date('Y-m-d H:i:s', filemtime($file_path));
                $file_id = md5($file . $current_path);
            ?>
            <div class="list-group-item d-flex align-items-center">
                <div class="flex-grow-1">
                    <span class="<?php echo $is_dir ? 'folder' : 'file'; ?>">
                        <?php if ($is_dir): ?>
                            <a href="?path=<?php echo urlencode($current_path . '/' . $file); ?>" class="text-white">
                                <strong><?php echo htmlspecialchars($file); ?></strong>
                            </a>
                        <?php else: ?>
                            <?php echo htmlspecialchars($file); ?>
                        <?php endif; ?>
                        <?php if (!$is_dir): ?>
                            <small class="text-muted ms-2">(<?php echo $file_size; ?>)</small>
                        <?php endif; ?>
                    </span>
                    <small class="text-muted ms-3">
                        Perms: <?php echo $perms; ?> | Modified: <?php echo $modified; ?>
                    </small>
                </div>
                
                <div class="btn-group">
                    <?php if (!$is_dir): ?>
                        <button class="btn btn-sm btn-outline-success" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editModal-<?php echo $file_id; ?>">
                            Edit
                        </button>
                        <a href="?path=<?php echo urlencode($current_path); ?>&download=<?php echo urlencode($file); ?>" 
                           class="btn btn-sm btn-outline-success">
                            Download
                        </a>
                    <?php else: ?>
                        <a href="?path=<?php echo urlencode($current_path . '/' . $file); ?>" 
                           class="btn btn-sm btn-outline-info">
                            Open
                        </a>
                        <a href="?path=<?php echo urlencode($current_path); ?>&download=<?php echo urlencode($file); ?>" 
                           class="btn btn-sm btn-outline-success">
                            Download
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-sm btn-outline-warning" 
                            data-bs-toggle="modal" 
                            data-bs-target="#permsModal-<?php echo $file_id; ?>">
                        Perms
                    </button>
                    
                    <button class="btn btn-sm btn-outline-primary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#renameModal-<?php echo $file_id; ?>">
                        Rename
                    </button>
                    
                    <a href="?path=<?php echo urlencode($current_path); ?>&delete=<?php echo urlencode($file); ?>" 
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete <?php echo htmlspecialchars($file); ?>?')">
                        Delete
                    </a>
                </div>
            </div>

            <!-- Modals for each file -->
            <?php if (!$is_dir): ?>
            <!-- Edit Modal -->
            <div class="modal fade" id="editModal-<?php echo $file_id; ?>" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content bg-dark text-white">
                        <div class="modal-header">
                            <h5 class="modal-title">Editing: <?php echo htmlspecialchars($file); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="path" value="<?php echo htmlspecialchars($current_path); ?>">
                            <div class="modal-body p-0">
                                <textarea id="editor-<?php echo $file_id; ?>" 
                                          name="file_content" 
                                          class="form-control bg-dark text-white" 
                                          rows="15"><?php 
                                    echo htmlspecialchars(@file_get_contents($file_path) ?: 'File not readable or empty!');
                                ?></textarea>
                                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($file); ?>">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-outline-success">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Permissions Modal -->
            <div class="modal fade" id="permsModal-<?php echo $file_id; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content bg-dark text-white">
                        <div class="modal-header">
                            <h5 class="modal-title">Permissions: <?php echo htmlspecialchars($file); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="path" value="<?php echo htmlspecialchars($current_path); ?>">
                            <div class="modal-body">
                                <label class="form-label">Octal Permissions:</label>
                                <input type="text" name="permissions" 
                                       value="<?php echo $perms; ?>"
                                       class="form-control bg-dark text-white"
                                       placeholder="e.g., 0644, 0755">
                                <small class="text-muted">Use octal format (e.g., 0644 for files, 0755 for directories)</small>
                                <input type="hidden" name="perm_file" value="<?php echo htmlspecialchars($file); ?>">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-outline-success">Set Permissions</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Rename Modal -->
            <div class="modal fade" id="renameModal-<?php echo $file_id; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content bg-dark text-white">
                        <div class="modal-header">
                            <h5 class="modal-title">Rename: <?php echo htmlspecialchars($file); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="path" value="<?php echo htmlspecialchars($current_path); ?>">
                            <div class="modal-body">
                                <label class="form-label">New Name:</label>
                                <input type="text" name="new_name" 
                                       value="<?php echo htmlspecialchars($file); ?>"
                                       class="form-control bg-dark text-white">
                                <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($file); ?>">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-outline-success">Rename</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/meta.js"></script>
    <script>
        // Initialize CodeMirror editor
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                const textarea = this.querySelector('textarea');
                if (textarea && !textarea._codemirror) {
                    const editor = CodeMirror.fromTextArea(textarea, {
                        lineNumbers: true,
                        theme: "monokai",
                        mode: "text/plain",
                        matchBrackets: true,
                        autoCloseBrackets: true,
                        indentUnit: 4
                    });
                    textarea._codemirror = editor;
                    setTimeout(() => editor.refresh(), 100);
                }
            });
        });
    </script>
</body>
</html>