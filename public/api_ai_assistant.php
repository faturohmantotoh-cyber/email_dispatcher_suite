<?php
/**
 * AI Assistant API Endpoint
 * Handles chat requests using Groq API
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/util.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$message = trim($data['message']);
$conversationHistory = $data['history'] ?? [];

// Check if Groq API key is available
if (empty(GROQ_API_KEY)) {
    // Fallback to simulated response
    $response = generateSimulatedResponse($message);
    echo json_encode(['response' => $response, 'source' => 'simulated']);
    exit;
}

// Build conversation context
$messages = [
    [
        'role' => 'system',
        'content' => 'You are a helpful AI assistant for the Email Dispatcher Suite application. You help users with:\n' .
                   '- Contact management (add, edit, delete)\n' .
                   '- Email composition and sending\n' .
                   '- Email templates\n' .
                   '- Troubleshooting issues\n' .
                   '- Usage guidance\n\n' .
                   'Be concise, friendly, and helpful. Respond in Indonesian unless the user asks in English.'
    ]
];

// Add conversation history (last 10 messages to stay within context limits)
$recentHistory = array_slice($conversationHistory, -10);
foreach ($recentHistory as $msg) {
    $role = $msg['type'] === 'user' ? 'user' : 'assistant';
    $messages[] = [
        'role' => $role,
        'content' => $msg['content']
    ];
}

// Add current message
$messages[] = [
    'role' => 'user',
    'content' => $message
];

// Call Groq API
$ch = curl_init(GROQ_API_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . GROQ_API_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => GROQ_MODEL,
    'messages' => $messages,
    'temperature' => 0.7,
    'max_tokens' => 500
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    // Fallback to simulated response on error
    error_log("Groq API error for AI assistant: HTTP $httpCode, Response: $response");
    $simulatedResponse = generateSimulatedResponse($message);
    echo json_encode(['response' => $simulatedResponse, 'source' => 'simulated_fallback']);
    exit;
}

$data = json_decode($response, true);
if (!isset($data['choices'][0]['message']['content'])) {
    error_log("Groq API invalid response for AI assistant: $response");
    $simulatedResponse = generateSimulatedResponse($message);
    echo json_encode(['response' => $simulatedResponse, 'source' => 'simulated_fallback']);
    exit;
}

$aiResponse = $data['choices'][0]['message']['content'];
echo json_encode(['response' => $aiResponse, 'source' => 'groq']);

/**
 * Generate simulated response (fallback when Groq is not available)
 */
function generateSimulatedResponse($userMessage) {
    $lowerMessage = strtolower($userMessage);

    if (strpos($lowerMessage, 'kontak') !== false || strpos($lowerMessage, 'contact') !== false) {
        return 'Untuk manajemen kontak, Anda bisa:\n\n• Add kontak baru di halaman Contacts\n• Import dari file CSV/Excel\n• Edit atau delete kontak yang ada\n• Buat grup untuk segmentasi\n\nButuh bantuan lebih spesifik?';
    }
    if (strpos($lowerMessage, 'email') !== false || strpos($lowerMessage, 'kirim') !== false || strpos($lowerMessage, 'send') !== false) {
        return 'Untuk mengirim email:\n\n1. Buka halaman Compose\n2. Pilih template atau buat baru\n3. Pilih penerima (kontak/grup)\n4. Upload attachment jika perlu\n5. Klik Send untuk mengirim\n\nEmail akan diproses dan Anda bisa tracking status di halaman Logs.';
    }
    if (strpos($lowerMessage, 'template') !== false) {
        return 'Template email memudahkan Anda mengirim email dengan format konsisten. Anda bisa:\n\n• Buat template baru di halaman Templates\n• Gunakan placeholder seperti {nama}, {email}\n• Edit atau delete template yang ada\n• Preview template sebelum digunakan';
    }
    if (strpos($lowerMessage, 'error') !== false || strpos($lowerMessage, 'gagal') !== false) {
        return 'Maaf ada kendala. Mari troubleshooting:\n\n1. Cek koneksi internet\n2. Pastikan konfigurasi SMTP sudah benar\n3. Lihat detail error di halaman Logs\n4. Coba refresh halaman\n\nJika masih ada masalah, ceritakan detail error-nya ya.';
    }
    if (strpos($lowerMessage, 'terima kasih') !== false || strpos($lowerMessage, 'thanks') !== false) {
        return 'Sama-sama! Senang bisa membantu. 😊\n\nAda lagi yang ingin ditanyakan?';
    }
    if (strpos($lowerMessage, 'groq') !== false || strpos($lowerMessage, 'ai') !== false) {
        return 'Sekarang sistem sudah mendukung Groq AI untuk:\n\n1. **AI Matching** - Pencocokan lampiran ke penerima yang lebih cerdas (aktifkan di halaman Compose)\n2. **AI Assistant** - Chat widget ini sekarang menggunakan Groq untuk respon yang lebih pintar\n\nPastikan GROQ_API_KEY sudah diset di environment variable.';
    }

    $responses = [
        'Pertanyaan menarik! Bisa jelaskan lebih detail?',
        'Saya mengerti. Mari kita bahas lebih lanjut.',
        'Baik, saya siap membantu. Apa yang perlu saya jelaskan?',
        'Tentu! Saya bisa membantu dengan hal tersebut.'
    ];
    return $responses[array_rand($responses)];
}
