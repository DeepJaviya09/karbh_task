## Task Management App (Laravel + Vue)

Monorepo containing a Laravel API backend and a Vue.js SPA frontend.

Directories:
- `backend` – Laravel 10+ API (Sanctum auth, email verification, tasks, admin)
- `frontend` – Vue 3 app (Vue Router, Pinia, Axios)


### 1) Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+ and npm 9+
- Git
- SQLite (default for local dev) or MySQL/PostgreSQL (optional)


### 2) Clone and enter the project
```bash
git clone <your-repo-url> fsd
cd fsd
```


### 3) Backend Setup (Laravel)
```bash
cd backend

# Install dependencies
composer install

# Create .env
copy .env.example .env   # Windows
# or: cp .env.example .env  # macOS/Linux

# Generate app key
php artisan key:generate

# Create SQLite database file (default)
mkdir database 2> NUL
type NUL > database\database.sqlite

# Configure storage symlink (for file storage if needed)
php artisan storage:link
```

Update `.env` (minimum):
```
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

If using MySQL and the database/user do not exist yet, create them (example):

Then set in `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=task_manager
DB_USERNAME=task_user
DB_PASSWORD=strong_password_here
```


# Sanctum / Session for SPA auth
SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:3000

# Queue (email verification notifications use queues)
QUEUE_CONNECTION=database

# Mail (set your SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Task Manager"
```

Run migrations (and create tables for queues):
```bash
php artisan migrate
php artisan queue:table && php artisan migrate
```

Start the Laravel dev server (terminal A):
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Start the queue worker (terminal B):
```bash
cd backend
php artisan queue:work
```

Optional: Make an existing user admin (via Tinker):
```bash
php artisan tinker
>>> $u = App\Models\User::where('email', 'you@example.com')->first();
>>> $u->role = 'admin';
>>> $u->save();
```


### 4) Frontend Setup (Vue)
```bash
cd frontend

# Install dependencies
npm install

# Create environment file
copy .env.example .env 2> NUL
```

If there is no `.env.example`, create `.env` with:
```
VITE_API_BASE_URL=http://localhost:8000/api
```

Run the dev server on port 3000 (terminal C):
```bash
npm run dev -- --port 3000
```

Open the app: `http://localhost:3000`


### 5) Features Overview
- Email verification for non-admin signups (link points to frontend)
- Sanctum-based SPA authentication (login, logout, profile)
- Tasks CRUD with tags, status, due date
- Server-side search, sort, filter, pagination across all listing pages
- Admin can view users and view any user’s tasks
- Rate limiting on login


### 6) Admin UX
- Admin login redirects to `/users`
- Click a user card to view `/users/{id}/tasks`
- The old `/admin` page is removed


### 7) Email Verification Flow
- On signup (non-admin), a verification email is sent
- The verification link opens the frontend route `/verify-email?id={id}&token={token}`
- After verification, users can log in normally
- Verification email is only sent on signup (or explicit resend), not at every login

Resend verification:
```
POST http://localhost:8000/api/auth/resend-verification
{
  "email": "user@example.com"
}
```


### 8) Environment Reference
Backend `.env`:
- `APP_URL` must match the Laravel server URL
- `FRONTEND_URL` must match the Vue dev URL (default `http://localhost:3000`)
- `SANCTUM_STATEFUL_DOMAINS=localhost:3000`
- `SESSION_DOMAIN=localhost`
- Mail credentials per your SMTP provider

Frontend `.env`:
- `VITE_API_BASE_URL=http://localhost:8000/api`


### 9) Running Tests (Backend)
```bash
cd backend
php artisan test
```


### 10) Common Troubleshooting
- If you see CORS/auth issues, verify:
  - `SANCTUM_STATEFUL_DOMAINS=localhost:3000`
  - `SESSION_DOMAIN=localhost`
  - Browser cookies allowed for `localhost`
- Email not sending:
  - Check queue worker is running
  - Verify `MAIL_*` env settings
  - Check `storage/logs/laravel.log`
- “Cannot find path '.../backend'” when changing directories:
  - Ensure you run `cd backend` from project root (`fsd`), not already inside `backend`


### 11) Development Workflow (Recommended)
- Terminal A: Laravel server (`backend`): `php artisan serve --host=0.0.0.0 --port=8000`
- Terminal B: Queue worker (`backend`): `php artisan queue:work`
- Terminal C: Vue dev server (`frontend`): `npm run dev -- --port 3000`


### 12) Notes
- Default DB is SQLite; switch to MySQL/PostgreSQL by updating `.env` and running migrations
- Pagination, searching, sorting, and filtering are handled server-side across all list pages
- Overdue tasks are highlighted on the UI


### 13) API Base URLs
- Backend base: `http://localhost:8000`
- API base: `http://localhost:8000/api`
- Frontend base: `http://localhost:3000`


### 14) Production (High-Level)
- Configure a web server (Nginx/Apache) for Laravel and serve the built frontend
- Set proper `APP_URL`, `FRONTEND_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`
- Use a real database (MySQL/PostgreSQL)
- Run `php artisan migrate --force`
- Run queue workers under a supervisor (e.g., systemd, Supervisord)
- Build frontend: `cd frontend && npm ci && npm run build`
- Point web server to `frontend/dist` for the SPA and proxy API to Laravel


