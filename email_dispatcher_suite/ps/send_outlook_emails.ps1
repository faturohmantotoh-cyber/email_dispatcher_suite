<#
.SYNOPSIS
  Kirim email via Outlook (COM). Mendukung pemilihan akun melalui -Account (DisplayName atau SMTP).

.DESCRIPTION
  - Jika -Account diisi → pilih akun Outlook (by SMTP/DisplayName). Jika tidak ketemu → pakai default.
  - Penerima bisa dari -To (pisahkan ; atau ,) dan/atau -ToFile (tiap baris email).
  - -Html untuk body HTML, -Attachments untuk path atau wildcard (bisa banyak).
  - -PerRecipient untuk kirim SATU email per penerima (opsional).
  - -SaveToDraftOnly untuk simpan draft (tanpa kirim).

.EXAMPLE
  .\send_outlook_emails.ps1 `
    -Account "totoh.faturohman@daihatsu.astra.co.id" `
    -To "a@x.com;b@y.com" `
    -Subject "Test" `
    -Body "<b>Halo</b>" `
    -Html

.EXAMPLE
  .\send_outlook_emails.ps1 `
    -ToFile ".\queue\to_list.txt" `
    -Subject "PO Update" `
    -BodyFile ".\templates\body.html" `
    -Attachments "D:\email order\ASAHIMAS\*.pdf","D:\email order\COMMON\lampiran.pdf" `
    -Html

.EXAMPLE
  # Kirim 1 email per penerima dengan jeda 2 detik
  .\send_outlook_emails.ps1 `
    -Account "Nama Tampilan Akun" `
    -To "a@x.com,b@y.com" `
    -Subject "Per Recipient" `
    -Body "Hello" `
    -PerRecipient `
    -DelaySeconds 2
#>

[CmdletBinding()]
param(
    # Mode 1: JSON job file (for batch processing)
    [Parameter(Mandatory=$false)]
    [string]$JobJsonPath,

    # Result JSON output path
    [Parameter(Mandatory=$false)]
    [string]$ResultJsonPath,

    # Akun Outlook: DisplayName atau SMTP Address (opsional)
    [Parameter(Mandatory=$false)]
    [string]$Account,

    # Mode 2: Direct parameters
    # Penerima (pisahkan ; atau ,) - opsional jika pakai ToFile
    [Parameter(Mandatory=$false)]
    [string]$To,

    # CC & BCC (opsional)
    [Parameter(Mandatory=$false)]
    [string]$Cc,
    [Parameter(Mandatory=$false)]
    [string]$Bcc,

    # Alternatif: daftar penerima dari file (tiap baris alamat email)
    [Parameter(Mandatory=$false)]
    [string]$ToFile,

    # Subjek & Body
    [Parameter(Mandatory=$false)]
    [string]$Subject = "No subject",
    [Parameter(Mandatory=$false)]
    [string]$Body,
    [Parameter(Mandatory=$false)]
    [string]$BodyFile,

    # Attachments (mendukung wildcard)
    [Parameter(Mandatory=$false)]
    [string[]]$Attachments,

    # Body HTML?
    [Parameter(Mandatory=$false)]
    [switch]$Html,

    # Simpan draft saja (tanpa kirim)
    [Parameter(Mandatory=$false)]
    [switch]$SaveToDraftOnly,

    # Kirim satu email per penerima?
    [Parameter(Mandatory=$false)]
    [switch]$PerRecipient,

    # Jeda antar kirim (detik) bila PerRecipient
    [Parameter(Mandatory=$false)]
    [int]$DelaySeconds = 0
)

# --- Konfigurasi umum
$ErrorActionPreference = 'Stop'

# --- Helper log
function Write-Info($msg){ Write-Host "[OK]  $msg" -ForegroundColor Green }
function Write-Warn($msg){ Write-Host "[WARN] $msg" -ForegroundColor Yellow }
function Write-Err ($msg){ Write-Host "[ERROR] $msg" -ForegroundColor Red }

# --- Helper: Kill Outlook jika stuck
function Kill-OutlookSafely {
    try {
        $outlookProccess = Get-Process OUTLOOK -ErrorAction SilentlyContinue
        if ($outlookProccess) {
            Write-Warn "Menutup Outlook processes yang stuck..."
            $outlookProccess | Stop-Process -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 3
            Write-Info "Outlook ditutup"
        }
    } catch {
        Write-Warn "Tidak bisa close Outlook: $_"
    }
}

# --- CHECK: Apakah menggunakan JSON job mode?
if (-not [string]::IsNullOrWhiteSpace($JobJsonPath)) {
    # MODE: JSON JOB FILE
    Write-Info "Processing job from: $JobJsonPath"
    
    if (-not (Test-Path $JobJsonPath)) {
        Write-Err "Job file not found: $JobJsonPath"
        exit 1
    }
    
    try {
        $jobJson = Get-Content -Path $JobJsonPath -Encoding UTF8 | ConvertFrom-Json
        Write-Info "Job loaded: $($jobJson.subject)"
    } catch {
        Write-Err "Failed to parse job JSON: $_"
        exit 1
    }
    
    # Convert JSON to simple parameters
    $To = $null
    $Body = $jobJson.body
    $Cc = $jobJson.cc
    $Subject = $jobJson.subject
    $Html = $true  # Always HTML for JSON jobs
    
    # We'll process items one by one below
    $isJsonMode = $true
    $jsonItems = $jobJson.items
} else {
    $isJsonMode = $false
}

# --- Util: split penerima "a@x.com;b@y.com" / "a@x.com,b@y.com"
function Split-Recipients([string]$s){
    if ([string]::IsNullOrWhiteSpace($s)) { return @() }
    return ($s -split '[;,]' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' } | Select-Object -Unique)
}

# --- Util: ekspansi list file dari patterns (support wildcard)
function Get-FilesFromPatterns([string[]]$patterns){
    $out = @()
    foreach ($p in ($patterns | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })) {
        try {
            $items = @(Get-ChildItem -Path $p -File -ErrorAction SilentlyContinue)
            if ($items.Count -eq 0 -and (Test-Path $p)) {
                $fi = Get-Item -Path $p -ErrorAction SilentlyContinue
                if ($fi -and $fi.PSIsContainer -eq $false) { $items = @($fi) }
            }
            $out += $items
        } catch {
            Write-Warn "Attachment path error: $p → $($_.Exception.Message)"
        }
    }
    # unique by FullName
    return $out | Group-Object FullName | ForEach-Object { $_.Group[0] }
}

# --- Build recipients (for direct mode only)
$toList = @()
if (-not $isJsonMode) {
    $toList += Split-Recipients $To
    if ($ToFile -and (Test-Path $ToFile)) {
        $fileList = Get-Content -Path $ToFile -ErrorAction SilentlyContinue `
                   | ForEach-Object { $_.Trim() } `
                   | Where-Object { $_ -ne '' }
        $toList += $fileList
    }
    $toList = $toList | Select-Object -Unique
}
$ccList = Split-Recipients $Cc
$bccList = Split-Recipients $Bcc

# --- Body dari file jika disediakan
if (-not $Body -and $BodyFile -and (Test-Path $BodyFile)) {
    try {
        $Body = Get-Content -Path $BodyFile -Raw -ErrorAction Stop
    } catch {
        Write-Warn "Gagal membaca BodyFile: $BodyFile → $($_.Exception.Message)"
    }
}

# --- Validasi minimal
if (-not $isJsonMode) {
    # Direct mode: need recipients unless saving draft
    if (($toList.Count -eq 0) -and -not $SaveToDraftOnly) {
        Write-Err "Tidak ada penerima. Gunakan -To atau -ToFile (atau -SaveToDraftOnly untuk simpan draft)."
        exit 2
    }
} else {
    # JSON mode: need job items
    if (-not $jsonItems -or $jsonItems.Count -eq 0) {
        Write-Err "Job has no items to process."
        exit 2
    }
}

# --- Start Outlook
# OPTIMIZATION: Add retry logic for RPC_E_CALL_REJECTED errors
$ol = $null
$maxRetries = 3
$retryCount = 0

while ($retryCount -lt $maxRetries -and -not $ol) {
    try {
        $retryCount++
        if ($retryCount -gt 1) {
            Write-Warn "Retry $retryCount/$maxRetries untuk menghubung Outlook..."
            # On second retry, try to kill stuck Outlook process
            if ($retryCount -eq 2) {
                Kill-OutlookSafely
            }
            Start-Sleep -Seconds (2 * $retryCount)  # Exponential backoff: 2s, 4s, 6s
        }
        
        try {
            # Coba attach ke instance aktif (jika Outlook sudah terbuka)
            $ol = [Runtime.InteropServices.Marshal]::GetActiveObject("Outlook.Application")
            Write-Info "Outlook instance terbuka ditemukan, reuse"
        } catch {
            # Kalau tidak ada, buat baru
            Write-Info "Membuat instance Outlook baru..."
            $ol = New-Object -ComObject Outlook.Application
            Write-Info "Outlook instance berhasil dibuat"
        }
    } catch {
        $errorMsg = $_.Exception.Message
        if ($errorMsg -like "*0x80010001*" -or $errorMsg -like "*RPC_E_CALL_REJECTED*") {
            Write-Warn "Outlook sedang sibuk (RPC rejected). Akan mencoba lagi..."
            if ($retryCount -eq $maxRetries) {
                Write-Err "Gagal menghubung Outlook setelah $maxRetries percobaan."
                Write-Err "Solusi: Restart Outlook atau tutup aplikasi yang menggunakan Outlook."
                Write-Err "Detail: $errorMsg"
                exit 3
            }
            $ol = $null
        } else {
            Write-Err "Gagal membuat Outlook COM. Pastikan Outlook terinstall & profil default tersedia."
            Write-Err "Detail: $errorMsg"
            exit 3
        }
    }
}

if (-not $ol) {
    Write-Err "Tidak bisa membuat/connect ke Outlook setelah $maxRetries percobaan."
    exit 3
}

# Namespace MAPI
$ns = $ol.Session
if (-not $ns) { $ns = $ol.GetNamespace("MAPI") }
try { $ns.Logon() | Out-Null } catch { }

# --- Resolve Account (opsional)
$sendAccount = $null
if ($Account) {
    try {
        $allAcc = @($ns.Accounts)
        foreach ($acc in $allAcc) {
            $smtp = $null
            try { $smtp = $acc.SmtpAddress } catch { }
            $disp = $acc.DisplayName
            if ($smtp -and ($smtp -ieq $Account)) { $sendAccount = $acc; break }
            if ($disp -and ($disp -ieq $Account)) { $sendAccount = $acc; break }
        }
        if ($sendAccount) {
            Write-Info ("Akun ditemukan: " + ($sendAccount.SmtpAddress | ForEach-Object { $_ }) )
        } else {
            Write-Warn "Akun '$Account' tidak ditemukan. Akan gunakan akun default."
        }
    } catch {
        Write-Warn "Gagal enumerasi Accounts: $($_.Exception.Message). Lanjut dengan default."
    }
} else {
    Write-Info "Parameter -Account tidak diisi. Menggunakan akun default Outlook."
}

# --- Siapkan attachments (hanya untuk direct mode)
$files = @()
if (-not $isJsonMode -and $Attachments) {
    $files = Get-FilesFromPatterns $Attachments
    if ($files.Count -gt 0) {
        Write-Info ("Attachments: " + ($files | Select-Object -ExpandProperty FullName -Unique | Out-String).Trim())
    } else {
        Write-Warn "Tidak ada attachment yang cocok dari parameter -Attachments."
    }
}

# --- Fungsi kirim satu mail item (reusable)
function Send-OneMail(
    [object]$OlApp,
    [object]$SendAcc,
    [string[]]$ToArr,
    [string[]]$CcArr,
    [string[]]$BccArr,
    [string]$SubjectText,
    [string]$BodyText,
    [bool]$IsHtml,
    [object[]]$FileInfos,
    [bool]$SaveDraft
){
    $mail = $OlApp.CreateItem(0)  # olMailItem
    try {
        if ($SendAcc) { 
            try { $mail.SendUsingAccount = $SendAcc } catch { Write-Warn "Gagal set SendUsingAccount: $($_.Exception.Message)" }
        }
        if ($ToArr  -and $ToArr.Count  -gt 0) { $mail.To  = ($ToArr  -join '; ') }
        if ($CcArr  -and $CcArr.Count  -gt 0) { $mail.CC  = ($CcArr  -join '; ') }
        if ($BccArr -and $BccArr.Count -gt 0) { $mail.BCC = ($BccArr -join '; ') }

        $mail.Subject = $SubjectText

        if ($IsHtml) {
            $mail.HTMLBody = if ($BodyText) { $BodyText } else { "<html><body></body></html>" }
        } else {
            $mail.Body = $BodyText
        }

        if ($FileInfos -and $FileInfos.Count -gt 0) {
            foreach ($f in $FileInfos) {
                try { $null = $mail.Attachments.Add($f.FullName) }
                catch { Write-Warn "Gagal attach: $($f.FullName) → $($_.Exception.Message)" }
            }
        }

        if ($SaveDraft) {
            $mail.Save() | Out-Null
            Write-Info ("Draft tersimpan. Subjek: '{0}' | To: {1}" -f $SubjectText, ($ToArr -join '; '))
        } else {
            $mail.Send()
            Write-Info ("Email dikirim. Subjek: '{0}' | To: {1}" -f $SubjectText, ($ToArr -join '; '))
        }
    } catch {
        Write-Err ("Gagal membuat/mengirim email: {0}" -f $_.Exception.Message)
        throw
    }
}

# --- Kirim
$results = @()  # For JSON mode

try {
    if ($isJsonMode) {
        # MODE: JSON Job - send emails with optimization
        Write-Info "Processing $($jsonItems.Count) items from job..."
        
        # OPTIMIZATION: Pre-compile attachments to avoid repeated file lookups
        $attachmentCache = @{}
        foreach ($item in $jsonItems) {
            # Support attachment as string or array of strings
            $attList = @()
            if ($item.attachment -is [array]) {
                $attList = @($item.attachment)
            } elseif ($item.attachment) {
                $attList = @($item.attachment)
            }
            foreach ($att in $attList) {
                if ($att -and -not $attachmentCache.ContainsKey($att)) {
                    if (Test-Path $att) {
                        $attFile = @(Get-Item -Path $att -ErrorAction SilentlyContinue)
                        $attachmentCache[$att] = $attFile
                    } else {
                        $attachmentCache[$att] = @()
                    }
                }
            }
        }
        Write-Info "Attachment cache prepared: $($attachmentCache.Count) unique paths"
        
        # OPTIMIZATION: Batch email creation with reduced COM overhead
        $itemNum = 0
        $batchSize = 25  # Process 25 items before GC (increased from 5 for better performance)
        $itemsBatch = @()
        
        foreach ($item in $jsonItems) {
            $itemNum++
            $itemId = $item.id
            $toEmail = $item.to
            $attachment = $item.attachment
            
            $result = @{
                id = $itemId
                related_ids = @($item.related_ids)
                status = "failed"
                message = ""
            }
            
            try {
                if ($itemNum % $batchSize -eq 0) {
                    Write-Info "[$itemNum/$($jsonItems.Count)] Batch checkpoint"
                    [System.GC]::Collect() | Out-Null  # Force garbage collection to release COM resources
                }
                
                Write-Info "[$itemNum/$($jsonItems.Count)] Processing item $itemId to $toEmail"
                
                # OPTIMIZATION: Use cached attachments - support single or multiple
                $itemFiles = @()
                $attList = @()
                if ($item.attachment -is [array]) {
                    $attList = @($item.attachment)
                } elseif ($item.attachment) {
                    $attList = @($item.attachment)
                }
                foreach ($att in $attList) {
                    if ($attachmentCache.ContainsKey($att)) {
                        $itemFiles += $attachmentCache[$att]
                    }
                }
                
                # Parse TO emails (semicolon separated) - untuk group recipients
                $toList = Split-Recipients $toEmail
                
                # Per-item subject/body override (PO MTC feature)
                $itemSubject = if ($item.subject) { $item.subject } else { $Subject }
                $itemBody = if ($item.body) { $item.body } else { $Body }
                
                Send-OneMail -OlApp $ol `
                             -SendAcc $sendAccount `
                             -ToArr $toList `
                             -CcArr (Split-Recipients $Cc) `
                             -BccArr (Split-Recipients $Bcc) `
                             -SubjectText $itemSubject `
                             -BodyText $itemBody `
                             -IsHtml $true `
                             -FileInfos $itemFiles `
                             -SaveDraft $false
                
                $result.status = "sent"
                $result.message = "OK"
            } catch {
                $result.status = "failed"
                $result.message = $_.Exception.Message
                Write-Err ("Item $itemId failed: {0}" -f $_.Exception.Message)
            }
            
            $results += $result
        }
        
        Write-Info "All items processed: $($results.Count) results"
        
        # Write results JSON
        if ($ResultJsonPath) {
            try {
                $resultsJson = $results | ConvertTo-Json
                $resultsJson | Out-File -FilePath $ResultJsonPath -Encoding UTF8
                Write-Info "Results written to: $ResultJsonPath"
            } catch {
                Write-Err "Failed to write results JSON: $_"
            }
        }
        
    } else {
        # MODE: Direct parameters (original behavior)
        if ($PerRecipient.IsPresent -and $toList.Count -gt 0) {
            # Satu email per penerima
            $i = 0
            foreach ($addr in $toList) {
                $i++
                Write-Info ("Kirim [{0}/{1}] ke {2}" -f $i, $toList.Count, $addr)
                Send-OneMail -OlApp $ol `
                             -SendAcc $sendAccount `
                             -ToArr @($addr) `
                             -CcArr $ccList `
                             -BccArr $bccList `
                             -SubjectText $Subject `
                             -BodyText $Body `
                             -IsHtml $Html.IsPresent `
                             -FileInfos $files `
                             -SaveDraft $SaveToDraftOnly.IsPresent
                if ($DelaySeconds -gt 0 -and $i -lt $toList.Count -and -not $SaveToDraftOnly.IsPresent) {
                    Start-Sleep -Seconds $DelaySeconds
                }
            }
        } else {
            # Satu email untuk semua penerima (default)
            Send-OneMail -OlApp $ol `
                         -SendAcc $sendAccount `
                         -ToArr $toList `
                         -CcArr $ccList `
                         -BccArr $bccList `
                         -SubjectText $Subject `
                         -BodyText $Body `
                         -IsHtml $Html.IsPresent `
                         -FileInfos $files `
                         -SaveDraft $SaveToDraftOnly.IsPresent
        }
    }
}
catch {
    Write-Err "Proses berhenti dengan error: $($_.Exception.Message)"
    exit 4
}
finally {
    # Biarkan Outlook manage session/COM lifecycle
}