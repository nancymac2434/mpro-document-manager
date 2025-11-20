
# MentorPRO Document Manager Plugin

A custom WordPress plugin for managing secure document uploads and controlled sharing between **mentees**, **mentors**, and **program managers (PMs)** in the MentorPRO client portal.

---

## 🧩 Overview

This plugin adds a **Document Upload & Sharing System** inside the client portal. It allows users (mentees, mentors, and PMs) to upload documents and selectively share them with others in their client group. Uploaded documents appear in organized **tabs** depending on the user’s role and permissions.

### Key Features

- 🔒 Secure file upload with validation and nonce protection  
- 👥 Role-based sharing (mentees→mentors/PMs, mentors→mentees, PMs→mentees)  
- 🧠 Automatic sharing rule: mentee → mentor = also visible to all PMs  
- 🗂️ Tabbed document viewer with DataTables integration  
- 🪄 Drag & Drop upload interface with live file preview  
- 🕵️ Per-tab search with live filtering  
- 🎨 Responsive, accessible UI with Select2 multiselect dropdowns  

---

## ⚙️ Architecture

### Custom Post Type
**Name:** `uploaded_document`

Each document is stored as a WordPress post with metadata controlling access and visibility.

### Meta Fields

| Meta Key | Description |
|-----------|-------------|
| `document_url` | URL of uploaded file |
| `document_type` | File extension (pdf, docx, png, etc.) |
| `assigned_client` | Client group (e.g. `stepup`) |
| `document_roles` | Serialized array of role tokens (`['mentor', 'contract', 'mentee']`) defining “share with all” visibility |
| `document_user_mentee` | Array of mentee IDs for direct sharing |
| `document_user_mentor` | Array of mentor IDs for direct sharing |
| `document_user_contract` | Array of PM IDs for direct sharing |

---

## 🧩 Core Files and Functions

| Function | Description |
|-----------|-------------|
| `wpd_handle_document_upload()` | Handles upload validation, file saving, metadata creation, and auto-sharing rules. |
| `mpro_render_document_tabs()` | Renders the tabbed interface for Uploaded/Shared/Direct shares. |
| `mpro_build_query_args()` | Generates WP_Query filters based on role and visibility rules. |
| `display_document_list()` | Renders tables of accessible documents. |
| `build_user_checkboxes()` | Generates Select2 multiselect dropdowns for user targeting. |

---

## 🖱️ Frontend (JavaScript)

**File:** `assets/js/scripts.js`

| Functionality | Description |
|----------------|-------------|
| Select2 Init | Enhanced user selection dropdowns |
| Select All Buttons | Toggle all users with one click |
| Tab Navigation | Handles switching between document tabs |
| Drag & Drop Upload | Modern drag area with preview and validation |
| Per-Tab Search | Filters visible documents by keyword |
| DataTables Init | Adds sorting, search, and table enhancements |

---

## 🧠 Role Behavior Summary

| Uploader | Can Share With | Auto-shares | Tabs Seen |
|-----------|----------------|--------------|------------|
| Mentee | Mentors, PMs | All PMs (when shared with any mentor) | Uploaded by You, Shared with You |
| Mentor | Mentees | None | Uploaded by You, Shared with You |
| PM | Mentees | None | Uploaded by You, Shared with You, Shared directly with You |

---

## 🔒 Security

- Uses `wp_verify_nonce()` for form protection  
- File type/size validation with `wp_check_filetype()`  
- Only logged-in users can upload or access documents  
- Role-based and client-based visibility control  

---

## 🧰 Dependencies

| Library | Purpose |
|----------|----------|
| [Select2](https://select2.org/) | Enhanced user dropdowns |
| [DataTables](https://datatables.net/) | Sortable & searchable document tables |
| [SweetAlert2](https://sweetalert2.github.io/) | Upload alerts and errors |
| jQuery | Core DOM utilities (WordPress default) |

---

## 🧾 Developer Notes

Enable debugging by adding to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs will appear in `/wp-content/debug.log`.

To inspect document meta manually:

```sql
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id = <YOUR_POST_ID> 
ORDER BY meta_key;
```

---

## 🧑‍💼 Admin Instructions

1. Navigate to **Client Portal → Document Upload**
2. Use tabs to view:
   - Files you uploaded
   - Files shared with you
   - Files directly shared to you (PMs only)
3. Use search boxes to filter results
4. Use **Delete** to remove your own uploads

---

## 🧑‍🎨 Future Enhancements

- Bulk download/export options  
- Inline PDF previews  
- Document categorization and tags  
- Share expiry dates  
- Email notifications on new shares  

---

**Author:** Nancy McNamara  
**Version:** 1.0  
**License:** GPL2  
**URL:** https://mentorpro.com/

---
