# PROJECT CONTEXT — Sistem Pemesanan Kendaraan

## Overview

Technical test untuk posisi Fullstack Developer (PHP 8 + CodeIgniter 4 + React).
Aplikasi pemesanan kendaraan untuk perusahaan tambang nikel dengan multi-region,
multi-vehicle, dan approval berjenjang 2 level.

---

## Tech Stack

| Layer     | Technology                          |
|-----------|-------------------------------------|
| Backend   | PHP 8 + CodeIgniter 4               |
| Frontend  | React + Vite + Tailwind CSS         |
| Database  | MySQL                               |
| Auth      | JWT (manual, bukan library eksternal)|
| Export    | PhpSpreadsheet                      |

---

## Roles

| Role     | Keterangan                                      |
|----------|-------------------------------------------------|
| admin    | Mengelola master data dan membuat booking       |
| approver | Menyetujui atau menolak booking yang di-assign  |

`approval_level` di tabel `users`:
- `1` → approver level pertama
- `2` → approver level kedua
- `null` → admin (tidak punya level approval)

---

## Physical Data Model (Final)

```
users
  id              BIGINT PK AI
  name            VARCHAR(100)
  email           VARCHAR(100) UNIQUE
  password        VARCHAR(255)
  role            ENUM('admin','approver')
  approval_level  TINYINT NULL        -- 1|2|NULL
  created_at      DATETIME

vehicles
  id              BIGINT PK AI
  name            VARCHAR(100)
  plate_number    VARCHAR(20) UNIQUE
  type            ENUM('passenger','cargo')
  ownership       ENUM('own','rental')
  region          VARCHAR(100)
  status          ENUM('available','maintenance','inactive')
  created_at      DATETIME

drivers
  id              BIGINT PK AI
  name            VARCHAR(100)
  license_number  VARCHAR(50)
  phone           VARCHAR(20)
  status          ENUM('active','inactive')
  created_at      DATETIME

bookings
  id              BIGINT PK AI
  booking_code    VARCHAR(20) UNIQUE       -- format: BK-YYYYMMDD-XXXX
  admin_id        BIGINT FK → users.id
  vehicle_id      BIGINT FK → vehicles.id
  driver_id       BIGINT FK → drivers.id
  requester_name  VARCHAR(100)
  purpose         TEXT
  destination     TEXT
  start_date      DATETIME
  end_date        DATETIME
  status          ENUM('waiting_level_1','waiting_level_2','approved','rejected')
  created_at      DATETIME

booking_approvals
  id              BIGINT PK AI
  booking_id      BIGINT FK → bookings.id
  approver_id     BIGINT FK → users.id
  level           TINYINT                  -- 1|2
  status          ENUM('pending','approved','rejected')
  notes           TEXT NULL
  acted_at        DATETIME NULL
  UNIQUE KEY      (booking_id, level)

activity_logs
  id              BIGINT PK AI
  user_id         BIGINT FK → users.id
  action          VARCHAR(50)
  entity_type     VARCHAR(20)              -- 'booking'|'vehicle'|'driver'|'user'
  entity_id       BIGINT
  description     TEXT
  ip_address      VARCHAR(45)
  created_at      DATETIME
```

---

## API Response Shape

### Success
```json
{
  "status": true,
  "message": "Success",
  "data": {}
}
```

### Error / Validation
```json
{
  "status": false,
  "message": "Validation failed",
  "errors": {
    "field": "error message"
  }
}
```

---

## Folder Structure

### Backend (CI4)
```
app/
  Controllers/
    Api/
      AuthController.php
      BookingController.php
      VehicleController.php
      DriverController.php
      UserController.php
      DashboardController.php
      ReportController.php
  Models/
    UserModel.php
    VehicleModel.php
    DriverModel.php
    BookingModel.php
    BookingApprovalModel.php
    ActivityLogModel.php
  Services/
    BookingService.php       -- overlap check, create booking + approvals
    ActivityLogService.php   -- log writer
    JwtService.php
  Helpers/
    AvailabilityHelper.php   -- isVehicleAvailable(), isDriverAvailable()
```

### Frontend (React)
```
src/
  services/
    authService.js
    bookingService.js
    vehicleService.js
    driverService.js
    userService.js
    dashboardService.js
    reportService.js
  pages/
    admin/
      Dashboard.jsx
      Bookings.jsx
      BookingCreate.jsx
      BookingDetail.jsx
      Vehicles.jsx
      Drivers.jsx
      Users.jsx
      Reports.jsx
    approver/
      Dashboard.jsx
      MyApprovals.jsx
      BookingDetail.jsx
      AllBookings.jsx
    Login.jsx
  components/
    common/
      DataTable.jsx
      FormDialog.jsx
      ConfirmDialog.jsx
      StatusBadge.jsx
    layout/
      AdminLayout.jsx
      ApproverLayout.jsx
```

---

## Naming Conventions

| Layer         | Convention                         | Contoh                        |
|---------------|------------------------------------|-------------------------------|
| Controller    | Singular + PascalCase              | `BookingController`           |
| Model         | Singular + PascalCase + suffix     | `BookingModel`                |
| Service       | Singular + PascalCase + suffix     | `BookingService`              |
| DB table      | Plural + snake_case                | `booking_approvals`           |
| API endpoint  | Plural + kebab-case                | `/api/bookings`, `/api/users` |
| React page    | PascalCase                         | `BookingCreate.jsx`           |
| React service | camelCase + suffix                 | `bookingService.js`           |

---

## Business Rules

### Booking Creation
- `start_date` < `end_date` wajib (tidak boleh sama atau terbalik)
- `requester_name` wajib diisi
- Kendaraan dengan status `maintenance` atau `inactive` tidak bisa dipilih
- Driver dengan status `inactive` tidak bisa dipilih
- Saat create booking → 2 record `booking_approvals` dibuat otomatis (level 1 & 2, status `pending`)
- `booking_code` digenerate otomatis: format `BK-YYYYMMDD-XXXX` (XXXX = 4 char random alphanumeric uppercase, cek uniqueness sebelum insert)
- Booking bersifat **immutable** setelah submit — tidak bisa diedit

### Self-Approval Prevention
- `approver_id` (level 1 maupun level 2) tidak boleh sama dengan `admin_id` yang sedang login
- Validasi di backend saat create booking

### Approver Assignment
- Approver yang dipilih harus punya `approval_level` yang sesuai dengan level yang di-assign
- User level 1 hanya boleh jadi approver level 1, begitu pula level 2

### Overlap Check (Vehicle & Driver)
```sql
SELECT 1 FROM bookings
WHERE [vehicle_id | driver_id] = ?
  AND status IN ('waiting_level_1', 'waiting_level_2', 'approved')
  AND start_date < :end_date
  AND end_date   > :start_date
  AND id != :current_booking_id   -- hanya saat update
LIMIT 1
```
Diimplementasi sebagai fungsi reusable:
- `isVehicleAvailable(vehicle_id, start, end, exclude_id = null)`
- `isDriverAvailable(driver_id, start, end, exclude_id = null)`

### Approval Flow
```
Booking dibuat → status: waiting_level_1
                → 2 booking_approvals dibuat (level 1 & 2, status: pending)

Level 1 approve → booking_approvals level 1: approved
                → booking status: waiting_level_2
                → Level 2 baru bisa act

Level 2 approve → booking_approvals level 2: approved
                → booking status: approved (final positif)

Siapapun reject → booking_approvals record: rejected
                → booking status: rejected (final, immutable)
                → Level lain tidak perlu act

Re-approve setelah rejected: TIDAK diizinkan
```

### Stuck Booking
- Known behavior — tidak ada auto-expire
- Booking tetap di status `waiting_level_1` atau `waiting_level_2` sampai approver act
- UI menampilkan sebagai pending indefinitely

---

## Authorization Matrix

| Action                              | Admin | Approver |
|-------------------------------------|-------|----------|
| Login / Logout                      | ✅    | ✅       |
| CRUD Vehicles                       | ✅    | ❌       |
| CRUD Drivers                        | ✅    | ❌       |
| CRUD Users                          | ✅    | ❌       |
| Create Booking                      | ✅    | ❌       |
| View semua Booking                  | ✅    | ✅ (read-only) |
| View detail Booking                 | ✅    | ✅ (read-only) |
| Approve/Reject Booking              | ❌    | ✅ (hanya yang di-assign & giliran mereka) |
| View Dashboard                      | ✅    | ✅ (versi terbatas) |
| View Reports & Export Excel         | ✅    | ❌       |
| View Activity Logs                  | ✅    | ❌       |

---

## Dashboard Specification

### Admin Dashboard
- **Summary cards (4):**
  - Total kendaraan aktif (`status = available`)
  - Total booking bulan ini
  - Pending approval (waiting_level_1 + waiting_level_2)
  - Approved bulan ini
- **Bar chart:** Jumlah booking per bulan (12 bulan terakhir, semua status)
- **Donut chart:** Distribusi status booking (waiting / approved / rejected)
- **Horizontal bar chart:** Top 5 kendaraan paling sering dipakai (berdasarkan booking approved)

### Approver Dashboard
- **Summary card (1):** Jumlah booking menunggu approval saya
- **Tabel:** Daftar booking pending yang di-assign ke saya (dengan tombol approve/reject)

---

## Laporan Export Excel

**Filter:** date range (`start_date`) + status (opsional, default semua)

**Kolom:**
```
No | Booking Code | Requester | Kendaraan | Plat Nomor | Driver |
Tujuan | Keperluan | Tgl Mulai | Tgl Selesai | Status |
Approver L1 | Status L1 | Approver L2 | Status L2 |
Dibuat Oleh | Dibuat Pada
```

---

## Activity Log

### Events yang Di-log
| Action                | Entity Type | Keterangan                            |
|-----------------------|-------------|---------------------------------------|
| `user.login`          | `user`      | Setiap login berhasil                 |
| `booking.created`     | `booking`   | Admin submit booking baru             |
| `booking.approved`    | `booking`   | Approver approve (sertakan level)     |
| `booking.rejected`    | `booking`   | Approver reject (sertakan level)      |
| `vehicle.created`     | `vehicle`   | Admin tambah kendaraan                |
| `vehicle.updated`     | `vehicle`   | Admin edit kendaraan                  |
| `vehicle.deleted`     | `vehicle`   | Admin hapus kendaraan                 |
| `driver.created`      | `driver`    | Admin tambah driver                   |
| `driver.updated`      | `driver`    | Admin edit driver                     |
| `driver.deleted`      | `driver`    | Admin hapus driver                    |
| `user.created`        | `user`      | Admin tambah user                     |
| `user.updated`        | `user`      | Admin edit user                       |
| `user.deleted`        | `user`      | Admin hapus user                      |

### Format Description
Human-readable, contoh:
- `"Admin John membuat booking BK-20250417-A3X9 untuk kendaraan B 1234 XY"`
- `"Approver Jane (Level 1) menyetujui booking BK-20250417-A3X9"`
- `"Approver Budi (Level 2) menolak booking BK-20250417-A3X9: alasan terlalu jauh"`

---

## Seeder Data (untuk README)

```
Admin:
  email: admin@vehicle.com
  password: password

Approver Level 1:
  email: approver1@vehicle.com
  password: password

Approver Level 2:
  email: approver2@vehicle.com
  password: password
```

---

## Deliverables (dari Soal)

| No | Item                                          | Status       |
|----|-----------------------------------------------|--------------|
| 1  | Aplikasi pemesanan kendaraan (2 role)         | Target       |
| 2  | Admin input booking + tentukan driver & approver | Target    |
| 3  | Approval berjenjang minimal 2 level           | Target       |
| 4  | Approver act via aplikasi                     | Target       |
| 5  | Dashboard dengan grafik pemakaian kendaraan   | Target       |
| 6  | Laporan periodik + export Excel               | Target       |
| 7  | README (user/pass, versi, panduan)            | Target       |
| +  | Physical Data Model                           | Target (bonus)|
| +  | Activity Diagram                              | Target (bonus)|
| +  | Log aplikasi tiap proses                      | Target (bonus)|
| +  | UI/UX responsive                              | Target (bonus)|
