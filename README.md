# Current Affairs — Web (API + Admin)

Laravel API + React admin CMS.

Repos:
- https://github.com/whogauravyadav/ca-app-web

## Structure

```
backend/          (this repo root)
├── app/          Laravel
├── admin/        React + Vite admin CMS
├── routes/
└── ...
```

## Quick start

### API

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=4402
```

### Admin CMS

```bash
cd admin
npm install
npm run dev   # http://127.0.0.1:4401 (proxies /api → :4402)
```

**Admin login (after seed):** `admin@currentaffairs.app` / `password`

## Env highlights

See `.env.example` for DB + Ktatva Storage:

```
KTATVA_STORAGE_API_KEY=
KTATVA_STORAGE_BUCKET_ID=
KTATVA_STORAGE_BASE_URL=https://storage.ktatva.com/api/v1/storage
```

Never commit `.env`.
