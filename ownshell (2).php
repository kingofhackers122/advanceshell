<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); // Disable time limit
ini_set('memory_limit', '-1'); // Disable memory limit

// ======================
// CONFIGURATION
// ======================
$base_path = realpath(__DIR__); // base is the script's directory
$requested = isset($_GET['path']) ? urldecode($_GET['path']) : '';
$current_path = realpath($base_path . DIRECTORY_SEPARATOR . ltrim($requested, '/\\'));

// Fallback to base if invalid or outside allowed dir
if ($current_path === false || strpos($current_path, $base_path) !== 0) {
    $current_path = $base_path;
}


// Security check (uncomment when ready)
// if (!$current_path || !str_starts_with($current_path, $base_path)) {
//     die("<h2 style='color: red'>Access Denied!</h2>");
// }

// ======================
// FILE OPERATIONS
// ======================
$alert = ltrim(str_replace($base_path, '', $current_path), DIRECTORY_SEPARATOR);
$current_path_encoded = urlencode($relative_path);


// Handle POST operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create Directory
    if (isset($_POST['new_dir'])) {
        $new_dir = $current_path . '/' . basename($_POST['new_dir']);
        if (!file_exists($new_dir)) {
            mkdir($new_dir, 0755) ? $alert = "Directory created!" : $alert = "Creation failed!";
        }
    }
    
    // Create File
    if (isset($_POST['new_file'])) {
        $new_file = $current_path . '/' . basename($_POST['new_file']);
        if (!file_exists($new_file)) {
            file_put_contents($new_file, '') ? $alert = "File created!" : $alert = "Creation failed!";
        }
    }
    
    // File Upload
    if (isset($_FILES['uploaded_file'])) {
        $target = $current_path . '/' . basename($_FILES['uploaded_file']['name']);
        move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $target) 
            ? $alert = "File uploaded!" 
            : $alert = "Upload failed!";
    }
    
    // Edit File
    if (isset($_POST['file_content']) && isset($_POST['file_name'])) {
        $file = $current_path . '/' . basename($_POST['file_name']);
        if (is_writable($file)) {
            if (file_put_contents($file, $_POST['file_content'])) {
                $alert = "File saved!";
                touch($file);
            } else {
                $alert = "Save failed! Check permissions!";
            }
        } else {
            $alert = "File not writable!";
        }
    }
    
    // Rename File
    if (isset($_POST['new_name']) && isset($_POST['old_name'])) {
        $old = $current_path . '/' . basename($_POST['old_name']);
        $new = $current_path . '/' . basename($_POST['new_name']);
        rename($old, $new) ? $alert = "Renamed!" : $alert = "Rename failed!";
    }
    
    // Change Permissions
    if (isset($_POST['permissions']) && isset($_POST['perm_file'])) {
        $file = $current_path . '/' . basename($_POST['perm_file']);
        chmod($file, octdec($_POST['permissions'])) 
            ? $alert = "Permissions updated!" 
            : $alert = "Permission change failed!";
    }
}

// Handle GET actions
if (isset($_GET['delete'])) {
    $target = $current_path . '/' . basename($_GET['delete']);
    if (is_dir($target)) {
        rmdir($target) ? $alert = "Directory deleted!" : $alert = "Directory not empty!";
    } else {
        unlink($target) ? $alert = "File deleted!" : $alert = "Delete failed!";
    }
}

if (isset($_GET['download'])) {
    $target = $current_path . '/' . basename($_GET['download']);
    
    if (is_dir($target)) {
        $archiveFile = $target . '.zip';
        
        // Use system zip command if available
        if (function_exists('exec')) {
            exec('zip -r ' . escapeshellarg($archiveFile) . ' ' . escapeshellarg($target));
        } else {
            // Fallback to PHP ZipArchive
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
        }

        // Stream the file in chunks
        if (file_exists($archiveFile)) {
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($archiveFile));
            header('Content-Disposition: attachment; filename="'.basename($archiveFile).'"');
            
            // Clear buffers and stream
            while (ob_get_level()) ob_end_clean();
            $handle = fopen($archiveFile, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 1024 * 1024); // 1MB chunks
                ob_flush();
                flush();
            }
            fclose($handle);
            
            // Cleanup
            unlink($archiveFile);
            exit;
        } else {
            $alert = "Archive creation failed!";
        }
    } else {
        // Direct file download with chunked reading
        if (file_exists($target)) {
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($target));
            header('Content-Disposition: attachment; filename="'.basename($target).'"');
            
            while (ob_get_level()) ob_end_clean();
            $handle = fopen($target, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 1024 * 1024);
                ob_flush();
                flush();
            }
            fclose($handle);
            exit;
        }
    }
}

// ======================
// UI RENDERING
// ======================
$parent_dir = dirname($current_path);
$is_root = ($current_path === $base_path);
$items = scandir($current_path);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Advanced File Manager</title>
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
        .folder::before { content: "📁 "; }
        .file::before { 
            content: "📄 ";
            color: var(--text-white);
        }
        .file {
            color: var(--text-white);
        }
        .alert-matrix {
            background: #001100;
            border: 1px solid var(--neon-green);
        }
        a {
            color: var(--text-white);
            text-decoration: none;
        }
        a:hover {
            color: var(--neon-green);
        }
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php if ($alert): ?>
            <div class="alert alert-matrix alert-dismissible fade show">
                <?= $alert ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h1>File Manager</h1>
        <div class="mb-4">
            <p>Current Directory: <code><?= htmlspecialchars($current_path) ?></code></p>
            <?php if (!$is_root): ?>
                <a href="?path=<?= urlencode($parent_dir) ?>" class="btn btn-outline-success btn-sm">
                    ⬆️ Up One Level
                </a>
            <?php endif; ?>
        </div>

        <!-- Action Toolbar -->
        <div class="mb-4 border-bottom pb-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <form method="post">
                        <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                        <div class="input-group">
                            <input type="text" name="new_dir" placeholder="New folder" class="form-control bg-dark text-white">
                            <button class="btn btn-outline-success">Create Folder</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <form method="post">
                        <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                        <div class="input-group">
                            <input type="text" name="new_file" placeholder="New file" class="form-control bg-dark text-white">
                            <button class="btn btn-outline-success">Create File</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
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
            <?php foreach ($items as $item): ?>
                <?php if ($item === '.' || $item === '..') continue; ?>
                <?php
                $full_path = $current_path . '/' . $item;
                $is_dir = is_dir($full_path);
                $item_encoded = urlencode($item);
                $last_modified = date('Y-m-d H:i:s', filemtime($full_path));
                ?>
                <div class="list-group-item d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="<?= $is_dir ? 'folder' : 'file' ?>">
                            <?php if ($is_dir): ?>
                                <a href="?path=<?= urlencode($full_path) ?>" class="text-white">
                                    <?= htmlspecialchars($item) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($item) ?>
                                <small class="text-muted ms-2">
                                    (<?= number_format(filesize($full_path)/1024, 2) ?> KB)
                                </small>
                            <?php endif; ?>
                        </span>
                        <small class="text-muted ms-3">
                            Perms: <?= substr(sprintf('%o', fileperms($full_path)), -4) ?>
                            | Modified: <?= $last_modified ?>
                        </small>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="btn-group">
                        <?php if (!$is_dir): ?>
                            <button class="btn btn-sm btn-outline-success" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal-<?= md5($item) ?>">
                                Edit
                            </button>
                        <?php endif; ?>
                        
                        <a href="?path=<?= $current_path_encoded ?>&download=<?= $item_encoded ?>" 
                           class="btn btn-sm btn-outline-success">
                            Download
                        </a>
                        
                        <button class="btn btn-sm btn-outline-success" 
                                data-bs-toggle="modal" 
                                data-bs-target="#permsModal-<?= md5($item) ?>">
                            Perms
                        </button>
                        
                        <button class="btn btn-sm btn-outline-success" 
                                data-bs-toggle="modal" 
                                data-bs-target="#renameModal-<?= md5($item) ?>">
                            Rename
                        </button>
                        
                        <a href="?path=<?= $current_path_encoded ?>&delete=<?= $item_encoded ?>" 
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Delete <?= htmlspecialchars($item) ?>?')">
                            Delete
                        </a>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal-<?= md5($item) ?>" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">Editing: <?= $item ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                <div class="modal-body p-0">
                                    <textarea id="editor-<?= md5($item) ?>" 
                                              name="file_content" 
                                              class="form-control bg-dark text-white" 
                                              rows="15">
                                        <?php
                                        if (is_readable($full_path)) {
                                            $content = @file_get_contents($full_path);
                                            if ($content === false) {
                                                echo "Error reading file! Check permissions.";
                                            } else {
                                                echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                                            }
                                        } else {
                                            echo "File not readable!";
                                        }
                                        ?>
                                    </textarea>
                                    <input type="hidden" name="file_name" value="<?= $item ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-outline-success">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Permissions Modal -->
                <div class="modal fade" id="permsModal-<?= md5($item) ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">Permissions: <?= $item ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                <div class="modal-body">
                                    <input type="text" name="permissions" 
                                           value="<?= substr(sprintf('%o', fileperms($full_path)), -4) ?>"
                                           class="form-control bg-dark text-white">
                                    <input type="hidden" name="perm_file" value="<?= $item ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-outline-success">Set</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Rename Modal -->
                <div class="modal fade" id="renameModal-<?= md5($item) ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">Rename: <?= $item ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <input type="hidden" name="path" value="<?= $current_path_encoded ?>">
                                <div class="modal-body">
                                    <input type="text" name="new_name" 
                                           value="<?= $item ?>"
                                           class="form-control bg-dark text-white">
                                    <input type="hidden" name="old_name" value="<?= $item ?>">
                                </div>
                                <div class="modal-footer">
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
                    setTimeout(() => editor.focus(), 100);
                }
            });
        });

        // Handle form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                if (this.querySelector('.CodeMirror')) {
                    this.querySelector('.CodeMirror').CodeMirror.save();
                }
            });
        });
    </script>
</body>
</html>
