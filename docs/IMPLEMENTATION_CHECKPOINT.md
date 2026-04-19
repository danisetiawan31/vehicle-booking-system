IMPLEMENTATION CHECKPOINT — Sistem Pemesanan Kendaraan

STATUS GLOBAL
Fase aktif: Frontend selesai semua. Siap untuk testing menyeluruh dan README.

LAPISAN DATABASE
Semua tabel sudah dibuat via migration dan siap digunakan.
Tabel: users, vehicles, drivers, bookings, booking_approvals, activity_logs.
Urutan migration sudah benar sesuai dependency FK.
Timezone aplikasi dikonfigurasi ke Asia/Jakarta.

LAPISAN MODEL
Semua model sudah ada dan terkonfigurasi.
Tidak ada validasi di dalam model — validasi dilakukan di controller.
BookingApprovalModel tidak menggunakan useTimestamps karena acted_at dikelola manual oleh service.

LAPISAN SERVICE
JwtService — generate dan verify JWT (HMAC-SHA256, exp 24 jam, secret dari env jwt.secret)
AuthContext — static holder untuk JWT payload selama siklus request
ActivityLogService — menulis activity log, silent failure (tidak throw exception)
BookingService — generateBookingCode, isVehicleAvailable, isDriverAvailable, createBooking

LAPISAN FILTER & ROUTING
JwtAuthFilter memverifikasi Bearer token dan menyimpan payload via AuthContext::set().
Semua endpoint kecuali POST /api/auth/login wajib melewati filter ini.
Routing terdaftar untuk: auth, users, vehicles, drivers, bookings, dashboard, reports.

LAPISAN CONTROLLER — KONDISI TIAP CONTROLLER

AuthController (selesai)
Endpoint: POST /api/auth/login, POST /api/auth/logout
Login memverifikasi kredensial, menghasilkan JWT, dan mencatat activity log.
Logout bersifat stateless — tidak ada invalidasi token di server.

UserController (selesai)
Endpoint: GET/POST /api/users, PUT/DELETE /api/users/:id
Hanya dapat diakses oleh role admin.
Validasi approval_level: approver wajib level 1 atau 2, admin wajib null.
Password tidak pernah dikembalikan dalam response.
Self-delete dicegah di endpoint delete.

VehicleController (selesai)
Endpoint: GET/POST /api/vehicles, PUT/DELETE /api/vehicles/:id
Hanya dapat diakses oleh role admin.
GET mendukung filter opsional via query param status.

DriverController (selesai)
Endpoint: GET/POST /api/drivers, PUT/DELETE /api/drivers/:id
Hanya dapat diakses oleh role admin.
GET mendukung filter opsional via query param status.

BookingController (selesai)
Endpoint: GET /api/bookings, POST /api/bookings, GET /api/bookings/:id,
POST /api/bookings/:id/approve, POST /api/bookings/:id/reject
GET /api/bookings mendukung filter opsional via query param status (where b.status).
Admin melihat semua booking. Approver hanya melihat booking yang di-assign ke mereka.
Detail booking menyertakan vehicle, driver, admin (nested), dan array approvals
(level, status, notes, acted_at, approver_name — tidak menyertakan approver_id).
Create booking: validasi CI4 + 7 business check manual + delegasi ke BookingService.
Approve: cek pending dulu, lalu cek giliran, transaksi, log di luar transaksi.
Reject: cek pending, cek status masih actionable, notes wajib, transaksi, log di luar transaksi.

DashboardController (selesai)
Endpoint: GET /api/dashboard
Admin mendapat summary cards, bookings per bulan (12 bulan terakhir),
distribusi status, dan top 5 kendaraan terbanyak dipakai.
Approver mendapat jumlah pending miliknya dan daftar booking pending yang di-assign ke mereka.

ReportController (selesai)
Endpoint: GET /api/reports, GET /api/reports/export
Hanya admin. Filter wajib: start_date dan end_date (Y-m-d).
Filter opsional: status. Data di-JOIN dengan vehicle, driver, admin, approver L1 dan L2.
Export menghasilkan file .xlsx via PhpSpreadsheet, di-stream langsung tanpa menyimpan ke disk.

ActivityLogController (selesai)
Endpoint: GET /api/activity-logs
Hanya admin. Filter opsional: entity_type, start_date, end_date, page.
Response: { logs[], pagination: { total, page, limit, total_pages } }
logs menyertakan user_name via LEFT JOIN ke tabel users.

SEEDER
Tersedia via php spark db:seed MainSeeder.
Mengisi: 3 users, 6 vehicles, 4 drivers, 10 bookings dengan variasi status
(approved, waiting_level_1, waiting_level_2, rejected) dan booking_approvals
yang konsisten dengan setiap status. Data tersebar di 2 bulan untuk keperluan
chart dashboard.

KONVENSI RESPONSE (berlaku untuk semua controller)
Success: { "status": true, "message": "...", "data": {} }
Error: { "status": false, "message": "...", "data": null }
Validasi: { "status": false, "message": "Validation failed", "errors": { "field": "pesan" } }
Catatan: VehicleController mengembalikan validation errors di key "data", bukan "errors".
Frontend sudah handle keduanya (cek err.response.data.data dan err.response.data.errors).

LAPISAN FRONTEND

SETUP & KONFIGURASI
Vite + React 19 + Tailwind CSS v4 + shadcn/ui (Nova preset, non-TSX).
Alias @ → src/. VITE_API_URL → http://localhost:8080/api.
CORS backend sudah fix via CorsFilter + OPTIONS route.

FONDASI

- src/lib/utils.js — cn() helper
- src/utils/utils.js — formatDate, formatDateShort, formatCurrentMonth,
  formatMonthLabel, getStatusLabel. parseDate internal (fix timezone WIB).
- src/services/api.js — axios instance, Bearer token interceptor,
  401 → clear localStorage + redirect /login
- src/context/AuthContext.js, AuthProvider.jsx, useAuth.js

ROUTING & AUTH

- src/App.jsx — createBrowserRouter, RootRedirect (role-based), ProtectedRoute
- Route admin: /admin/dashboard, bookings, bookings/create, bookings/:id,
  vehicles, drivers, users, reports
- Route approver: /approver/dashboard, approvals, bookings, bookings/:id
- Semua route approver sudah terisi, tidak ada placeholder tersisa

LAYOUT

- SidebarLayout.jsx — collapsible desktop, mobile overlay, active NavLink
- AdminLayout.jsx, ApproverLayout.jsx

SERVICES

- api.js, authService.js
- vehicleService.js — getVehicles(params), createVehicle, updateVehicle, deleteVehicle
- driverService.js — getDrivers(params), createDriver, updateDriver, deleteDriver
- userService.js — getUsers(params), createUser, updateUser, deleteUser
- dashboardService.js — getDashboard
- bookingService.js — getBookings(params), getBookingById, createBooking,
  approveBooking, rejectBooking
- reportService.js — getReports(params), exportReports(params) [responseType blob]
- src/services/activityLogService.js — getActivityLogs(params)

HALAMAN SELESAI

- Login.jsx
- admin/Dashboard.jsx — 4 summary cards (gradient, border-left accent, subtitle),
  bar chart booking/bulan, donut chart distribusi status (center total label),
  top 5 kendaraan (custom list + progress bar)
- admin/Vehicles.jsx — list + filter status + CRUD
- admin/Drivers.jsx — list + filter status + CRUD
- admin/Users.jsx — list + CRUD, Select role & approval_level fully controlled
- admin/Bookings.jsx — list + filter status (query param ke backend), navigasi ke detail dan create
- admin/BookingDetail.jsx — read-only, 3 card (info booking, kendaraan & driver,
  riwayat approval). Field via nested objects: booking.vehicle._, booking.driver._,
  booking.admin.name
- admin/BookingCreate.jsx — form create, Promise.all fetch dropdowns,
  datetime-local → Y-m-d H:i:s conversion, 409 conflict banner
- admin/Reports.jsx — filter date range + status, fetch on demand,
  export Excel via blob download
- approver/Dashboard.jsx — 3 summary cards, urgency banner, tabel pending bookings
- approver/MyApprovals.jsx — list pending bookings milik approver, approve via
  ConfirmDialog, reject via FormDialog dengan textarea notes wajib.
  isActionable pakai Number(user.approval_level) vs booking.status untuk cek giliran.
  Flat fields: vehicle_name, driver_name, plate_number.
- approver/Bookings.jsx — list semua booking read-only, filter status (query param
  ke backend), navigasi ke /approver/bookings/:id. Flat fields dari list endpoint.
- approver/BookingDetail.jsx — read-only 3 card (info booking, kendaraan & driver,
  riwayat approval) + card Aksi kondisional. isActionable cek myApproval.status
  === "pending" AND giliran via approval*level vs booking.status. Approve via
  ConfirmDialog, reject via FormDialog. Setelah aksi: refetch booking, tidak navigate away.
  Nested fields: booking.vehicle.*, booking.driver.\_, booking.admin.name.
  Approvals array: level, status, notes, acted_at, approver_name.
- src/pages/admin/ActivityLogs.jsx — tabel read-only, filter entity_type +
  date range, pagination Prev/Next, fetch on mount dan on apply
- Route /admin/activity-logs dan NavLink "Activity Log" sudah terdaftar

KOMPONEN REUSABLE

- Button.jsx, InputField.jsx, StatusBadge.jsx
- ConfirmDialog.jsx, FormDialog.jsx, DataTable.jsx (index sebagai arg kedua render)

SHADCN COMPONENTS TERINSTALL
button, input, label, card, badge, dialog, alert-dialog, table, select
