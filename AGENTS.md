# AGENTS.md — Sistem Pemesanan Kendaraan

Instruksi kerja aktif untuk AI coding agent (Antigravity). Dibaca otomatis sebelum merencanakan atau melakukan perubahan apapun di project ini.

---

## 1. Dokumen Acuan (Source of Truth — WAJIB Dibaca Sebelum Implementasi)

- [docs/schema.md](file:///d:/project/vehicle-booking/docs/schema.md) — Skema database final, relasi, dan constraint
- [docs/api-contract.md](file:///d:/project/vehicle-booking/docs/api-contract.md) — Kontrak REST API lengkap (endpoint, auth, role, payload, response envelope)
- [docs/PROJECT_CONTEXT.md](file:///d:/project/vehicle-booking/docs/PROJECT_CONTEXT.md) — Konteks proyek, spesifikasi bisnis, dan matriks hak akses
- [docs/done.md](file:///d:/project/vehicle-booking/docs/done.md) — Histori pengerjaan & status fitur selesai

> [!IMPORTANT]
> Dokumen di atas **TIDAK BOLEH** diubah oleh agent tanpa persetujuan eksplisit dari user. Jika implementasi memerlukan hal yang belum tercantum, **STOP** dan minta konfirmasi user.

---

## 2. Tech Stack & Struktur Direktori

| Layer                   | Teknologi                                                     | Lokasi / Folder Utama                              |
| :---------------------- | :------------------------------------------------------------ | :------------------------------------------------- |
| **Backend**             | PHP 8.2+ / CodeIgniter 4 (`^4.7`)                             | `backend/`                                         |
| **Backend Controllers** | CodeIgniter 4 REST API Controllers                            | `backend/app/Controllers/Api/`                     |
| **Backend Models**      | CodeIgniter 4 Models                                          | `backend/app/Models/`                              |
| **Backend Services**    | Business Logic Services                                       | `backend/app/Services/`                            |
| **Backend Filters**     | Auth & CORS Filters                                           | `backend/app/Filters/`                             |
| **Excel Export**        | PhpSpreadsheet (`^5.6`)                                       | `backend/app/Controllers/Api/ReportController.php` |
| **Frontend**            | React (`^19.2.4`) + Vite (`^8.0.4`) + Tailwind CSS (`^4.2.2`) | `frontend/`                                        |
| **Frontend Router**     | React Router DOM (`^7.14.1`)                                  | `frontend/src/App.jsx`                             |
| **Frontend HTTP**       | Axios (`^1.15.0`)                                             | `frontend/src/services/api.js`                     |
| **Frontend Components** | Radix UI primitives + shadcn + Common Wrappers                | `frontend/src/components/`                         |

---

## 3. Prinsip Kerja — Aturan Utama Agent

1. **Selalu Rencana Dulu, Baru Eksekusi:** Sebelum menulis kode untuk task apapun, susun rencana implementasi berupa langkah-langkah kecil. Tunggu persetujuan eksplisit dari user sebelum mulai coding.
2. **Satu Langkah Kecil per Iterasi:**
   - **Backend:** 1 endpoint / 1 service function pendukungnya.
   - **Frontend:** 1 komponen / 1 tampilan halaman.
   - **Database:** 1 file migration per perubahan skema.
3. **Berhenti Setelah 1 Langkah Selesai:** Laporkan file yang berubah, hasil verifikasi/test, dan cara mengetesnya. Tunggu persetujuan user sebelum lanjut ke langkah berikutnya.
4. **Jangan Mengasumsikan Requirement:** Jika ada ambigu atau hal yang tidak tertulis di `docs/`, tanyakan ke user. Jangan menebak sendiri.
5. **Urutan Dependency Logis:** Bangun komponen dasar (backend service/endpoint) sebelum membangun UI frontend yang mengonsumsinya.

---

## 4. Kebebasan Implementasi & Catatan `done.md`

- **Scope & Requirement:** Dilarang menambah atau mengubah scope tanpa persetujuan user.
- **Detail Teknis:** Improvisasi detail teknis (struktur internal, optimasi query, helper) diperbolehkan selama memberikan benefit konkret.
- **Pencatatan Penyimpangan:** Setiap penyimpangan atau keputusan teknis penting wajib dicatat pada entry `docs/done.md` saat langkah diselesaikan.

---

## 5. Kebijakan Test & Retry (Backend CI4 Test)

- **Tes Otomatis Backend WAJIB:** Setiap endpoint/service backend yang diselesaikan dalam 1 langkah wajib memiliki tes otomatis Pest.
- **Setup & Framework:** Menggunakan Pest (`composer require pestphp/pest --dev`), dengan base `TestCase` yang meng-extend `CodeIgniter\Test\CIUnitTestCase`. Setup dilakukan 1x di awal.
- **Assertion Bisnis Nyata:** Tes wajib meng-assert aturan bisnis nyata (misal: penolakan bentrok jadwal overlap, penolakan self-approval, penolakan persetujuan di luar giliran), bukan sekadar tes tes kosmetik/boilerplate.
- **Batas Retry (Maksimal 2x):** Jika tes gagal, coba perbaiki maksimal 2 kali. Jika masih gagal setelah 2x percobaan: **STOP**. Laporkan ke user (tes yang gagal, error log, dan dugaan penyebab). Jangan lanjut ke langkah berikutnya dan jangan update `done.md`.
- **Cakupan Tes (Test Scope):**
  - Default per langkah: Domain-scoped (contoh: `vendor/bin/pest tests/app/Services/BookingServiceTest.php`).
  - **Full Suite (`vendor/bin/pest`):** WAJIB dijalankan jika langkah tersebut mengubah file lintas-modul (`AuthContext`, `JwtAuthFilter`, base Model/Controller) atau sebelum suatu fitur ditutup secara formal di `done.md`.

---

## 6. Update `docs/done.md`

Setelah 1 langkah kecil selesai, tes lolos (jika ada), DAN user menyetujui hasil langkah tersebut — tambahkan catatan ringkas pekerjaan ke `docs/done.md`.

---

## 7. Konvensi Kode & Aturan Terverifikasi

### A. Format Response Envelope Backend

- **Success (200/201):** `{ "status": true, "message": "...", "data": <object|array|null> }`
- **Error Standar (400/422):** `{ "status": false, "message": "...", "errors": { "<field>": "..." } }`

> [!CAUTION]
> **Technical Debt Response Format (Dilarang Ditiru pada Kode Baru):**
>
> - `POST /api/auth/login` (401) mengembalikan `"data": null` alih-alih key `"errors"`.
> - `VehicleController` & `DriverController` (422) mengembalikan objek error di dalam key `"data"` alih-alih `"errors"`.
> - `GET /api/reports/export` mengembalikan raw binary stream `.xlsx`.

### B. Verified Business Rules (Wajib Dipatuhi)

1. **Format Booking Code:** `BK-YYYYMMDD-XXXX` (4 karakter alfanumerik acak + loop retry keunikan hingga 10x di `BookingService`).
2. **Ketersediaan Master Data:** Kendaraan wajib status `available`, driver wajib status `active`.
3. **Pengecekan Overlap Jadwal:** `(start_date < newEndDate) AND (end_date > newStartDate)` berlaku untuk booking berstatus `waiting_level_1`, `waiting_level_2`, dan `approved`.
4. **Alur Persetujuan 2-Level Sekuensial:** `waiting_level_1` $\rightarrow$ `waiting_level_2` $\rightarrow$ `approved`. Penolakan pada level manapun langsung mengubah status menjadi `rejected`.
5. **Request Payload Approver:** `approver_level1_id` & `approver_level2_id` adalah parameter request body pada `POST /api/bookings` (bukan kolom tabel `bookings`), yang digunakan untuk menginsert 2 record ke `booking_approvals`.
6. **Pencegahan Self-Approval:** ID approver Level 1 maupun Level 2 tidak boleh sama dengan ID admin pembuat.
7. **Immutability Pemesanan:** Booking tidak dapat diubah (edit/update) setelah disubmit.
8. **Audit Logging:** Seluruh aksi utama wajib dicatat ke `activity_logs` via `ActivityLogService`.

### C. Konvensi Kode Frontend (Terverifikasi di `frontend/src/`)

- **Tabel Reusable:** `verified: src/components/common/DataTable.jsx` (komponen tabel terisolasi dengan prop `columns`, `data`, `loading`, `emptyText`, skeleton loader, dan kustomisasi render via `col.render`).
- **Modal / Dialog:** `verified: src/components/common/FormDialog.jsx` (Dialog form via Radix Dialog) & `src/components/common/ConfirmDialog.jsx` (Dialog konfirmasi via Radix AlertDialog). **Dilarang mempergunakan `alert()` / `confirm()` native browser**.
- **Form Input Components:** `verified: src/components/common/InputField.jsx` (komponen input terabstraksi membungkus Radix Input, Label, dan pesan error validation) serta Radix Select.
- **Pemisahan `components/ui/` vs `components/common/`:** `verified: src/components/ui/` berisi komponen dasar Radix/shadcn (dilarang dimodifikasi langsung), sedangkan `src/components/common/` berisi layer wrapper aplikasi (`Button`, `ConfirmDialog`, `DataTable`, `FormDialog`, `InputField`, `StatusBadge`).
- **Formatting & Timezone Utilities:** `verified: src/utils/utils.js` (utilitas tanggal `formatDate`, `formatDateShort`, `formatCurrentMonth`, label status `getStatusLabel`, dan konversi WIB `Asia/Jakarta` diisolasi terpusat di file ini).

---

## 8. Larangan Utama Agent

1. **JANGAN** mengubah file `docs/schema.md`, `docs/api-contract.md`, atau `CONTOH-AGENTS.md`.
2. **JANGAN** menambah library/package baru tanpa persetujuan eksplisit dari user.
3. **JANGAN** membuat format response envelope baru di luar standar yang ada.
4. **JANGAN** mengedit file di `frontend/src/components/ui/` secara langsung (gunakan wrapper di `frontend/src/components/common/`).
5. **JANGAN** mempergunakan `alert()` atau `confirm()` native browser di frontend.
6. **JANGAN** membuat endpoint atau kolom database baru tanpa meng-update dokumen acuan terlebih dahulu.
