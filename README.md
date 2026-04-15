# E-Learning Starter (Nuxt + Laravel + Docker)

Starter LMS yang mudah dipakai, terpisah FE/BE, dan siap untuk pengembangan jangka panjang.

## Stack

- Frontend: Nuxt 4 (Vue 3 + TypeScript)
- Backend: Laravel 13 (API)
- Database: PostgreSQL 17
- Cache/Queue: Redis 7
- Reverse Proxy: Nginx
- Orkestrasi: Docker Compose

## Struktur Folder

```text
.
|- frontend/            # Nuxt app (responsive UI starter)
|- backend/             # Laravel API
|- docker/
|  |- nginx/default.conf
|- docker-compose.yml
```

## Cara Menjalankan

1. Build dan jalankan semua service:

```bash
docker compose up -d --build
```

2. Buka aplikasi:

- Frontend: http://localhost:3000
- Backend (Laravel via Nginx): http://localhost:8080
- Login dashboard: http://localhost:3000/login
- User dashboard: http://localhost:3000/dashboard
- Student view (front end): http://localhost:3000/student
- Admin view (back end): http://localhost:3000/admin
- Admin role management: http://localhost:3000/admin/users
- Health endpoint: http://localhost:8080/api/health

3. Hentikan service:

```bash
docker compose down
```

Jika ingin sekaligus menghapus volume database/redis:

```bash
docker compose down -v
```

## Akun Dummy Login

| Role | Email | Password | Dashboard Default |
| --- | --- | --- | --- |
| Admin | `admin@elearning.local` | `password123` | `/admin` |
| Mentor | `mentor@elearning.local` | `password123` | `/mentor` |
| Student | `student@elearning.local` | `password123` | `/student` |

Jika akun dummy pernah berubah, reset user dummy dengan:

```bash
docker compose exec -T backend php artisan db:seed --class=UserSeeder --force
```

Jika data tampilan (course/modul/lesson) kosong, reset seluruh dummy data dengan:

```bash
docker compose exec -T backend php artisan db:seed --force
```

## Fitur Yang Sudah Aktif

- Login/logout berbasis token (Laravel Sanctum).
- Route protection di frontend berdasarkan login + role.
- Dashboard student dummy (protected).
- Dashboard admin dummy (protected).
- CRUD Course (create, read, update, delete) di dashboard admin.
- Role management user (admin/mentor/student + active/inactive) di dashboard admin.
- Dummy image lokal di `frontend/public/images` dipakai untuk UI home/student/admin.

## Catatan Pengembangan

- Frontend membaca API base URL dari env container: `NUXT_PUBLIC_API_BASE`.
- Backend default env diarahkan ke PostgreSQL + Redis dalam Docker network.
- Saat container backend pertama kali hidup, script `backend/docker/start.sh` akan:
  - memastikan `.env` tersedia,
  - install dependency Composer bila belum ada,
  - generate `APP_KEY` bila kosong,
  - menjalankan migrasi otomatis,
  - menjalankan seeder otomatis.

### Smoke Test Login

Jalankan smoke test untuk validasi login dummy account + akses dashboard role:

```bash
docker compose exec -T backend php artisan test --filter=AuthSmokeTest
```

## Endpoint API

- `GET /api/health`
- `POST /api/login`
- `GET /api/me` (auth:sanctum)
- `POST /api/logout` (auth:sanctum)
- `GET /api/courses` (public, published courses)
- `GET /api/student/dashboard` (auth:sanctum + role: student/mentor/admin)
- `GET /api/admin/dashboard` (auth:sanctum + role: admin)
- `GET /api/admin/courses` (auth:sanctum + role: admin)
- `POST /api/admin/courses` (auth:sanctum + role: admin)
- `PUT /api/admin/courses/{course}` (auth:sanctum + role: admin)
- `DELETE /api/admin/courses/{course}` (auth:sanctum + role: admin)
- `GET /api/admin/users` (auth:sanctum + role: admin)
- `PATCH /api/admin/users/{user}/role` (auth:sanctum + role: admin)
# my-elearning
