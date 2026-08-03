# INTERVIEW CONTEXT

Situasi Interview
Format: Google Meet, presentasi + demo langsung aplikasi
Audiens: IT Head / Senior Developer (technical)
Durasi: 30 menit
Posisi: Fullstack Developer

Status Project (Semua Terverifikasi)
Backend: selesai dan berjalan
Frontend: selesai dan berjalan
README: selesai, memenuhi semua poin soal wajib
Physical Data Model: akurat, semua tabel/FK/ENUM verified
Activity Diagram: akurat, semua flow verified
Activity Log: implemented end-to-end (backend + frontend)

Soal Technical Test (Referensi)
Wajib:
2 user: admin dan approver ✅
Admin input booking + tentukan driver & approver ✅
Approval berjenjang minimal 2 level ✅
Approver act via aplikasi ✅
Dashboard dengan grafik pemakaian kendaraan ✅
Laporan periodik + export Excel ✅
README berisi username/password, DB version, PHP version, framework, panduan ✅

Bonus:

Physical Data Model ✅
Activity Diagram ✅
Log aplikasi pada tiap proses ✅
UI/UX responsif ✅

---

Database (6 Tabel)

users — id, name, email, password, role (admin/approver), approval_level (1/2/null), created_at
vehicles — id, name, plate_number, type (passenger/cargo), ownership (own/rental), region, status (available/maintenance/inactive), created_at
drivers — id, name, license_number, phone, status (active/inactive), created_at
bookings — id, booking_code, admin_id, vehicle_id, driver_id, requester_name, purpose, destination, start_date, end_date, status (waiting_level_1/waiting_level_2/approved/rejected), created_at
booking_approvals — id, booking_id, approver_id, level (1/2), status (pending/approved/rejected), notes, acted_at. UNIQUE KEY (booking_id, level)
activity_logs — id, user_id, action, entity_type, entity_id, description, ip_address, created_at

---

## Approval Flow (Penting untuk Dijelaskan)

```
Booking dibuat
  → status: waiting_level_1
  → 2 record booking_approvals dibuat otomatis (level 1 & 2, status: pending)

Level 1 approve
  → booking_approvals level 1: approved
  → booking status: waiting_level_2
  → Level 2 baru bisa act

Level 2 approve
  → booking_approvals level 2: approved
  → booking status: approved (final positif)

Siapapun reject
  → booking_approvals record: rejected
  → booking status: rejected (final, immutable)
  → Level lain tidak bisa act lagi
```

Booking bersifat immutable setelah submit — tidak bisa diedit.

---

## Keputusan Teknis Kunci + Alasan

JWT Manual
Requirement soal: tidak pakai library eksternal untuk auth.
Implementasi HMAC-SHA256 sendiri. Payload berisi id, name, email, role, approval_level.
Logout stateless — tidak ada token blacklist, token expired di sisi client.
Self-Approval Prevention
Approver yang dipilih tidak boleh sama dengan admin yang membuat booking.
Validasi di backend saat create — bukan hanya di frontend.
Overlap Check
Mencegah kendaraan atau driver di-booking di tanggal yang bertabrakan.
Query: cek booking aktif (waiting_level_1/waiting_level_2/approved) dengan date range overlap.
Logika: start_date_baru < end_date_existing AND end_date_baru > start_date_existing.
Diimplementasi sebagai fungsi reusable di BookingService.
Booking Code
Format: BK-YYYYMMDD-XXXX (4 karakter random alphanumeric uppercase).
Cek uniqueness sebelum insert untuk menghindari duplikasi.
Activity Log — Silent Failure
Log gagal tidak menghentikan proses utama.
Tujuan: audit trail tidak boleh merusak user experience jika DB log bermasalah.
Separation of Concern
Controller: handle request, validasi, return response.
Service: business logic (BookingService, JwtService, ActivityLogService).
Model: query layer.
Helper: fungsi reusable (isVehicleAvailable, isDriverAvailable).

---

## Authorization Matrix

| Action                      | Admin | Approver                            |
| --------------------------- | ----- | ----------------------------------- |
| CRUD Vehicles/Drivers/Users | ✅    | ❌                                  |
| Create Booking              | ✅    | ❌                                  |
| View semua Booking          | ✅    | ✅ (read-only)                      |
| Approve/Reject Booking      | ❌    | ✅ (hanya yang di-assign & giliran) |
| Dashboard                   | ✅    | ✅ (versi terbatas)                 |
| Laporan + Export Excel      | ✅    | ❌                                  |
| Activity Log                | ✅    | ❌                                  |

---

## Akun Default

| Role             | Email                 | Password |
| ---------------- | --------------------- | -------- |
| Admin            | admin@vehicle.com     | password |
| Approver Level 1 | approver1@vehicle.com | password |
| Approver Level 2 | approver2@vehicle.com | password |

---

## Alur Demo (30 Menit)

1. Login sebagai admin
2. Dashboard admin — tunjukkan summary cards, bar chart, donut chart, top 5 kendaraan
3. Buat Booking — isi form lengkap, pilih kendaraan/driver/approver L1 dan L2, submit
4. Tunjukkan booking baru di list dengan status waiting_level_1
5. Login sebagai approver1 → Dashboard → Persetujuan Saya → approve
6. Login sebagai approver2 → booking sudah waiting_level_2 → approve
7. Login sebagai admin → booking status approved
8. Laporan — set filter tanggal, Cari, Export Excel
9. Activity Log — tunjukkan semua aktivitas tercatat otomatis
10. Jika ada waktu — tunjukkan PDM dan Activity Diagram di folder docs

---

## Dokumen Tambahan

- docs/physical-data-model.png — Physical Data Model (ERD)
- docs/activity-diagram.png — Activity Diagram
