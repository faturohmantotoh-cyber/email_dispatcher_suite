param(
    [Parameter(Mandatory=$true)][string]$Account,
    [Parameter(Mandatory=$true)][string]$OutputCsv
)

$ErrorActionPreference = 'SilentlyContinue'

# ----------------------------
# Launch or attach to Outlook
# ----------------------------
$outlook = $null
$isNewInstance = $false

try {
    $outlook = [Runtime.InteropServices.Marshal]::GetActiveObject('Outlook.Application')
    Write-Host "[OK] Connected to running Outlook instance"
} catch {
    $isNewInstance = $true
    try {
        $outlook = New-Object -ComObject Outlook.Application
        Write-Host "[OK] Opened new Outlook instance"
        Write-Host "[INFO] Waiting 5 seconds for Outlook to initialize..."
        Start-Sleep -Seconds 5
    } catch {
        Write-Host "[ERROR] Cannot open Outlook: $_" -ForegroundColor Red
        exit 1
    }
}

try {
    $ns = $outlook.Session
    Write-Host "[OK] Got Outlook Session"
} catch {
    Write-Host "[ERROR] Cannot get session: $_" -ForegroundColor Red
    exit 1
}

# ----------------------------
# Helper collections (FAST)
# ----------------------------
# De-dupe by email using HashSet (O(1) check)
$seenEmails = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
# De-dupe DL expansion (avoid cycles)
$seenDLIds   = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)

# Results (list of PSObjects)
$rows = New-Object System.Collections.Generic.List[object]

function Add-EmailRow {
    param([string]$Name, [string]$Email)
    if ([string]::IsNullOrWhiteSpace($Email)) { return }
    if ($Email.StartsWith("/o=")) { return } # skip Exchange DN
    if ($seenEmails.Add($Email)) {
        $rows.Add([PSCustomObject]@{
            Name  = if ([string]::IsNullOrWhiteSpace($Name)) { $Email } else { $Name }
            Email = $Email
        })
    }
}

# Get SMTP email from AddressEntry as robust as possible
function Get-SmtpFromAddressEntry {
    param($ae)
    if (-not $ae) { return $null }
    try {
        # 1) SMTP type direct
        if ($ae.Type -eq 'SMTP' -and $ae.Address) { return $ae.Address }

        # 2) Exchange User
        $exUser = $ae.GetExchangeUser()
        if ($exUser -and $exUser.PrimarySmtpAddress) { return $exUser.PrimarySmtpAddress }

        # 3) Contact (local)
        $contact = $ae.GetContact()
        if ($contact) {
            foreach ($p in 'Email1Address','Email2Address','Email3Address','PrimarySmtpAddress','SmtpAddress') {
                if ($contact.PSObject.Properties[$p]) {
                    $val = $contact.$p
                    if (-not [string]::IsNullOrWhiteSpace($val)) { return $val }
                }
            }
        }

        # 4) MAPI property PR_SMTP_ADDRESS
        $schema = "http://schemas.microsoft.com/mapi/proptag/0x39FE001E"
        try {
            $smtp = $ae.PropertyAccessor.GetProperty($schema)
            if (-not [string]::IsNullOrWhiteSpace($smtp)) { return $smtp }
        } catch {}

        # 5) Fallback to Address if looks like email
        if ($ae.Address -and $ae.Address -match '^[^@\s]+@[^@\s]+\.[^@\s]+$') { return $ae.Address }
    } catch {}
    return $null
}

# Expand AddressEntry (can be DL or single)
function Expand-AddressEntry {
    param(
        $ae,
        [int]$depth = 0,
        [int]$maxDepth = 5
    )
    if (-not $ae) { return }
    if ($depth -gt $maxDepth) { return }

    # If it's a distribution list in GAL (AddressEntry with Members), expand members
    $expanded = $false
    try {
        if ($ae.Members -and $ae.Members.Count -gt 0) {
            # Avoid cycles by DL ID/Name
            $dlKey = if ($ae.ID) { $ae.ID } else { $ae.Name }
            if ($dlKey -and -not $seenDLIds.Contains($dlKey)) {
                [void]$seenDLIds.Add($dlKey)
                foreach ($m in $ae.Members) {
                    Expand-AddressEntry -ae $m -depth ($depth + 1) -maxDepth $maxDepth
                }
                $expanded = $true
            }
        }
    } catch {}

    if (-not $expanded) {
        $email = Get-SmtpFromAddressEntry $ae
        $name  = $ae.Name
        Add-EmailRow -Name $name -Email $email
    }
}

# Expand members of a personal Contact Group (DistListItem)
function Expand-DistListItem {
    param($dli)
    if (-not $dli) { return }
    try {
        $count = $dli.MemberCount
        if ($count -gt 0) {
            for ($i = 1; $i -le $count; $i++) {
                try {
                    $rcp = $dli.GetMember($i)  # Recipient
                    if ($rcp -and $rcp.AddressEntry) {
                        Expand-AddressEntry -ae $rcp.AddressEntry -depth 0 -maxDepth 5
                    } else {
                        # Fallback: try direct SMTP from Recipient.Address
                        $addr = $rcp.Address
                        if ($addr -and $addr -match '^[^@\s]+@[^@\s]+\.[^@\s]+$') {
                            Add-EmailRow -Name $rcp.Name -Email $addr
                        }
                    }
                } catch {}
            }
        }
    } catch {}
}

# -------------------------------------
# Locate Contacts folder (by Account)
# -------------------------------------
$contactsFolder = $null

# Approach 0: Try match Store by Account name (fast if provided)
if ($Account) {
    Write-Host "[INFO] Trying to locate Contacts in store that matches: $Account"
    try {
        foreach ($store in $ns.Stores) {
            if ($store.DisplayName -like "*$Account*") {
                try {
                    # Store.GetDefaultFolder works on Outlook 2010+
                    $contactsFolder = $store.GetDefaultFolder(10) # olFolderContacts = 10
                    if ($contactsFolder) {
                        Write-Host "[OK] Using Contacts from store: $($store.DisplayName)"
                        break
                    }
                } catch {}
            }
        }
    } catch {
        Write-Host "[WARN] Store scan by Account failed: $_"
    }
}

# Approach 1: Default Contacts (fastest)
if (-not $contactsFolder) {
    Write-Host "[INFO] Attempting to access default Contacts folder..."
    try {
        $contactsFolder = $outlook.GetNamespace("MAPI").GetDefaultFolder(10)
        if ($contactsFolder) { Write-Host "[OK] Got default Contacts folder" }
    } catch {
        Write-Host "[WARN] GetNamespace.GetDefaultFolder failed: $_"
    }
}

# Approach 2: Session.GetDefaultFolder
if (-not $contactsFolder) {
    Write-Host "[INFO] Trying Session.GetDefaultFolder method..."
    try {
        $contactsFolder = $ns.GetDefaultFolder(10)
        if ($contactsFolder) { Write-Host "[OK] Got Contacts folder via Session" }
    } catch {
        Write-Host "[WARN] Session.GetDefaultFolder failed: $_"
    }
}

# Approach 3: Iterate stores (fallback)
if (-not $contactsFolder) {
    Write-Host "[INFO] Searching through stores for Contacts folder..."
    try {
        foreach ($store in $ns.Stores) {
            try {
                $root = $store.GetRootFolder()
                # Direct "Contacts"
                try {
                    $contactsFolder = $root.Folders.Item("Contacts")
                } catch {}
                # "Kontak" (localized)
                if (-not $contactsFolder) {
                    try { $contactsFolder = $root.Folders.Item("Kontak") } catch {}
                }
                if ($contactsFolder) {
                    Write-Host "[OK] Found Contacts in store: $($store.DisplayName)"
                    break
                }
            } catch {}
        }
    } catch {
        Write-Host "[WARN] Error iterating stores: $_"
    }
}

if (-not $contactsFolder) {
    Write-Host "[ERROR] Could not find or access Contacts folder after all attempts!" -ForegroundColor Red
    Write-Host "[ERROR] Make sure:" -ForegroundColor Red
    Write-Host "  1. Outlook is properly configured with an active account" -ForegroundColor Red
    Write-Host "  2. The account has a Contacts folder" -ForegroundColor Red
    Write-Host "  3. You have permission to access the Contacts" -ForegroundColor Red
    exit 1
}

# ------------------------------------------------
# FAST traversal: use Restrict + recurse subfolders
# ------------------------------------------------
function Process-ContactsFolder {
    param($folder)

    if (-not $folder) { return }
    try {
        $items = $folder.Items

        # Contacts only
        $contactItems = $items.Restrict("[MessageClass] = 'IPM.Contact'")
        if ($contactItems -and $contactItems.Count -gt 0) {
            Write-Host "[INFO] Contacts in '$($folder.Name)': $($contactItems.Count)"
            foreach ($c in $contactItems) {
                try {
                    # Name (prefer FullName -> FileAs -> Subject -> DisplayName)
                    $name = $null
                    foreach ($p in 'FullName','FileAs','Subject','DisplayName') {
                        if ($c.PSObject.Properties[$p]) {
                            $name = $c.$p
                            if (-not [string]::IsNullOrWhiteSpace($name)) { break }
                        }
                    }
                    # Email (prioritized)
                    $email = $null
                    foreach ($p in 'Email1Address','Email2Address','Email3Address','PrimarySmtpAddress','SMTPAddress','EmailAddress') {
                        if ($c.PSObject.Properties[$p]) {
                            $v = $c.$p
                            if (-not [string]::IsNullOrWhiteSpace($v)) { $email = $v; break }
                        }
                    }
                    Add-EmailRow -Name $name -Email $email
                } catch {}
            }
        }

        # Personal Contact Group (DistList)
        $dlItems = $items.Restrict("[MessageClass] = 'IPM.DistList'")
        if ($dlItems -and $dlItems.Count -gt 0) {
            Write-Host "[INFO] Contact Groups in '$($folder.Name)': $($dlItems.Count)"
            foreach ($dli in $dlItems) {
                try { Expand-DistListItem $dli } catch {}
            }
        }
    } catch {}

    # Recurse subfolders
    try {
        foreach ($sub in $folder.Folders) {
            Process-ContactsFolder $sub
        }
    } catch {}
}

Write-Host "[INFO] Reading contacts (including Contact Groups) from folder tree..."
Process-ContactsFolder $contactsFolder

Write-Host "[OK] Successfully collected $($rows.Count) unique contacts"

# ----------------------------
# Save to CSV
# ----------------------------
try {
    $dir = Split-Path $OutputCsv -Parent
    if (-not (Test-Path $dir)) { 
        New-Item -Path $dir -ItemType Directory -Force | Out-Null
        Write-Host "[OK] Created directory: $dir"
    }

    if ($rows.Count -eq 0) {
        Write-Host "[WARN] No contacts to export (0 items with email addresses)" -ForegroundColor Yellow
        "Name,Email" | Out-File -FilePath $OutputCsv -Encoding UTF8
        Write-Host "[OK] Created empty CSV file"
    } else {
        # Already unique via HashSet; just export
        $rows | Export-Csv -Path $OutputCsv -Encoding UTF8 -NoTypeInformation
        $csvSize = (Get-Item $OutputCsv).Length
        Write-Host "[OK] Exported $($rows.Count) contacts to $OutputCsv ($csvSize bytes)"
    }
} catch {
    Write-Host "[ERROR] Error saving CSV: $_" -ForegroundColor Red
    exit 1
}
``