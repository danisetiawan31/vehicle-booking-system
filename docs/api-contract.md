# REST API Contract — Vehicle Booking System

Dokumen ini adalah acuan resmi (ground-truth) seluruh 23 REST API endpoint di bawah namespace `/api`, terverifikasi dari [Routes.php](file:///d:/project/vehicle-booking/backend/app/Config/Routes.php), [JwtAuthFilter.php](file:///d:/project/vehicle-booking/backend/app/Filters/JwtAuthFilter.php), dan Controller di [app/Controllers/Api/](file:///d:/project/vehicle-booking/backend/app/Controllers/Api/).

---

## 1. Standard Response Format & Deviations

### Format Standar Aplikasi
- **Success (200 / 201):** `{ "status": true, "message": "...", "data": <object|array|null> }`
- **Error Standar (422 / 400):** `{ "status": false, "message": "...", "errors": { "<field>": "..." } }`
- **Common Auth Errors:**
  - **401 Unauthorized:** `{ "status": false, "message": "Unauthorized", "data": null }` (dari `JwtAuthFilter`)
  - **403 Forbidden:** `{ "status": false, "message": "Forbidden", "data": null }` (pemeriksaan Role)
  - **404 Not Found:** `{ "status": false, "message": "<Item> not found", "data": null }`

> [!WARNING]
> **Deviasi Response Format dalam Kode (Catatan Integrasi Frontend):**
> 1. `POST /api/auth/login` (401 Error): Mengembalikan `"data": null` alih-alih `"errors"`.
> 2. `VehicleController` & `DriverController` (422 Validation Error): Mengembalikan objek error di dalam key `"data"` alih-alih `"errors"`.
> 3. `GET /api/reports/export`: Mengembalikan raw stream file binary Excel (`.xlsx`).

---

## 2. Endpoint Summary Table

| Resource | Method | Endpoint | Auth Filter | Allowed Roles | Deskripsi Singkat |
| :--- | :--- | :--- | :---: | :---: | :--- |
| **Auth** | `POST` | `/api/auth/login` | Public | Unauthenticated | Login user & dapatkan token JWT |
| | `POST` | `/api/auth/logout` | Protected | `admin`, `approver` | Logout / respons sukses |
| **Users** | `GET` | `/api/users` | Protected | `admin` | List semua user (tanpa password) |
| | `POST` | `/api/users` | Protected | `admin` | Tambah user baru (`admin`/`approver`) |
| | `PUT` | `/api/users/(:num)` | Protected | `admin` | Update data user |
| | `DELETE` | `/api/users/(:num)` | Protected | `admin` | Hapus user (mencegah hapus akun sendiri) |
| **Vehicles** | `GET` | `/api/vehicles` | Protected | `admin` | List kendaraan (support query `?status=`) |
| | `POST` | `/api/vehicles` | Protected | `admin` | Tambah kendaraan baru |
| | `PUT` | `/api/vehicles/(:num)` | Protected | `admin` | Update data kendaraan |
| | `DELETE` | `/api/vehicles/(:num)` | Protected | `admin` | Hapus kendaraan |
| **Drivers** | `GET` | `/api/drivers` | Protected | `admin` | List driver (support query `?status=`) |
| | `POST` | `/api/drivers` | Protected | `admin` | Tambah driver baru |
| | `PUT` | `/api/drivers/(:num)` | Protected | `admin` | Update data driver |
| | `DELETE` | `/api/drivers/(:num)` | Protected | `admin` | Hapus driver |
| **Bookings** | `GET` | `/api/bookings` | Protected | `admin`, `approver` | List booking (approver auto-filtered JOIN) |
| | `POST` | `/api/bookings` | Protected | `admin` | Buat pemesanan baru + 2 level approvals |
| | `GET` | `/api/bookings/(:num)` | Protected | `admin`, `approver` | Detail booking + vehicle, driver, approvals |
| | `POST` | `/api/bookings/(:num)/approve` | Protected | `approver` | Approve booking sesuai giliran level |
| | `POST` | `/api/bookings/(:num)/reject` | Protected | `approver` | Reject booking (wajib isi `notes`) |
| **Dashboard** | `GET` | `/api/dashboard` | Protected | `admin`, `approver` | Data statistik (berbeda per role) |
| **Reports** | `GET` | `/api/reports` | Protected | `admin` | JSON laporan pemesanan kendaraan |
| | `GET` | `/api/reports/export` | Protected | `admin` | Download file `.xlsx` laporan |
| **Logs** | `GET` | `/api/activity-logs` | Protected | `admin` | Audit log aktivitas pengguna |

---

## 3. Detailed Endpoint Specifications

### A. Authentication (`/api/auth`)
* **`POST /api/auth/login`**
  - **Body:** `{ "email": "...", "password": "..." }` (Required)
  - **Success (200):** Data berisi `token` (JWT) & object `user` (`id`, `name`, `email`, `role`, `approval_level`).
  - **Errors:** 422 (Validation failure), 401 (Invalid credentials $\rightarrow$ `"data": null`).

* **`POST /api/auth/logout`**
  - **Success (200):** `{ "status": true, "message": "Logout successful", "data": null }`.

---

### B. User Management (`/api/users`) — Admin Only
* **`GET /api/users`** $\rightarrow$ Success (200): List user array tanpa field `password`.
* **`POST /api/users`**
  - **Body:** `name`, `email` (unique), `password` (min 6), `role` (`admin`\|`approver`), `approval_level` (wajib 1/2 jika `approver`; wajib null jika `admin`).
  - **Errors:** 422 (Aturan `approval_level` melanggar / email duplikat).
* **`PUT /api/users/(:num)`** $\rightarrow$ Body opsional (`name`, `email`, `password`, `role`, `approval_level`). Errors: 404 (User tak ditemukan), 422.
* **`DELETE /api/users/(:num)`** $\rightarrow$ Errors: 400 (Cannot delete your own account), 404.

---

### C. Master Data: Vehicles (`/api/vehicles`) & Drivers (`/api/drivers`) — Admin Only
> [!NOTE]
> Kedua controller ini mengembalikan error validasi 422 pada key `"data"` (bukan `"errors"`).

* **`GET /api/vehicles` & `GET /api/drivers`** $\rightarrow$ Support query parameter `?status=`.
* **`POST /api/vehicles`**
  - **Body:** `name`, `plate_number` (unique), `type` (`passenger`\|`cargo`), `ownership` (`own`\|`rental`), `region`, `status` (`available`\|`maintenance`\|`inactive`).
* **`POST /api/drivers`**
  - **Body:** `name`, `license_number`, `phone`, `status` (`active`\|`inactive`).
* **`PUT` & `DELETE`** $\rightarrow$ Mengikuti pola standar CRUD (Errors: 404 jika ID tidak ditemukan, 422 jika validasi gagal).

---

### D. Bookings (`/api/bookings`)

* **`GET /api/bookings`**
  - **Role:** Admin (melihat semua), Approver (otomatis di-filter via JOIN `booking_approvals` berdasarkan `approver_id`). Support query `?status=`.

* **`POST /api/bookings`**
  - **Role:** Admin only.
  - **Body:** `vehicle_id`, `driver_id`, `requester_name`, `purpose`, `destination`, `start_date` (`Y-m-d H:i:s`), `end_date`, `approver_level1_id`, `approver_level2_id`.
  - **Errors & Validasi:**
    - **422:** Urutan tanggal (`start_date >= end_date`), Kendaraan status !== `available`, Driver status !== `active`, Approver L1 role/level mismatch, Approver L2 role/level mismatch, Self-approval attempt.
    - **409 Conflict:** Bentrok jadwal / overlap kendaraan atau driver (`isVehicleAvailable()` / `isDriverAvailable()`).

* **`GET /api/bookings/(:num)`**
  - **Success (200):** Object booking lengkap dengan nested: `vehicle`, `driver`, `admin`, dan array `approvals` (`level`, `status`, `notes`, `acted_at`, `approver_name`).

* **`POST /api/bookings/(:num)/approve`**
  - **Role:** Approver only. Body: *Empty*.
  - **Errors:** 404 (Bukan approver booking ini), 422 (Sudah diproses / status !== `pending`), 422 (Bukan giliran approve: L2 mencoba approve saat status masih `waiting_level_1`).

* **`POST /api/bookings/(:num)/reject`**
  - **Role:** Approver only.
  - **Body:** `{ "notes": "..." }` (Wajib diisi).
  - **Errors:** 422 (Notes kosong), 422 (Booking sudah tidak dapat diproses).

---

### E. Dashboard, Reports, & Activity Logs

* **`GET /api/dashboard`**
  - **Role:** Admin & Approver.
  - **Admin Data:** `summary` (`total_vehicles`, `total_bookings_this_month`, `pending_approval`, `approved_this_month`), `bookings_per_month` (12 bulan), `status_distribution`, `top_vehicles` (top 5).
  - **Approver Data:** `summary.pending_for_me`, `pending_bookings` list.

* **`GET /api/reports` & `GET /api/reports/export`**
  - **Role:** Admin only.
  - **Params:** `start_date` (required), `end_date` (required), `status` (optional).
  - **Export Output:** Binary stream file `.xlsx`.

* **`GET /api/activity-logs`**
  - **Role:** Admin only.
  - **Params:** `entity_type`, `start_date`, `end_date`, `page` (paginasi 50 log per halaman).
