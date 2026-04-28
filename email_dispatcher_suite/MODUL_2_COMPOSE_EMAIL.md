# 📧 MODUL 2: MEMBUAT & MENGIRIM EMAIL
**Email Dispatcher Suite - Operational Manual**

---

## 📋 DAFTAR ISI MODUL
1. [Overview Proses Pengiriman](#1-overview-proses-pengiriman)
2. [Akses Compose Email](#2-akses-compose-email)
3. [Compose Email - Detail](#3-compose-email---detail)
4. [Select Recipients](#4-select-recipients)
5. [Upload Lampiran](#5-upload-lampiran)
6. [Preview & Similarity Matching](#6-preview--similarity-matching)
7. [Send Email](#7-send-email)
8. [Best Practices](#8-best-practices)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. OVERVIEW PROSES PENGIRIMAN

### 1.1 Email Sending Workflow
```
┌─────────────────────────────────────────────┐
│                                             │
│  1. COMPOSE EMAIL                           │
│     Input: Subject, Body, Recipients        │
│     └─→ [Form validation]                   │
│                                             │
│  2. SELECT RECIPIENTS                       │
│     • By Group (auto-populate)              │
│     • By Group Order (multi-group)          │
│     • Manual selection                      │
│                                             │
│  3. UPLOAD ATTACHMENTS (Optional)           │
│     Files → Similarity matching preparation │
│                                             │
│  4. PREVIEW                                 │
│     Review recipients & attachment match    │
│     Adjust similarity threshold             │
│                                             │
│  5. SEND                                    │
│     System create job in database           │
│     PowerShell script execute               │
│     Outlook send emails                     │
│                                             │
│  6. MONITOR                                 │
│     View logs & tracking                    │
│     Check delivery status per recipient     │
│     Download report                         │
│                                             │
└─────────────────────────────────────────────┘
```

### 1.2 Key Features
- ✅ **Rich text editor** untuk email body dengan formatting
- ✅ **Recipient grouping** by grup atau group order
- ✅ **Similarity-based matching** untuk smart attachment distribution
- ✅ **Real-time preview** sebelum kirim
- ✅ **Batch email sending** dipadu dengan individual tracking
- ✅ **Detailed logging** untuk setiap pengiriman

---

## 2. AKSES COMPOSE EMAIL

### 2.1 Via Menu
- **Sidebar** → Click **"Kirim Email (Similarity)"**

### 2.2 Via Direct URL
- `http://localhost/email_dispatcher_suite/public/compose.php`

### 2.3 Session Requirement
- ✅ Must be logged in
- ✅ Minimum role: User
- ✅ Permission: Send emails

---

## 3. COMPOSE EMAIL - DETAIL

### 3.1 Compose Form Layout
```
┌─ FORM COMPOSE EMAIL ─────────────────────────────┐
│                                                   │
│  1. SUBJEK EMAIL *                               │
│     [________________________________]           │
│                                                   │
│  2. EMAIL CC (Optional)                          │
│     [________________________________]           │
│     Hint: Pisahkan dengan ; untuk multiple      │
│                                                   │
│  3. EMAIL BODY (Rich Text Editor)                │
│     [B] [I] [U] [Link] [Table] [Image]          │
│     ┌────────────────────────────────────────┐   │
│     │                                         │   │
│     │ Ketik atau paste konten email...       │   │
│     │                                         │   │
│     └────────────────────────────────────────┘   │
│                                                   │
└─────────────────────────────────────────────────┘
```

### 3.2 Step 1: Input Subjek Email

#### Field Subjek
- **Type**: Text input (max 200 char)
- **Required**: ✅ Yes
- **Validation**: Cannot be empty

#### Input Examples
| Tipe Kampanye | Contoh Subjek |
|---------------|---------------|
| Penawaran | "Penawaran Produk Terbaru - Q1 2024" |
| Promosi | "Diskon 20% untuk Member Setia Kami" |
| Update | "Update Status Proyek - Minggu 3" |
| Undangan | "Undangan Workshop Penjualan 15 Feb 2024" |

#### Tips
- ✅ Gunakan subject yang descriptive & menarik
- ✅ Maksimal 60 karakter untuk preview di email client
- ✅ Mulai dengan action word (Penawaran, Update, Undangan, dll)
- ❌ Jangan gunakan ALL CAPS (spam indicator)
- ❌ Jangan gunakan special char yang weird

### 3.3 Step 2: Input Email CC (Optional)

#### Field CC
- **Type**: Email address input
- **Required**: ❌ No (optional)
- **Format**: Multiple emails separated by semicolon (;)

#### Input Examples
```
Single CC:
director@company.com

Multiple CC:
director@company.com;manager@company.com;admin@company.com
```

#### Validasi
- ✅ Format: name@domain.ext
- ✅ Multiple: Split dengan semicolon
- ❌ Invalid: email tanpa @ atau domain
- ❌ Invalid: Multiple comma (use semicolon)

#### Use Cases
- **Director review**: CC director sebelum final send
- **Multiple stakeholder**: CC semua team lead
- **Compliance**: CC audit/compliance team

### 3.4 Step 3: Compose Email Body

#### Rich Text Editor (Quill)
Editor dengan toolbar:
```
[B] [I] [U] [⊕ Link] [🖼️ Image] [▦ Table] [▾ Format]
       Bold | Italic | Underline | Insert Link/Image/Table
```

#### Formatting Options

**Text Formatting:**
- **[B]** Bold → Make text **bold**
- **[I]** Italic → Make text *italic*
- **[U]** Underline → Make text <u>underlined</u>

**Paragraph Formatting:**
- **Header**: Convert to H1, H2, H3
- **List**: Bullet points atau numbered list
- **Blockquote**: Quoted text with special styling
- **Code**: Monospace font for code sample

**Content Insertion:**
- **[Link]**: Insert hyperlink
  ```
  Select text → [Link] → Input URL → Apply
  Result: Clickable link
  ```
- **[Image]**: Insert gambar
  ```
  [Image] → Input image URL → Apply
  Result: Image displayed in email
  ```
- **[Table]**: Insert tabel
  ```
  [Table] → Set rows/cols → Apply → Edit content
  ```

#### Content Guidelines
- ✅ Use HTML formatting untuk professional look
- ✅ Include images/logos untuk branding
- ✅ Use tables untuk present data clearly
- ✅ Keep paragraphs short & scannable
- ❌ Jangan use too many colors/fonts
- ❌ Jangan paste gambar ke text directly (use [Image] button)

#### Paste HTML from Word
1. Copy content dari Word/HTML editor
2. Paste ke email body editor
3. System automatically convert HTML:
   ```
   <p>Paragraph</p>
   <strong>Bold text</strong>
   <table>...</table>
   ```
4. Formatting preserved

#### Character Limit
- Max: 50,000 karakter (~10,000 words)
- Recommended: < 2,000 words untuk email

---

## 4. SELECT RECIPIENTS

### 4.1 Three Selection Modes

#### Mode A: By Group
**Untuk send ke 1 group kontak saja**

1. Klik dropdown **"Filter Grup"**
   ```
   [Filter Grup (0) ▾]  ← Click here
   ```
2. Panel terbuka showing all groups:
   ```
   ┌─ SELECT GROUP ────────────────┐
   │ [Search groups...          ]  │
   │                              │
   │ [✓ Pilih Semua] [✗ Bersihkan]│
   │                              │
   │ [checkbox] Vendor ASAHIMAS   │
   │ [checkbox] Customer B2B      │
   │ [checkbox] Partner Strategic │
   │ [checkbox] Group Internal    │
   │ ...                          │
   │                              │
   │ [Apply]                      │
   └───────────────────────────────┘
   ```
3. Centang grup(s):
   - Single group: Centang 1 group
   - Multiple groups: Centang 2+ groups
4. Klik **"Apply"**
5. Recipients otomatis populate:
   ```
   [Filter Grup (2) ▾]  ← Shows selected count
   Selected: 26 recipients
   ```

#### Mode B: By Group Order
**Untuk send ke multiple groups dalam 1 campaign**

1. Dalam panel "Filter Grup", scroll ke bawah
2. Section **"GROUP ORDER"** muncul:
   ```
   ┌─ GROUP ORDER ──────────────┐
   │ [Search group orders...]   │
   │                            │
   │ [checkbox] Q1 Sales 2024   │
   │ [checkbox] Partner Program │
   │ [checkbox] Customer Loyalty│
   │ ...                        │
   └────────────────────────────┘
   ```
3. Centang 1 group order
4. Klik **"Apply"**
5. All groups dalam order otomatis selected:
   ```
   Selected: 3 groups
   - Vendor ASAHIMAS
   - Partner Strategic
   - Customer B2B
   
   Total Recipients: 65
   ```

#### Mode C: Manual Selection
**Untuk send ke selected recipients tanpa group structure**

1. **Leave "Filter Grup" unchecked**
   - Jangan pilih apapun di group panel
2. **Manual search & select**:
   ```
   [🔍 Cari Kontak...]  ← Search box
   
   Results:
   [checkbox] John Doe (john@mail.com)
   [checkbox] Jane Smith (jane@mail.com)
   [checkbox] PT ASAHIMAS (supplier@asahimas.co.id)
   ```
3. Use search untuk filter:
   - Type "john" → Show "John Doe"
   - Type "asahi" → Show "PT ASAHIMAS"
4. Centang desired recipients
5. Counter shows:
   ```
   Selected: 12 / Total: 1,234
   ```

### 4.2 Recipient Counter
```
Selected: 26 / Total: 1,234
│         ││      │
│         ││      └─ Total kontak di database
│         │└─ Total kontak yang match filter
│         └─ Kontak yang dipilih untuk kirim
```

### 4.3 Best Practices
- ✅ **Group mode** recommended untuk clean organization
- ✅ **Group order** untuk multi-group campaign
- ✅ Verify recipients before proceed to preview
- ✅ Min 1, Max 5000 recipients per job
- ❌ Don't mix manual + group selection (choose one)

---

## 5. UPLOAD LAMPIRAN

### 5.1 Akses Upload Section
Scroll down ke section **"📎 Lampiran & Template"**

```
┌─ LAMPIRAN & TEMPLATE ─────────────────────┐
│                                           │
│  [📤 Pilih File] [Clear All]             │
│                                           │
│  📎 Attached Files:                       │
│  ✅ sales_template.pdf (2.3 MB)          │
│     [Show] [Delete]                      │
│                                           │
│  ✅ price_list.xlsx (1.5 MB)             │
│     [Show] [Delete]                      │
│                                           │
│  Next: Upload akan digunakan untuk       │
│  similarity matching dengan recipients   │
│                                           │
└───────────────────────────────────────────┘
```

### 5.2 File Upload

#### File Types Supported
| Type | Extensions | Max Size | Use Case |
|------|-----------|----------|----------|
| Document | PDF, DOC, DOCX | 10 MB | Proposal, report |
| Spreadsheet | XLSX, XLS, CSV | 10 MB | Price list, data |
| Image | JPG, PNG, GIF | 5 MB | Logo, photo |
| Archive | ZIP | 10 MB | Multiple files |
| Text | TXT | 1 MB | Pure text |

#### Upload Process
1. Klik **"📤 Pilih File"**
2. File browser dialog:
   ```
   Navigate to file location
   Select 1 or more files (Ctrl+Click for multiple)
   Click "Open"
   ```
3. File upload:
   ```
   📤 Uploading...
   - sales_template.pdf [████████--] 80%
   - price_list.xlsx [████████████] 100%
   ```
4. Success:
   ```
   ✅ 2 files berhasil di-upload
   ```

### 5.3 File Management

#### Show/Preview File
- Klik tombol **"[Show]"** untuk preview
- Browser automatically open file

#### Delete Single File
- Klik tombol **"[Delete]"** pada file
- File langsung terhapus from attachment list

#### Clear All Files
- Klik tombol **"Clear All"**
- Konfirmasi popup:
   ```
   Clear all 2 attachments?
   [Cancel] [Yes, Clear All]
   ```
- All files terhapus

### 5.4 File Naming Best Practices
Untuk optimal similarity matching, gunakan naming convention:

**Format Recommended:**
```
[GroupName]_[content]_[version].ext

Contoh:
ASAHIMAS_price_list_q1.pdf
ASAHIMAS_proposal_2024.pdf
Vendor_Product_Brochure.xlsx
Partner_Contract_v2.docx
```

**Benefit:**
- ✅ System otomatis match file ke recipient group
- ✅ Recipient "ASAHIMAS" dapat file "ASAHIMAS_*"
- ✅ Similarity score otomatis boost

**Avoid:**
- ❌ Generic names: "file1.pdf", "document_final.pdf"
- ❌ Very long names: "This is a proposal for..."
- ❌ Special characters: "file@#$%.pdf"

---

## 6. PREVIEW & SIMILARITY MATCHING

### 6.1 Preview Purpose
Sebelum send, preview untuk:
- ✅ Verify recipients correct
- ✅ Check attachment matching
- ✅ Adjust similarity threshold if needed
- ✅ Last chance to edit

### 6.2 Go to Preview
Klik tombol **"3) Preview & Cocokkan (Similarity)"**

Redirect ke halaman preview:
```
┌─ PREVIEW PENCOCOKAN ──────────────────────┐
│                                           │
│ Subjek: Penawaran Q1 2024                 │
│ Email CC: director@company.com            │
│                                           │
│ Threshold Similarity: [60 ▭▭▭▭■] 80     │
│                      Change ↑             │
│                                           │
│ ┌─ RENCANA PENGIRIMAN ─────────────────┐ │
│ │ Penerima│Grup │Email│Attachment│Skor│ │
│ ├─────────┼─────┼─────┼────────┼─────┤ │
│ │John Doe │Vend │john@│✅.pdf  │95  │ │
│ │PT ASAH..│Vend │supp@│✅.pdf  │98  │ │
│ │Jane Smth│Cust │jane@│⚠️ Low │55  │ │
│ │...     │     │     │        │    │ │
│ └──────────────────────────────────────┘ │
│                                           │
│ Summary: 26 recipients, 24 will send    │
│          2 below threshold (no attach)  │
│                                           │
│ [⟵ Back to Compose] [→ Send]            │
└───────────────────────────────────────────┘
```

### 6.3 Understanding the Preview Table

| Kolom | Isi | Meaning |
|-------|-----|---------|
| **Penerima** | Name | Recipient name |
| **Grup** | Group name | Group membership |
| **Email** | Email address | Target email |
| **Attachment** | File + status | Matched file |
| **Skor** | 0-100 | Similarity score |

### 6.4 Attachment Status Codes
| Icon | Status | Meaning | Action |
|------|--------|---------|--------|
| ✅ | Success | Match ≥ threshold | Will send with attachment |
| ⚠️ | Low match | Match < threshold | Will send without attachment |
| ❌ | No match | No matching file | Will send without attachment |
| 🔄 | Processing | System calculating | Wait... |

### 6.5 Similarity Threshold

#### What is Threshold?
- **Minimum score** untuk kirim attachment
- **Range**: 0-100
- **Default**: 60 (recommended)

#### How Threshold Works
```
Score 95 with threshold 60:
  95 ≥ 60 → ✅ Send file

Score 45 with threshold 60:
  45 < 60 → ⚠️ Don't send file (below threshold)
```

#### Adjust Threshold
1. Drag slider **"Threshold Similarity"**
2. Left (lower): More permissive, send more files
3. Right (higher): More strict, send fewer files
4. Table updates real-time:
   ```
   Threshold 30: 25 recipients akan terima file
   Threshold 60: 24 recipients akan terima file
   Threshold 80: 20 recipients akan terima file
   ```

#### Threshold Strategy
| Threshold | Behavior | Use When |
|-----------|----------|----------|
| 10-30 | Very permissive | Want all recipients get attachment |
| 40-60 | Balanced (recommended) | Normal use case |
| 70-90 | Strict | Only perfect matches get file |
| 95+ | Very strict | Only exact email/name match |

### 6.6 Similarity Scoring Logic
System calculates match berdasarkan:

1. **Email match**: Compare filename dengan email domain
   - "ASAHIMAS_file.pdf" vs "supplier@**asahimas**.co.id" → ~70%

2. **Name match**: Compare filename dengan recipient name
   - "ASAHIMAS_file.pdf" vs "PT **ASAHIMAS**" → ~90%

3. **Group match**: Compare filename dengan group name
   - "Vendor_file.pdf" vs Group "**Vendor ASAHIMAS**" → ~85%

4. **Final score**: Take highest match (usually group match)

---

## 7. SEND EMAIL

### 7.1 Execute Send
Klik tombol **"→ Send Email"** di preview page

### 7.2 Sending Process
```
System flow:
1. Validate all data
2. Create mail_job record in database
3. Create mail_job_items (per recipient)
4. Generate job_X.json file
5. Execute PowerShell script
6. Connect to Outlook
7. Send emails (grouped by recipient list)
8. Generate result JSON
9. Update database with status
10. Display final report
```

### 7.3 Sending Progress Page
```
┌─ PENGIRIMAN DIPROSES ──────────────────┐
│                                        │
│ Job ID: 12345                         │
│ Status: Processing...                 │
│                                        │
│ PowerShell Output:                    │
│ [OK] Processing 26 items from job...  │
│ [OK] [1/26] Processing item 1         │
│ [OK] Email dikirim. To: john@mail.com│
│ [OK] [2/26] Processing item 2         │
│ [OK] Email dikirim. To: jane@mail.com│
│ [OK] [3/26] Processing item 3         │
│ [OK] Email dikirim. To: supplier@    │
│ ...                                    │
│ [OK] [26/26] Processing item 26       │
│ [OK] Email dikirim. To: ...           │
│ [OK] Results written to: result...json│
│                                        │
│ ✅ PENGIRIMAN SELESAI                 │
│ Total: 26 emails sent                 │
│                                        │
│ [📊 Lihat Rekap & Log]                │
└────────────────────────────────────────┘
```

### 7.4 What to Check
- ✅ Job ID created (database record)
- ✅ PowerShell script output showing "Email dikirim"
- ✅ Final status "PENGIRIMAN SELESAI"
- ✅ Email count matches recipient count

### 7.5 Troubleshooting Send Issues

| Issue | Cause | Fix |
|-------|-------|-----|
| "Outlook not connected" | Outlook application not running | Open Outlook desktop app |
| "Account not found" | Sender account not in Outlook | Configure in Settings |
| "Invalid recipient email" | Bad email format | Fix in contacts & retry |
| "Attachment not found" | File deleted after upload | Re-upload attachment |
| "Script timeout" | Too many recipients/large files | Send in batches |

---

## 8. BEST PRACTICES

### 8.1 Email Composition
✅ **DO:**
- Subject: Clear, descriptive, < 60 chars
- Body: Well-formatted, scannable
- CC: Only necessary stakeholders
- Attachment: Relevant to recipient
- Test: Preview before large batch send

❌ **DON'T:**
- Subject: "Check this" (too vague)
- Body: Wall of text (no formatting)
- CC: Entire distribution list (spam look)
- Attachment: Generic file to all (inefficient)
- Urgent: Don't mark all as urgent

### 8.2 Recipient Selection
✅ **DO:**
- Use groups untuk organized sending
- Verify recipients before send
- Segment by role/interest
- Min 1, Max 5000 per job

❌ **DON'T:**
- Manual select 1000+ recipients
- Mix group + manual selection
- Send to unverified email list
- Send test email to production list

### 8.3 Attachment Management
✅ **DO:**
- Use clear file naming
- Follow naming convention: [GroupName]_[content]
- Test similarity matching first
- Keep file size < 5MB

❌ **DON'T:**
- Send same file to everyone (inefficient)
- Use generic names: "file1.pdf"
- Large file (>10MB)
- Corrupted or password-protected files

### 8.4 Similarity Matching
✅ **DO:**
- Understand threshold mechanism
- Adjust threshold per campaign
- Name files per recipient group
- Monitor preview before send

❌ **DON'T:**
- Don't assume threshold default is perfect
- Don't send with very low threshold (<20)
- Don't use misleading filenames

---

## 9. TROUBLESHOOTING

### Issue 9.1: Recipients Not Showing
**Problem:** No recipients appear in compose form

**Checklist:**
1. ✅ Group ada? (Go to Contacts → check group exists)
2. ✅ Group punya members? (Edit group → verify min 1)
3. ✅ Select group dipilih? (Click Filter Group checkbox)
4. ✅ Refresh page?

**Solution:**
- Create/add members to group
- verify group selection active
- Refresh browser (Ctrl+F5)

### Issue 9.2: Similarity Score Low for All
**Problem:** All recipients below threshold score

**Cause:**
- Filename tidak mirip dengan recipient/group name
- Example: File "general_promo.pdf" vs Group "Vendor ASAHIMAS"

**Solution:**
1. **Option A: Rename file**
   - "ASAHIMAS_promo.pdf" → better match
   - Upload file baru (delete old)

2. **Option B: Lower threshold**
   - Drag slider ke 30-40
   - More recipients akan dapat file

3. **Option C: Use Group Order**
   - Organize contacts per group
   - Name file per group name

### Issue 9.3: Attachment Not Showing in Preview
**Problem:** File upload tapi tidak muncul di preview

**Cause:**
- File upload failed silently
- Session timeout
- Browser cache issue

**Solution:**
1. Delete old attachment
2. Re-upload file
3. Verify file appears in "Lampiran" list
4. Refresh preview page

### Issue 9.4: Email Subject Gets Cut Off
**Problem:** Long subject tidak completely visible

**Solution:**
- Keep subject < 60 characters
- Most email client preview cut at 60 chars
- Test subject di actual email client

### Issue 9.5: HTML Formatting Lost After Upload
**Problem:** Paste HTML dari Word, tapi formatting hilang

**Cause:**
- Unsupported HTML tags
- Special Word formatting

**Solution:**
1. Paste as plain text (Ctrl+Shift+V)
2. Re-apply manual formatting
3. Or upload as PDF attachment
4. Use HTML editor untuk direct HTML input

---

**END OF MODULE 2**

*Lanjut ke MODULE 3 untuk Grup & Group Order management*
