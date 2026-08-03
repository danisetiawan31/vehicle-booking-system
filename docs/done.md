# DONE — Implementasi Sistem Pemesanan Kendaraan

Dokumen ini berisi catatan histori pengerjaan dan status final seluruh komponen sistem yang telah diverifikasi secara penuh pada codebase backend (CodeIgniter 4) dan frontend (React + Vite).

---

## Status Global

- **Status Proyek:** **SELESAI (100% Implemented & Verified)**
- **Coverage:**
  - Backend REST API (6 Migration, 6 Model, 4 Service, 2 Filter, 8 Controller, 4 Seeder)
  - Automated Testing Suite (43 Pest Tests, 145 Assertions Passed)
  - Frontend SPA (13 Pages, 3 Layouts, 8 Services, 7 Reusable Components, 9 Radix/shadcn Primitives)

---

## Summary Selesai per Lapisan

### 1. Database & Seeders

- [x] **Migrations (6/6):** `users`, `vehicles`, `drivers`, `bookings`, `booking_approvals`, `activity_logs`.
- [x] **Seeder Data:** `MainSeeder` (`UserSeeder`, `VehicleSeeder`, `DriverSeeder`, `BookingSeeder`) menghasilkan 3 user, 6 kendaraan, 4 driver, dan 10 booking dengan variasi status & approval.
- [x] **Timezone:** `Asia/Jakarta` (WIB) dikonfigurasi global di `App.php` & utility tanggal frontend `utils.js`.

### 2. Backend API (CodeIgniter 4)

- [x] **Models (6/6):** `UserModel`, `VehicleModel`, `DriverModel`, `BookingModel`, `BookingApprovalModel`, `ActivityLogModel`.
- [x] **Services (4/4):** `JwtService` (HMAC-SHA256), `AuthContext`, `ActivityLogService`, `BookingService` (generator kode `BK-YYYYMMDD-XXXX`, overlap check, transaksi 2-level approval).
- [x] **Filters & Guards:** `JwtAuthFilter` (verifikasi Bearer token), `CorsFilter` (CORS handling).
- [x] **Controllers (8/8):**
  - `AuthController`: Login & Logout (stateless)
  - `UserController`: CRUD user, role admin/approver, validation `approval_level`, self-delete prevention
  - `VehicleController`: CRUD kendaraan, filter status, status `available`
  - `DriverController`: CRUD driver, filter status, status `active`
  - `BookingController`: List (filtered per approver), Detail (nested objects), Create (validasi 7 aturan bisnis), Approve (cek turn), Reject (notes wajib)
  - `DashboardController`: Summary admin (cards, 12-month chart, status donut, top 5 vehicles) & summary approver (pending for me)
  - `ReportController`: List JSON & Export Excel `.xlsx` via PhpSpreadsheet
  - `ActivityLogController`: Audit log list, filter entity/date, paginasi

### 3. Frontend SPA (React + Vite + Tailwind CSS)

- [x] **Routing & Auth:** `App.jsx` (React Router v7), `RootRedirect` (role-based), `ProtectedRoute` (`admin` / `approver`).
- [x] **Layouts:** `AdminLayout`, `ApproverLayout`, `SidebarLayout` (collapsible, active NavLink, mobile overlay).
- [x] **Services (8/8):** `authService`, `userService`, `vehicleService`, `driverService`, `bookingService`, `dashboardService`, `reportService`, `activityLogService` (Axios interceptor + auto 401 logout/redirect).
- [x] **Admin Pages (9/9):** `Login`, `Dashboard`, `Bookings`, `BookingCreate`, `BookingDetail`, `Vehicles`, `Drivers`, `Users`, `Reports`, `ActivityLogs`.
- [x] **Approver Pages (4/4):** `Dashboard`, `MyApprovals`, `Bookings`, `BookingDetail`.
- [x] **Common Components:** `DataTable` (reusable with skeleton loader), `FormDialog` (Radix Dialog), `ConfirmDialog` (Radix AlertDialog), `InputField`, `StatusBadge`, `Button`.

### 4. Backend Hardening & Automated Testing Suite (Pest)

- [x] **Test Setup & Framework Foundation (Tahap 1):** `pestphp/pest ^2.35`, base `TestCase` (`CodeIgniter\Test\CIUnitTestCase`), `Pest.php`, fail-fast exception pada `JwtService` bila secret kosong (`JwtServiceTest` 6 unit tests).
- [x] **Validasi Approver L1 != L2 (Tahap 2):** Validasi baru pada `BookingController::create()` menolak payload jika `approver_level1_id === approver_level2_id` (HTTP 422) (`BookingCreateValidationTest` 8 integration tests).
- [x] **Concurrency & Pessimistic Row Locking (Tahap 3):**
  - Refactor `BookingService::createBooking()` mengunci baris kendaraan & driver secara konsisten (`vehicle` dahulu baru `driver`) via `SELECT ... FOR UPDATE` + transaksi eksplisit (`transBegin()`, `transCommit()`, `transRollback()`).
  - Refactor `BookingController::approve()` & `reject()` mengunci baris booking & approval via `SELECT ... FOR UPDATE` sebelum membaca status + transaksi eksplisit.
  - Test suite `BookingConcurrencyTest` (4 test cases: 2-connection unshared pessimistic lock primitive, overlap creation prevention, double-approve prevention, double-reject prevention).
- [x] **Authorization Check `BookingController::show()` (Tahap 4):** Approver hanya dapat mengakses detail pemesanan jika terdaftar di `booking_approvals` (HTTP 403 jika unassigned) (`BookingShowAuthTest` 4 integration tests).
- [x] **Filter Sequential Turn Dashboard Approver (Tahap 5):** `DashboardController::buildApproverDashboard()` memfilter `pending_bookings` & `summary.pending_for_me` secara akurat sesuai giliran level (`waiting_level_1` untuk Level 1, `waiting_level_2` untuk Level 2) (`DashboardApproverTest` 1 test case).
- [x] **Cleanup Filters.php (Tahap 6):** Memangkas komentar impor mati dan baris duplikat alias `'cors' => Cors::class` di `app/Config/Filters.php`.
- [x] **Regression Test & Fix UserController (Tahap 7a):** Penambahan validasi `approval_level` role `admin` pada `UserController::update()` & suite regresi `UserControllerTest` (15 test cases: CRUD, role validation, self-delete prevention, password filtering).
- [x] **Global Pest Test Suite:** **43 Passed (145 Assertions)**.

---

## Catatan Technical Debt & Penyesuaian

1. **Technical Debt Response Format:**
   - `POST /api/auth/login` (401 Error) mengembalikan `"data": null` alih-alih `"errors"`.
   - `VehicleController` & `DriverController` (422 Error) mengembalikan objek error validasi di key `"data"` alih-alih `"errors"`.
   - Kedua deviasi di atas ditangani dengan aman di layer frontend (`err.response.data.data` || `err.response.data.errors`).
2. **Immutability Pemesanan:** Booking yang disubmit bersifat read-only bagi Admin (tidak dapat diedit), hanya dapat diajukan transisi status via aksi `approve` / `reject` oleh Approver.
