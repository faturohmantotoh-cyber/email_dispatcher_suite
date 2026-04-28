<?php
/**
 * api_contact_upload.php
 * Handle:
 * 1. Download contact template (CSV)
 * 2. Upload & Process contact CSV (skip duplicate emails)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

// === ACTION: DOWNLOAD TEMPLATE ===
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    // Get next ID (last id + 1)
    $lastId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM contacts")->fetchColumn();
    $nextId = $lastId + 1;
    
    // Generate CSV template
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contact_template_id_' . $nextId . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Write header
    fputcsv($output, ['Name', 'Email']);
    
    // Write 5 example rows (untuk template)
    fputcsv($output, ['Contoh 1', 'contoh1@example.com']);
    fputcsv($output, ['Contoh 2', 'contoh2@example.com']);
    fputcsv($output, ['Contoh 3', 'contoh3@example.com']);
    fputcsv($output, ['Contoh 4', 'contoh4@example.com']);
    fputcsv($output, ['Contoh 5', 'contoh5@example.com']);
    
    fclose($output);
    exit;
}

// === ACTION: UPLOAD & PROCESS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['contact_file'])) {
    header('Content-Type: application/json');
    
    try {
        // Validate file
        $file = $_FILES['contact_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if (!$file['tmp_name'] || !file_exists($file['tmp_name'])) {
            throw new Exception('File tidak ditemukan');
        }
        
        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File terlalu besar (maks 10MB)');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Allowed MIME types for CSV
        $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('File harus berformat CSV. MIME: ' . $mimeType);
        }
        
        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new Exception('File harus berformat CSV');
        }
        
        // Read and process CSV
        $fp = fopen($file['tmp_name'], 'r');
        if (!$fp) {
            throw new Exception('Tidak bisa membuka file CSV');
        }
        
        // Read header
        $header = fgetcsv($fp);
        if ($header === false || count($header) === 0) {
            fclose($fp);
            throw new Exception('File CSV tidak valid (header tidak terbaca)');
        }
        
        // Strip BOM
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        
        // Map header columns
        $map = [];
        foreach ($header as $i => $h) {
            $map[strtolower(trim((string)$h))] = $i;
        }
        
        $hasName = array_key_exists('name', $map);
        $hasEmail = array_key_exists('email', $map);
        
        if (!$hasEmail) {
            fclose($fp);
            throw new Exception('CSV harus memiliki kolom "Email"');
        }
        
        // Get existing emails to skip duplicates
        $existingEmails = [];
        $existingResult = $pdo->query("SELECT email FROM contacts WHERE email IS NOT NULL AND email != ''");
        foreach ($existingResult->fetchAll(PDO::FETCH_COLUMN) as $email) {
            $existingEmails[strtolower(trim($email))] = true;
        }
        
        // Process CSV data
        $pdo->beginTransaction();
        try {
            $summary = [
                'processed' => 0,
                'inserted' => 0,
                'skipped_duplicate' => 0,
                'skipped_empty' => 0,
                'errors' => 0,
                'notes' => []
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO contacts(display_name, email, source, last_synced)
                VALUES(?, ?, 'Manual Upload', NOW())
            ");
            
            while (($row = fgetcsv($fp)) !== false) {
                $summary['processed']++;
                
                $email = trim((string)($row[$map['email']] ?? ''));
                if ($email === '') {
                    $summary['skipped_empty']++;
                    continue;
                }
                
                // Check for duplicate
                $emailLower = strtolower($email);
                if (isset($existingEmails[$emailLower])) {
                    $summary['skipped_duplicate']++;
                    continue;
                }
                
                // Get name
                $name = '';
                if ($hasName) {
                    $name = trim((string)($row[$map['name']] ?? ''));
                }
                if ($name === '') {
                    $name = $email;
                }
                
                try {
                    $stmt->execute([$name, $email]);
                    $summary['inserted']++;
                    // Mark email as processed
                    $existingEmails[$emailLower] = true;
                } catch (Exception $ex) {
                    $summary['errors']++;
                    $summary['notes'][] = "Error email {$email}: " . $ex->getMessage();
                }
            }
            
            fclose($fp);
            $pdo->commit();
            
            // Add warnings if needed
            if ($summary['skipped_duplicate'] > 0) {
                $summary['notes'][] = "⚠️ {$summary['skipped_duplicate']} email sudah ada di database (tidak diupdate)";
            }
            if ($summary['skipped_empty'] > 0) {
                $summary['notes'][] = "⚠️ {$summary['skipped_empty']} baris kosong (tidak ada email)";
            }
            
            echo json_encode([
                'success' => true,
                'summary' => $summary
            ]);
            
        } catch (Exception $e) {
            fclose($fp);
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Default: method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
