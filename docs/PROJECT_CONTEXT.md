# PROJECT CONTEXT — Sistem Pemesanan Kendaraan

Dokumen ini berisi konteks proyek, spesifikasi bisnis, matriks otorisasi, serta kebutuhan fungsional dashboard dan laporan untuk **Sistem Pemesanan Kendaraan Operasional Perusahaan Tambang Nikel**.

> [!NOTE]
> **Single Source of Truth References:**
> - **Skema Database & Relasi:** Lihat [docs/schema.md](file:///d:/project/vehicle-booking/docs/schema.md)
> - **Spesifikasi REST API & Contract:** Lihat [docs/api-contract.md](file:///d:/project/vehicle-booking/docs/api-contract.md)
> - **Aturan Kerja & Konvensi Agent:** Lihat [AGENTS.md](file:///d:/project/vehicle-booking/AGENTS.md)

---

## 1. Overview & Latar Belakang

Aplikasi ini dikembangkan sebagai solusi sistem pemesanan kendaraan operasional multi-region (tambang/kantor cabang/kantor pusat) dengan dukungan berbagai jenis kendaraan (penumpang & kargo), kepemilikan (milik sendiri & sewa), serta alur persetujuan (approval) berjenjang 2 level.

---

## 2. Definisi Role & Matriks Otorisasi

### Peran Pengguna (Roles)
- **`admin`**: Bertanggung jawab mengelola master data (user, kendaraan, driver), membuat pemesanan baru, serta memantau laporan & log aktivitas.
- **`approver`**: Bertanggung jawab melakukan peninjauan (setuju / tolak) terhadap pemesanan yang ditugaskan sesuai tingkatannya (`approval_level`).

| User Field | Role | Level | Deskripsi |
| :--- | :---: | :---: | :--- |
| `role = 'admin'` | `admin` | `null` | Hak akses penuh pengelolaan master & pembuatan booking |
| `role = 'approver'` | `approver` | `1` | Approver tingkat pertama (Level 1) |
| `role = 'approver'` | `approver` | `2` | Approver tingkat kedua (Level 2) |

---

### Matriks Otorisasi Fitur

| Fitur / Aksi | Admin | Approver | Keterangan Otorisasi |
| :--- | :---: | :---: | :--- |
| **Login / Logout** | ✅ | ✅ | Autentikasi berbasis JWT |
| **CRUD Master Data (Users, Vehicles, Drivers)** | ✅ | ❌ | Hanya Admin yang berhak mengelola |
| **Buat Pemesanan Baru (Create Booking)** | ✅ | ❌ | Admin membuat booking & memilih 2 Approver |
| **Lihat Daftar & Detail Pemesanan** | ✅ | ✅ | Admin: semua booking; Approver: booking terkait |
| **Approve / Reject Pemesanan** | ❌ | ✅ | Hanya Approver yang di-assign & sesuai giliran level |
| **Lihat Dashboard** | ✅ | ✅ | Admin: Grafik & Top 5; Approver: Pending list |
| **Lihat Laporan & Export Excel** | ✅ | ❌ | Admin download `.xlsx` laporan |
| **Lihat Log Aktivitas System** | ✅ | ❌ | Audit trail aktivitas pengguna |

---

## 3. Spesifikasi Bisnis Utama (Business Rules)

1. **Alur Persetujuan 2-Level Sekuensial:**
   - Status pemesanan diawali dari `waiting_level_1`.
   - Approver Level 1 menyetujui $\rightarrow$ Status berubah menjadi `waiting_level_2`.
   - Approver Level 2 menyetujui $\rightarrow$ Status berubah menjadi `approved` (final).
   - Penolakan oleh Approver Level 1 atau Level 2 $\rightarrow$ Status langsung berubah menjadi `rejected` (final).
2. **Pengecekan Overlap & Ketersediaan:**
   - Pemesanan hanya bisa dibuat jika Kendaraan berstatus `available` dan Driver berstatus `active`.
   - Tanggal mulai (`start_date`) harus sebelum tanggal selesai (`end_date`).
   - Kendaraan dan Driver dipastikan bebas bentrok waktu untuk rentang `(start_date < newEndDate) AND (end_date > newStartDate)`.
3. **Pencegahan Self-Approval:**
   - Admin pembuat pemesanan tidak dapat memilih dirinya sendiri sebagai Approver Level 1 maupun Level 2.
4. **Immutability Pemesanan:**
   - Pemesanan yang telah disubmit bersifat immutable (read-only), tidak dapat diubah kembali oleh Admin.

---

## 4. Spesifikasi Dashboard & Laporan

### Admin Dashboard
- **Summary Cards (4):** Total Kendaraan Aktif, Total Booking Bulan Ini, Pending Approval (`waiting_level_1` + `waiting_level_2`), Approved Bulan Ini.
- **Bar Chart:** Grafik tren jumlah booking per bulan (12 bulan terakhir).
- **Donut Chart:** Distribusi status booking (`waiting`, `approved`, `rejected`).
- **Progress Bar Chart:** Top 5 kendaraan terbanyak digunakan (berdasarkan booking `approved`).

### Approver Dashboard
- **Summary Card (1):** Jumlah pemesanan menunggu persetujuan saya (`pending_for_me`).
- **Interactive List:** Daftar pemesanan pending yang membutuhkan persetujuan/penolakan dengan modal aksi.

### Export Laporan Excel (`.xlsx`)
- **Filter:** Rentang tanggal mulai (`start_date` - `end_date`) dan status opsional.
- **Output:** Stream `.xlsx` berisi rincian booking, kendaraan, driver, status L1 & L2, serta pembuat.

---

## 5. Kredensial Pengujian (Seeder Data)

| Role | Email | Password Default |
| :--- | :--- | :--- |
| **Admin** | `admin@vehicle.com` | `password` |
| **Approver Level 1** | `approver1@vehicle.com` | `password` |
| **Approver Level 2** | `approver2@vehicle.com` | `password` |
