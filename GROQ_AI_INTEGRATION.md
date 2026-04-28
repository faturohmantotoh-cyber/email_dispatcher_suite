# Groq AI Integration Guide

## Overview

Email Dispatcher Suite sekarang mendukung **Groq AI-powered matching** untuk pencocokan lampiran ke penerima yang lebih cerdas dan akurat.

## Fitur AI Matching

- **Semantic Understanding**: AI mengerti variasi nama, inisial, dan konteks
- **Lebih Akurat**: Menggunakan model Llama 3 via Groq API untuk matching yang lebih pintar
- **Fallback Otomatis**: Jika API key tidak tersedia, sistem otomatis fallback ke classic similarity matching
- **Toggle UI**: Pilih antara AI atau Classic matching langsung dari halaman compose

## Fitur AI Assistant

- **Chat Widget**: Widget chat floating di pojok kanan bawah
- **Groq-Powered**: Menggunakan Groq API untuk respon yang lebih cerdas dan kontekstual
- **Conversation Memory**: Mengingat 10 pesan terakhir untuk konteks percakapan
- **Fallback Otomatis**: Jika API gagal, fallback ke respon simulated
- **Quick Actions**: Tombol cepat untuk bantuan, dokumentasi, troubleshooting

## Cara Setup

### 1. Daftar di Groq

1. Buka https://console.groq.com
2. Sign up atau login
3. Buat API Key baru di menu "API Keys"

### 2. Set Environment Variable

#### Windows (Command Prompt)
```cmd
setx GROQ_API_KEY "your_api_key_here"
```

#### Windows (PowerShell)
```powershell
[System.Environment]::SetEnvironmentVariable('GROQ_API_KEY', 'your_api_key_here', 'User')
```

#### Windows (System-wide - butuh restart Laragon)
```cmd
setx GROQ_API_KEY "your_api_key_here" /M
```

### 3. Restart Web Server

Setelah set environment variable, restart Laragon atau Apache untuk memastikan variable terbaca.

## Cara Menggunakan

### AI Matching (Pencocokan Lampiran)

1. Buka halaman **Kirim Email (Similarity)**
2. Scroll ke bawah ke bagian "Ambang kemiripan"
3. Centang checkbox **"🤖 Gunakan AI Matching (Groq)"**
4. Klik **"Preview & Cocokkan"**
5. Sistem akan menggunakan Groq API untuk matching lampiran ke penerima

### AI Assistant (Chat Widget)

1. Buka halaman mana saja di aplikasi
2. Klik tombol **AI** di pojok kanan bawah
3. Ketik pertanyaan Anda
4. AI akan merespon menggunakan Groq API
5. Gunakan quick actions untuk bantuan cepat

## Perbedaan AI vs Classic

### AI Matching
| Classic Matching | AI Matching (Groq) |
|-----------------|-------------------|
| Menggunakan `similar_text()` PHP | Menggunakan Llama 3 via Groq API |
| String-based similarity | Semantic understanding |
| Tidak mengerti konteks | Mengerti variasi nama, inisial, konteks |
| Cepat (local) | Sedikit lebih lambat (network request) |
| Tidak butuh API key | Butuh GROQ_API_KEY |

### AI Assistant
| Simulated Response | Groq-Powered |
|-------------------|--------------|
| Keyword-based responses | Contextual AI responses |
| Terbatas pada predefined responses | Bisa menjawab berbagai pertanyaan |
| Tidak mengerti konteks percakapan | Mengingat 10 pesan terakhir |
| Cepat (local) | Sedikit lebih lambat (network request) |
| Tidak butuh API key | Butuh GROQ_API_KEY |

## Contoh Use Case

### Classic Matching
- File: `budi_santoso.pdf`
- Penerima: `Budi Santoso <budi.santoso@example.com>`
- Skor: ~85-90

### AI Matching
- File: `B_Santoso_2024.pdf`
- Penerima: `Budi Santoso <budi.santoso@example.com>`
- Skor: ~95 (AI mengerti B_Santoso = Budi Santoso)

- File: `3M_Indonesia_PO.pdf`
- Penerima: `PT 3M Indonesia <contact@3m.co.id>`
- Skor: ~98 (AI mengerti brand dan context)

## Troubleshooting

### AI Matching tidak bekerja
1. Pastikan `GROQ_API_KEY` sudah di-set di environment variable
2. Restart web server (Laragon/Apache)
3. Cek error log di `storage/logs/` untuk pesan error dari Groq API

### Classic matching digunakan meskipun AI diaktifkan
- Ini normal jika `GROQ_API_KEY` tidak tersedia atau API call gagal
- Sistem otomatis fallback ke classic matching untuk memastikan sistem tetap bekerja

### API call lambat
- Groq sangat cepat, tapi network request tetap butuh waktu
- Jika terlalu lambat, gunakan classic matching saja

## Keamanan

- API key disimpan di environment variable, tidak di-hardcode di code
- API call timeout set ke 10 detik untuk mencegah hang
- Error dari API di-log ke error log untuk debugging

## Biaya

- Groq menawarkan free tier yang generous
- Cek pricing di https://groq.com/pricing
- Untuk use case email dispatcher biasa, biaya sangat minimal

## Konfigurasi Lanjutan

Jika ingin mengubah model yang digunakan, edit di `config.php`:

```php
define('GROQ_MODEL', 'llama3-8b-8192'); // Default
// Alternatif:
// define('GROQ_MODEL', 'llama3-70b-8192'); // Lebih pintar, lebih lambat
// define('GROQ_MODEL', 'mixtral-8x7b-32768'); // Model alternatif
```

## Support

Jika ada masalah dengan integrasi Groq:
1. Cek error log di `storage/logs/`
2. Pastikan internet connection stabil
3. Verifikasi API key valid di Groq Console
