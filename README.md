# E-Learning Starter (Nuxt + Laravel + Docker)

Starter LMS fullstack dengan UI modern dan alur belajar end-to-end:
- katalog course publik,
- detail course + beli langsung (tanpa keranjang),
- pembayaran + riwayat transaksi,
- profil akun (upload foto + ganti password),
- dashboard admin/mentor/student.

## Stack

- Frontend: Nuxt 4 (Vue 3 + TypeScript)
- Backend: Laravel 13 (REST API + Sanctum)
- Database: PostgreSQL 17
- Cache/Queue/Session: Redis 7
- Reverse proxy: Nginx
- Orkestrasi: Docker Compose

## Struktur Folder

```text
.
|- frontend/            # Nuxt app (UI, routing, middleware auth/role)
|- backend/             # Laravel API, migration, seeder
|- docker/
|  |- nginx/default.conf
|- docker-compose.yml
```

## Menjalankan Project

1. Build dan jalankan semua service:

```bash
docker compose up -d --build
```

2. Buka aplikasi:

- Frontend: http://localhost:3000
- Backend API (via Nginx): http://localhost:8080
- Health check API: http://localhost:8080/api/health

3. Hentikan service:

```bash
docker compose down
```

Jika perlu reset data PostgreSQL + Redis:

```bash
docker compose down -v
```

## URL Penting

- Home: http://localhost:3000/
- Login: http://localhost:3000/login
- Katalog course: http://localhost:3000/courses
- Pembayaran: http://localhost:3000/payments
- Profil akun: http://localhost:3000/profile
- Dashboard umum (redirect sesuai role): http://localhost:3000/dashboard
- Student view: http://localhost:3000/student
- Mentor studio: http://localhost:3000/mentor
- Admin dashboard: http://localhost:3000/admin
- Admin users: http://localhost:3000/admin/users
- Admin vouchers: http://localhost:3000/admin/vouchers

## Akun Dummy

| Role | Email | Password | Default Path |
| --- | --- | --- | --- |
| Admin | `admin@elearning.local` | `password123` | `/admin` |
| Mentor | `mentor@elearning.local` | `password123` | `/mentor` |
| Student | `student@elearning.local` | `password123` | `/student` |

Reset user dummy:

```bash
docker compose exec -T backend php artisan db:seed --class=UserSeeder --force
```

Reset seluruh data dummy (user, course, kurikulum, voucher):

```bash
docker compose exec -T backend php artisan db:seed --force
```

## Fitur Aktif

### Guest
- Lihat Home + preview katalog.
- Buka halaman katalog `/courses` dengan:
  - search keyword,
  - filter level,
  - filter kategori,
  - sorting (rating/judul).
- Buka detail course `/courses/[slug]` (trailer, tools, review, kurikulum accordion).

### Authenticated User (Student/Mentor/Admin)
- Login/logout berbasis token Laravel Sanctum.
- Route protection frontend (`auth` + `role` middleware).
- Beli kelas langsung dari detail course (tanpa keranjang).
- Buat transaksi di `/payments`, pakai voucher, dan bayar transaksi pending.
- Lihat riwayat transaksi.
- Kelola profil di `/profile`:
  - upload avatar (jpg/png/webp max 3 MB),
  - pakai preset avatar,
  - ubah nama,
  - ganti password (otomatis login ulang).

### Admin
- Kelola course (CRUD).
- Kelola user:
  - create user,
  - ubah role/status,
  - reset password user.
- Kelola voucher promo:
  - nama promo, kode, harga promo,
  - periode aktif,
  - pilih course mana saja yang dapat promo.

### Mentor
- Mentor Studio untuk course yang diampu:
  - update informasi course,
  - upload trailer video,
  - CRUD modul,
  - CRUD lesson.

## Catatan Pembayaran

Integrasi pembayaran saat ini menggunakan flow internal/simulasi transaksi (`pending` -> `paid`) untuk kebutuhan development.
Belum terhubung ke payment gateway eksternal produksi.

## Endpoint API

### Public
- `GET /api/health`
- `POST /api/login`
- `GET /api/courses`
- `GET /api/courses/{slug}`

### Authenticated (`auth:sanctum`)
- `GET /api/me`
- `POST /api/logout`
- `PATCH /api/me/profile`
- `POST /api/me/avatar`
- `POST /api/me/password`

### Student/Mentor/Admin
- `GET /api/student/dashboard`
- `GET /api/student/transactions`
- `POST /api/student/transactions/checkout`
- `POST /api/student/transactions/{transaction}/pay`

### Mentor/Admin
- `GET /api/mentor/dashboard`
- `GET /api/mentor/courses/{course}`
- `PUT /api/mentor/courses/{course}`
- `POST /api/mentor/courses/{course}/trailer/upload`
- `POST /api/mentor/courses/{course}/modules`
- `PUT /api/mentor/modules/{module}`
- `DELETE /api/mentor/modules/{module}`
- `POST /api/mentor/modules/{module}/lessons`
- `PUT /api/mentor/lessons/{lesson}`
- `DELETE /api/mentor/lessons/{lesson}`

### Admin
- `GET /api/admin/dashboard`
- `GET /api/admin/courses`
- `POST /api/admin/courses`
- `PUT /api/admin/courses/{course}`
- `DELETE /api/admin/courses/{course}`
- `GET /api/admin/users`
- `POST /api/admin/users`
- `PATCH /api/admin/users/{user}/role`
- `POST /api/admin/users/{user}/reset-password`
- `GET /api/admin/vouchers`
- `POST /api/admin/vouchers`
- `PUT /api/admin/vouchers/{voucher}`
- `DELETE /api/admin/vouchers/{voucher}`

## Command Berguna

Cek status container:

```bash
docker compose ps
```

Jalankan migration manual:

```bash
docker compose exec -T backend php artisan migrate --force
```

Build frontend production:

```bash
docker compose exec -T frontend npm run build
```

Smoke test login:

```bash
docker compose exec -T backend php artisan test --filter=AuthSmokeTest
```

## Catatan Startup Backend

Saat container backend start (`backend/docker/start.sh`), sistem akan otomatis:
- copy `.env` jika belum ada,
- install dependency Composer jika belum ada,
- generate `APP_KEY` jika kosong,
- menunggu PostgreSQL ready,
- menjalankan migrate + seed otomatis.
