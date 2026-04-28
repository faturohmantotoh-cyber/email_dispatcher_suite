# Outlook COM Error Troubleshooting

## Error: RPC_E_CALL_REJECTED (0x80010001)

```
[ERROR] Gagal membuat Outlook COM. Pastikan Outlook terinstall & profil default tersedia.
Detail: Retrieving the COM class factory for component with CLSID {0006F03A-0000-0000-C000-000000000046}
failed due to the following error: 80010001 Call was rejected by callee.
(Exception from HRESULT: 0x80010001 (RPC_E_CALL_REJECTED))
```

### Apa Ini?
Outlook sedang **sibuk** atau **terkunci** dan menolak koneksi PowerShell (RPC call rejected).

### Penyebab Umum
1. ✗ Outlook sedang terbuka dan sedang processing
2. ✗ Multiple aplikasi mengakses Outlook bersamaan
3. ✗ Outlook instance stuck/frozen
4. ✗ Background sync/update sedang berjalan
5. ✗ Add-ins yang lambat sedang loading

---

## ✅ Solusi Cepat (Try This First)

### **Solusi #1: Tutup Outlook**
```
1. Klik File → Exit di Outlook
2. Pastikan OUTLOOK.EXE sudah tidak ada di Task Manager
3. Tunggu 5 detik
4. Klik "Kirim Email" di aplikasi
5. PowerShell akan membuat instance Outlook baru
```

### **Solusi #2: Restart Komputer**
Jika tetap error setelah tutup Outlook:
```
Restart → Coba lagi
```

---

## 🔧 Teknis: Auto-Recovery (Sudah Diterapkan)

Script PowerShell sekarang memiliki **retry logic otomatis**:

```
Attempt 1 (Immediate) → Failed?
    ↓ Wait 2 seconds
Attempt 2 → Failed?
    ↓ Kill Outlook process
    ↓ Wait 4 seconds
Attempt 3 (Fresh Outlook) → Failed?
    ↓ Error message dengan solusi
```

### Apa yang Dilakukan Script
1. ✅ Retry hingga 3x dengan exponential backoff
2. ✅ Pada retry ke-2: Automatic kill Outlook yang stuck
3. ✅ Pada retry ke-3: Fresh Outlook instance
4. ✅ Jika tetap gagal: Guide ke manual troubleshooting

---

## 🛠️ Advanced Troubleshooting

### Check: Outlook Processes Running
```powershell
Get-Process OUTLOOK | Select-Object Name, Id, WorkingSet
```

### Kill All Outlook (If Stuck)
```powershell
Get-Process OUTLOOK -ErrorAction SilentlyContinue | Stop-Process -Force
```

### Test Outlook COM Manually
```powershell
$ol = New-Object -ComObject Outlook.Application
$ol.GetNamespace("MAPI").Session
```

### Check Outlook Registry
```powershell
Get-ItemProperty "HKCU:\Software\Microsoft\Office\16.0\Outlook" | Select-Object InstallRoot
```

---

## 📋 Checklist Sebelum Send Email

- [ ] Tutup Outlook (`Alt+F4`)
- [ ] Pastikan tidak ada aplikasi lain yang mengakses Outlook
- [ ] Antivirus tidak sedang scanning email folder
- [ ] Network connection stabil
- [ ] PowerShell Admin mode (jika di WinServer)

---

## 🔄 Jika Masih Error Setelah Semua

### Step 1: Clear Outlook Cache
```
Control Panel → Mail → Show Profiles → double-click default → Remove
```

### Step 2: Repair Office
```
Windows: Settings → Apps → Office → Repair
Mac: Applications → Office → Install → Repair
```

### Step 3: Switch to SMTP Alternative
Jika Outlook COM tidak bisa diperbaiki, pertimbangkan:
- Gunakan direct SMTP server (tidak perlu Outlook)
- Email akan jauh lebih cepat
- Butuh konfigurasi SMTP credentials

---

## 📊 Status Script

**Current Implementation:**
- ✅ Automatic retry (3 attempts)
- ✅ Exponential backoff (2s, 4s, 6s)
- ✅ Auto-kill stuck Outlook process
- ✅ Fresh instance creation
- ✅ Helpful error messages

**Script Location:** [ps/send_outlook_emails.ps1](ps/send_outlook_emails.ps1)

---

## 🎯 Quick Reference

| Jika | Lakukan |
|-----|---------|
| Pertama kali error | Tutup Outlook, coba lagi |
| Tetap error | Restart komputer, coba lagi |
| Error berulang | Run Advanced Troubleshooting |
| Urgent | Gunakan webmail Outlook.com sementara |

---

## Need More Help?

1. **Check logs.php** - Lihat detail error berkali-kali
2. **PowerShell output** - Pesan detail ada di send.php output
3. **Task Manager** - Lihat process apa yang locking Outlook

Script sekarang lebih robust dan akan auto-recover dari error ini! 🚀
