# Backend Hardening & Full Test Suite

## Konteks & tujuan

Audit investigasi oleh Antigravity terhadap `backend/app/` menemukan 1 bug security kritis
(fallback JWT secret kosong), 5 edge case belum dihandle (race condition di booking create
& approve, validasi approver L1=L2, auth check yang hilang di `show()`, dashboard approver
menampilkan booking yang belum gilirannya), 2 code smell minor, dan **0% test coverage
bisnis** (Pest belum ter-install, satu-satunya file test adalah boilerplate CI4 bawaan).

Tujuan pekerjaan ini: perbaiki seluruh temuan di atas sesuai prioritas, dan bangun test
suite otomatis (Pest) yang mencakup **seluruh backend** — bukan cuma modul yang bermasalah,
tapi juga modul yang audit nyatakan "bersih" — supaya business rule inti (overlap check,
approval 2-level, self-approval prevention, dsb.) benar-benar terverifikasi otomatis, bukan
cuma didokumentasikan.

> Catatan: pekerjaan ini murni perbaikan & testing di layer application/service.
> **Tidak ada perubahan skema database.**

## Requirement

1. `JwtService`: fail-fast kalau `jwt.secret` kosong/tidak ada di `.env` — throw config
   exception saat generate maupun verify token. Jangan default ke string kosong `''`.
2. Setup Pest (`composer require --dev pestphp/pest`), base `TestCase` yang meng-extend
   `CodeIgniter\Test\CIUnitTestCase`. Dilakukan 1x di Tahap 1.
3. `BookingController::create` — tambah validasi `approver_level1_id !== approver_level2_id`
   (422 kalau sama).
4. `BookingService::createBooking` + `isVehicleAvailable()`/`isDriverAvailable()` — overlap
   check dan insert harus berada dalam satu transaksi dengan pessimistic lock
   (`SELECT ... FOR UPDATE`) pada row `vehicles` & `drivers` yang relevan, dijalankan
   **sebelum** overlap check, supaya request konkuren untuk resource yang sama ter-serialize.
5. `BookingService::createBooking` — ganti pola `transStart()`/`transComplete()` implisit
   menjadi `try/catch` eksplisit dengan `transRollback()` di blok `catch` dan rethrow
   exception, supaya exception non-query (bukan cuma query gagal) tetap ter-rollback bersih.
6. `BookingController::approve` (dan `reject` untuk konsistensi) — lock row `bookings`
   (`SELECT ... FOR UPDATE` by id) di awal transaksi, sebelum baca status approval/booking,
   supaya request approve/reject konkuren untuk booking yang sama ter-serialize.
7. `BookingController::show` — tambah authorization check: kalau `authUser.role === 'approver'`,
   wajib ada record di `booking_approvals` dengan `approver_id` = dia untuk `booking_id` ini,
   else return 403.
8. `DashboardController` — query `pending_bookings` untuk approver di-JOIN ke `bookings`,
   filter tambahan: level 1 hanya muncul kalau `booking.status = waiting_level_1`, level 2
   hanya kalau `booking.status = waiting_level_2`.
9. `Filters.php` — hapus baris duplicate key `'cors'` (CI4 built-in `Cors::class` yang
   ter-overwrite) dan dead code `use CodeIgniter\Filters\Cors` yang di-comment.
10. Test regresi untuk seluruh modul backend: Auth/JWT, Users, Vehicles, Drivers, Bookings
    (create/list/detail/approve/reject), Dashboard, Reports, ActivityLogs.

## Tahapan implementasi

**Tahap 1 — Security & Test Foundation**

- Setup Pest (install, config `Pest.php`, base `TestCase`).
- Fix `JwtService` fail-fast (requirement 1).
- Test: `JwtServiceTest` — generate/verify valid token, expired token, malformed token
  (bukan 3-bagian), token dengan signature di-tamper, dan kondisi `jwt.secret` kosong
  harus throw exception (bukan menghasilkan token yang "jalan").

**Tahap 2 — Validasi Quick Win**

- Tambah validasi `approver_level1_id !== approver_level2_id` (requirement 3).
- ⚠️ Update `docs/api-contract.md` (section 422 validation `POST /api/bookings`) untuk
  mencantumkan kasus error baru ini — **butuh persetujuan eksplisit kamu** sebelum diubah,
  sesuai larangan di `AGENTS.md` poin 8.1.
- Test: `BookingCreateValidationTest` — cover ketujuh aturan bisnis create booking (date
  order, vehicle status, driver status, approver1 role/level, approver2 role/level,
  self-approval, L1≠L2).

**Tahap 3 — Concurrency Fix**

- Refactor `BookingService::createBooking`: pessimistic lock vehicle & driver row + pola
  try/catch + `transRollback()` eksplisit (requirement 4 & 5).
- Refactor `BookingController::approve` & `reject`: lock booking row di awal transaksi
  (requirement 6).
- Test: `BookingConcurrencyTest` — 2 pemanggilan `createBooking()` berurutan cepat untuk
  vehicle/driver & rentang tanggal yang sama harus menghasilkan tepat 1 sukses + 1 gagal
  (409); 2 pemanggilan `approve()` berurutan cepat untuk booking yang sama harus hanya 1
  yang berhasil mengubah status.
  > Catatan: Pest/PHPUnit berjalan sequential single-process, jadi "concurrent" di sini
  > disimulasikan lewat 2 pemanggilan service berurutan yang saling overlap secara logis
  > (bukan literal parallel thread) — cukup untuk memverifikasi logika lock bekerja, bukan
  > untuk stress-test performa di bawah beban nyata.

**Tahap 4 — Authorization Fix**

- Tambah auth check di `BookingController::show` (requirement 7).
- Test: `BookingShowAuthTest` — approver ter-assign bisa akses, approver tidak ter-assign
  → 403, admin selalu bisa akses semua booking.

**Tahap 5 — Dashboard Fix**

- Fix query `pending_bookings` approver (requirement 8).
- Test: `DashboardApproverTest` — approver level 2 tidak muncul di pending list selama
  status booking masih `waiting_level_1`; muncul begitu status jadi `waiting_level_2`.

**Tahap 6 — Cleanup & Full Suite**

- Hapus dead code `Filters.php` (requirement 9).
- Jalankan full suite `vendor/bin/pest` sesuai kebijakan `AGENTS.md` section 5 (wajib
  sebelum fitur ditutup formal di `done.md`).

**Tahap 7 — Regression Coverage Modul Lain**
Test untuk modul yang audit nyatakan "bersih" tapi belum ada test otomatis sama sekali:

- `UserController`: validasi `approval_level` (create & update, role admin/approver),
  self-delete prevention.
- `VehicleController` & `DriverController`: CRUD + filter `?status=`.
- `ReportController`: JSON list & export `.xlsx` (cek isi stream, bukan cuma status code).
- `ActivityLogController`: filter `entity_type`/`start_date`/`end_date`, paginasi 50/halaman.
- `ActivityLogService`: memastikan seluruh aksi utama (login, CRUD master data,
  create/approve/reject booking) benar-benar tercatat.

## Skema/struktur data

Tidak ada perubahan skema database. Seluruh perbaikan di layer Service/Controller/Filter.

## Edge case yang perlu dihandle

Sesuai temuan audit (referensi nomor sesuai laporan audit):

1. Race condition overlap check sebelum transaksi (create booking).
2. `transStart()`/`transComplete()` tidak rollback bersih kalau exception non-query terjadi.
3. `approver_level1_id` boleh sama dengan `approver_level2_id`.
4. Race condition di `approve()` — double-click atau 2 request bersamaan lolos validasi.
5. `show()` tidak ada auth check untuk approver — bocor data booking.
6. Fallback JWT secret kosong — auth bypass total kalau `.env` tidak lengkap.
7. Dead code & duplicate key di `Filters.php`.
8. Dashboard approver L2 melihat booking yang belum gilirannya.

## Testing

Ringkasan file test yang harus ada setelah seluruh tahap selesai:

- `JwtServiceTest`
- `BookingCreateValidationTest`
- `BookingConcurrencyTest`
- `BookingShowAuthTest`
- `DashboardApproverTest`
- `UserControllerTest`, `VehicleControllerTest`, `DriverControllerTest`
- `ReportControllerTest`, `ActivityLogControllerTest`, `ActivityLogServiceTest`

Semua test wajib meng-assert aturan bisnis nyata (sesuai `AGENTS.md` section 5), bukan
sekadar tes kosmetik/boilerplate. Full suite (`vendor/bin/pest`) wajib hijau sebelum
Tahap 6 ditutup.

## Kriteria selesai

- Seluruh 7 tahap di atas selesai, masing-masing dengan test yang lolos.
- Full suite `vendor/bin/pest` hijau tanpa regresi pada endpoint yang sudah ada.
- `docs/done.md` diupdate dengan entry baru mereferensikan spec ini.
- `docs/api-contract.md` diupdate untuk validasi L1≠L2 (Tahap 2) — **setelah persetujuan
  eksplisit user**, bukan diubah sepihak oleh Antigravity.
- Setiap penyimpangan dari spec ini (kalau ada, misal detail implementasi lock yang
  berbeda) dicatat di entry `done.md` sesuai kebijakan `AGENTS.md`.
