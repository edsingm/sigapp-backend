# Departments & Positions

## Overview

Tenant users must belong to a **Department** and a **Position**. Both fields are required when creating a user. Positions carry a numeric `level` field used in approval flows: lower level = higher hierarchy (e.g., level 1 = Director, level 5 = Analyst).

---

## Database

### `departments`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(150) | required |
| `description` | text | nullable |
| `active` | boolean | default `true` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `positions`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(150) | required |
| `description` | text | nullable |
| `level` | smallint unsigned | required, min 1 — lower = higher hierarchy |
| `active` | boolean | default `true` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `users` (additions)

| Column | Type | Notes |
|---|---|---|
| `department_id` | FK → departments | nullable on delete |
| `position_id` | FK → positions | nullable on delete |

---

## Architecture

```
Request → Controller → Service → Repository → Model → Resource
```

### Files

| Layer | Department | Position |
|---|---|---|
| Model | `app/Models/Tenant/Department.php` | `app/Models/Tenant/Position.php` |
| Repository | `app/Repositories/Tenant/DepartmentRepository.php` | `app/Repositories/Tenant/PositionRepository.php` |
| Service | `app/Services/Tenant/DepartmentService.php` | `app/Services/Tenant/PositionService.php` |
| Controller | `app/Http/Controllers/Api/V1/Tenant/Admin/DepartmentController.php` | `app/Http/Controllers/Api/V1/Tenant/Admin/PositionController.php` |
| Store Request | `app/Http/Requests/Tenant/StoreDepartmentRequest.php` | `app/Http/Requests/Tenant/StorePositionRequest.php` |
| Update Request | `app/Http/Requests/Tenant/UpdateDepartmentRequest.php` | `app/Http/Requests/Tenant/UpdatePositionRequest.php` |
| Resource | `app/Http/Resources/Tenant/DepartmentResource.php` | `app/Http/Resources/Tenant/PositionResource.php` |

### Migrations (tenant)

```
database/migrations/tenant/2026_04_04_000001_create_departments_table.php
database/migrations/tenant/2026_04_04_000002_create_positions_table.php
database/migrations/tenant/2026_04_04_000003_add_department_position_to_users_table.php
```

---

## API Endpoints

All routes are under the `tenant.admin` middleware group, prefixed `/api/v1/tenant-admin/`.

### Departments

| Method | URI | Action | Route name |
|---|---|---|---|
| GET | `/departments` | List (paginated) | `tenant-admin.departments.index` |
| POST | `/departments` | Create | `tenant-admin.departments.store` |
| GET | `/departments/{id}` | Show | `tenant-admin.departments.show` |
| PUT/PATCH | `/departments/{id}` | Update | `tenant-admin.departments.update` |
| DELETE | `/departments/{id}` | Delete | `tenant-admin.departments.destroy` |
| GET | `/departments/select` | List active (for dropdowns) | `tenant-admin.departments.select` |

### Positions

| Method | URI | Action | Route name |
|---|---|---|---|
| GET | `/positions` | List (paginated) | `tenant-admin.positions.index` |
| POST | `/positions` | Create | `tenant-admin.positions.store` |
| GET | `/positions/{id}` | Show | `tenant-admin.positions.show` |
| PUT/PATCH | `/positions/{id}` | Update | `tenant-admin.positions.update` |
| DELETE | `/positions/{id}` | Delete | `tenant-admin.positions.destroy` |
| GET | `/positions/select` | List active (for dropdowns) | `tenant-admin.positions.select` |

---

## Payloads

### Store Department
```json
{
    "name": "Engineering",
    "description": "Engineering department",
    "active": true
}
```

### Store Position
```json
{
    "name": "Director",
    "description": "Top-level director",
    "level": 1,
    "active": true
}
```

### Store User (additions)
```json
{
    "department_id": 1,
    "position_id": 2
}
```

---

## Hierarchy & Approval Flows

Positions use the `level` field to represent hierarchy. A **lower level number means higher authority** (e.g., CEO = 1, Manager = 2, Analyst = 5).

The `PositionService::findApproversAbove(Position $position)` method returns all active positions with a level strictly lower than the given position — useful for routing approval requests to the correct authority.

```php
// Returns positions with level < $position->level, ordered ascending
$approvers = $positionService->findApproversAbove($currentUserPosition);
```

---

## Validation

- `department_id` and `position_id` are **required** on user create (`StoreUserRequest`).
- Both are validated with `exists:departments,id` / `exists:positions,id`.
- A department or position **cannot be deleted** while it has users assigned (`DEPARTMENT_IN_USE` / `POSITION_IN_USE` error code, HTTP 422).

---

## Tests

```
tests/Feature/Tenant/Admin/DepartmentTest.php
tests/Feature/Tenant/Admin/PositionTest.php
tests/Feature/Tenant/Admin/UserManagementWithDepartmentPositionTest.php
```

Covers: list, create (valid + invalid), show, show 404, update, delete (with and without users), select endpoint, user creation with department/position, validation failures.
