# School Management REST API

A small dependency-free PHP 8.2 REST API using PDO, MySQL, JWT authentication, and role-based access control.

## Local URL

With the local PHP server running:

```text
http://127.0.0.1:8080/schoolmanagement/api
```

All request and response bodies are JSON. Send authenticated requests with:

```text
Authorization: Bearer <token>
Content-Type: application/json
```

## Setup

Database defaults are already configured for `127.0.0.1:3306`, user `root`, password `0000`, and database `school_management`. Override them with `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, and `DB_PASSWORD`, or in the ignored `config/local.php` file.

Run setup from this directory using XAMPP PHP:

```powershell
C:\xampp\php\php.exe bin\setup.php admin "a-long-unique-password"
```

The command creates the database and tables, seeds the three roles, and creates or resets the named admin account. Keep `config/local.php` private and replace its JWT secret before deploying elsewhere.

## Authentication

`POST /api/auth/login`

```json
{
  "username": "admin",
  "password": "your-password",
  "role": "admin"
}
```

The `role` may be omitted when the account has exactly one role. Multi-role accounts must select one assigned role. Other authenticated endpoints:

- `GET /api/auth/me`
- `POST /api/auth/change-password` with `current_password` and `new_password`

## Admin API

Admin resources support `GET` and `POST` on the collection and `GET`, `PATCH`, and `DELETE` on `/{id}`:

```text
/api/admin/academic-years
/api/admin/standards
/api/admin/subjects
/api/admin/batches
/api/admin/standard-subjects
/api/admin/students
/api/admin/teachers
/api/admin/enrollments
/api/admin/teacher-subjects
/api/admin/lecture-slots
/api/admin/timetable
/api/admin/standard-fees
/api/admin/fee-payments
```

Lecture operations use dedicated endpoints:

- `GET /api/admin/lecture-changes?batch_id=1&date=2026-09-28`
- `POST /api/admin/lecture-changes` assigns a proxy or subject adjustment to one dated lecture
- `DELETE /api/admin/lecture-changes/{id}` removes an active change before attendance opens
- `GET /api/admin/attendance/{sessionId}` returns attendance with its audit history
- `PATCH /api/admin/attendance/{sessionId}` corrects records and requires `correction_reason`

User accounts use `/api/admin/users`. Create one with:

```json
{
  "username": "teacher1",
  "password": "a-long-unique-password",
  "roles": ["teacher"],
  "is_active": true
}
```

Create the corresponding teacher or student profile afterward, using the returned user `id` as `user_id`. Collection GET requests accept `limit` (maximum 200) and `offset`.

## Teacher API

- `GET /api/teacher/schedule`
- `GET /api/teacher/students?batch_id=1` only returns students in a batch where that teacher has a scheduled lecture
- `GET /api/teacher/attendance/lectures?date=2026-09-28`
- `GET /api/teacher/attendance/roster?timetable_entry_id=1&date=2026-09-28`
- `POST /api/teacher/attendance` saves a draft
- `POST /api/teacher/attendance/submit` submits and locks the complete roster

## Student API

- `GET /api/student/profile`
- `GET /api/student/enrollments`
- `GET /api/student/fees`

The fee response calculates payable, paid, and balance values per enrollment.

## Design Notes

The schema keeps `academic_year_id` in enrollments and timetable entries. Since the year also comes from the selected batch, database triggers reject mismatches. Teacher-subject mappings define eligibility, while timetable entries are the recurring weekly plan.

`lecture_occurrences` identifies one batch/date/slot. `lecture_changes` independently records a proxy or subject adjustment for that occurrence. Attendance uses separate session, record, and append-only history tables. Enrollment start and end dates determine the roster, and database triggers reject attendance for a student who was not enrolled in that batch on the lecture date. Marker, submitter, locker, latest editor, and administrative corrections are all attributed to authenticated user IDs.
