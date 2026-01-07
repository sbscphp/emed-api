# eMed API - Project Documentation

## 1. PROJECT OVERVIEW

**Project Name:** eMed API  
**Purpose:** A comprehensive E-Medical Records System (EMR) designed to manage hospital operations including patient records, consultations, pharmacy, laboratory, radiology, billing, and inventory.  
**Architecture:** RESTful API-first application using the Service-Repository pattern with Multi-tenancy support.

### Technology Stack
- **Framework:** Laravel 11.31
- **Language:** PHP ^8.2
- **Database:** MySQL
- **Authentication:** JWT (JSON Web Tokens) via `tymon/jwt-auth`
- **Multi-tenancy:** `spatie/laravel-multitenancy` & `stancl/tenancy`
- **Key Packages:**
  - `barryvdh/laravel-dompdf`: PDF generation
  - `maatwebsite/excel`: Excel export/import
  - `santigarcor/laratrust`: Role-based access control (RBAC)
  - `cloudinary-labs/cloudinary-laravel`: File storage
  - `hidehalo/nanoid-php`: Unique ID generation

### Target Audience
- Hospital Administrators
- Doctors / Consultants
- Pharmacists
- Laboratory Technicians
- Radiologists
- Receptionists / Record Officers

---

## 2. INSTALLATION & SETUP

### Prerequisites
- PHP >= 8.2
- Composer
- Node.js & NPM (for asset compilation if needed)
- MySQL Database

### Installation Steps

1.  **Clone the Repository**
    ```bash
    git clone <repository_url>
    cd emed-api
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    npm install
    ```

3.  **Environment Configuration**
    Copy the example environment file and update variables.
    ```bash
    cp .env.example .env
    ```
    
    **Key `.env` Variables:**
    - `APP_URL`: Base URL of the API.
    - `DB_Connection`: Database credentials.
    - `JWT_SECRET`: Secret key for JWT auth.
    - `CLOUDINARY_*`: Cloudinary credentials for file uploads.
    - `PAYSTACK_SECRET_KEY`: Payment gateway key.

4.  **Generate Keys**
    ```bash
    php artisan key:generate
    php artisan jwt:secret
    ```

5.  **Database Setup**
    Create a database (e.g., `emed`) and run migrations.
    ```bash
    php artisan migrate
    ```
    *Note: Since this is a multi-tenant app, specific tenant migrations may apply.*

6.  **Run Local Server**
    ```bash
    php artisan serve
    # Starts server at http://127.0.0.1:8000
    ```

---

## 3. PROJECT STRUCTURE

The project follows a modular **Service-Repository Pattern** to ensure separation of concerns.

### Key Directories

| Directory | Purpose |
|-----------|---------|
| `app/Http/Controllers/v1` | API Controllers grouped by module (Admin, Auth, etc.). |
| `app/Models` | Eloquent Models representing database entities. |
| `app/Services` | deeply nested business logic, decoupled from controllers. |
| `app/Repositories` | Data access layer used by Services to interact with the database. |
| `app/Enums` | Enumerations for constant values (Roles, Statuses). |
| `app/Exports` | Logic for exporting data (Excel/CSV). |
| `database/migrations` | Database schema definitions, separated into `landlord` and `tenant`. |
| `routes/api.php` | API route definitions. |

---

## 4. FEATURES & MODULES

### Core Modules

#### **1. Authentication & User Management**
- **Purpose:** Secure login and role management.
- **Routes:** `POST /api/v1/auth/login`, `/api/v1/admin/users/*`
- **Key Classes:** `AuthController`, `UserController`, `RoleController`
- **Logic:** `Validation`, `User Creation`, `Role Assignment`.

#### **2. Patient Records**
- **Purpose:** Manage patient demographics and visit history.
- **Routes:** `/api/v1/admin/record/patient/*`
- **Key Models:** `Patient`, `PatientVisit`, `NextOfKin`
- **Service:** `RecordManagementService` (implied name)

#### **3. Consultation**
- **Purpose:** Doctors document diagnosis, treatment, and prescriptions.
- **Routes:** `/api/v1/admin/consultation/*`
- **Key Models:** `Consultation`, `Treatment`, `MedicalHistory`
- **Features:** History taking, Vitals review, Diagnosis.

#### **4. Pharmacy & Inventory**
- **Purpose:** Drug dispensing and inventory tracking.
- **Routes:** `/api/v1/admin/pharmacy/*`, `/api/v1/admin/medicine/*`
- **Key Models:** `Pharmacy`, `Medication`, `Inventory`
- **Features:** Stock levels, Supplier management, Prescription fulfillment.

#### **5. Laboratory & Radiology**
- **Purpose:** Diagnostic test management.
- **Routes:** `/api/v1/admin/laboratory/*`, `/api/v1/admin/radiology/*`
- **Key Models:** `Laboratory`, `LaboratoryResult`, `Radiology`
- **Features:** Test ordering, Result entry, Status tracking.

#### **6. Billing**
- **Purpose:** Invoicing and payments.
- **Routes:** `/api/v1/admin/billing/*`
- **Sub-modules:** Service setup, Invoice generation, Payment processing (Paystack).

---

## 5. DATABASE SCHEMA

### Key Tables
- **tenants**: Stores tenant information for multi-tenancy.
- **users**: System users (Admin, Doctors, etc.).
- **patients**: Patient profiles.
- **consultations**: Records of doctor-patient interactions.
- **medications**: Drug catalog.
- **pharmacies**: Pharmacy transaction records.
- **laboratories**: Lab test catalog and records.
- **radiologies**: Radiology test catalog and records.
- **billings**: Financial records.

### Relationships
- `Patient` **hasMany** `PatientVisit`
- `PatientVisit` **hasOne** `Consultation`
- `Consultation` **morphsMany** `Prescription` (or similar polymorphic relations).

---

## 6. AUTHENTICATION & AUTHORIZATION

- **System:** JWT Auth (`tymon/jwt-auth`).
- **Guards:** `api` (JWT), `web` (Session - less used).
- **Middleware:** 
  - `auth:api`: Ensures valid token.
  - `tenant`: Scopes request to the current tenant.
  - `role:{role_name}`: Laratrust middleware for permissions.
- **Roles:** Super Admin, Admin, Doctor, Pharmacist, Lab Technician, Nurse.

---

## 7. API DOCUMENTATION

**Base URL:** `http://localhost:8000/api/v1`

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/login` | User login | No |
| POST | `/admin/register` | Register new tenant/admin | No |
| GET | `/admin/me` | Get current user profile | Yes |
| GET | `/admin/record/patient` | List patients | Yes |
| POST | `/admin/record/patient/create` | Create a patient | Yes |
| GET | `/admin/pharmacy/lists` | List pharmacy orders | Yes |

*Note: All authorized requests must include header `Authorization: Bearer <token>`.*

---

## 8. KEY SERVICES & COMPONENTS

### Custom Services (`app/Services`)
Business logic is encapsulated in service classes to keep controllers thin.
- **Example:** `PatientService` handles creating patients and generating unique IDs.

### Events & Listeners
Used for decoupled actions like sending emails after registration.

### File Storage
- **Driver:** Cloudinary (configured in `.env`).
- **Usage:** Uploading patient files, profile pictures, scan results.

---

## 9. TESTING

- **Framework:** PHPUnit (standard in Laravel 11).
- **Location:** `tests/Feature` and `tests/Unit`.
- **Running Tests:**
  ```bash
  php artisan test
  ```

---

## 10. DEPLOYMENT

1.  **Server Requirements:** Nginx/Apache, PHP 8.2+, MySQL 8.0+.
2.  **Environment:** Set `APP_ENV=production`, `APP_DEBUG=false`.
3.  **Optimization:**
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
4.  **Queue Worker:**
    Ensure a supervisor process runs the queue for emails/jobs.
    ```bash
    php artisan queue:listen --tries=3
    ```

---

## 11. TROUBLESHOOTING

- **500 Internal Server Error:** Check `storage/logs/laravel.log`.
- **JWT Error (Token Invalid):** Ensure `JWT_SECRET` matches across environments or clear cache.
- **Class Not Found:** Run `composer dump-autoload`.
- **Permission Denied:** Ensure `storage` and `bootstrap/cache` are writable.

---

## 12. COMMON TASKS

### Adding a New API Endpoint
1.  Define route in `routes/api.php`.
2.  Create Controller: `php artisan make:controller v1/Admin/NewFeatureController`.
3.  Create Service/Repository (optional but recommended).
4.  Implement logic and return `JsonResponser`.

### Running Migrations
```bash
php artisan migrate
```
For tenant-specific migrations:
```bash
php artisan tenants:migrate
```
