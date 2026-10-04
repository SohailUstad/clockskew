<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Api
{
    private const RESOURCES = [
        'academic-years' => ['table' => 'academic_years', 'fields' => ['name', 'start_date', 'end_date', 'is_active'], 'required' => ['name', 'start_date', 'end_date']],
        'standards' => ['table' => 'standards', 'fields' => ['name'], 'required' => ['name']],
        'batches' => ['table' => 'batches', 'fields' => ['standard_id', 'academic_year_id', 'name'], 'required' => ['standard_id', 'academic_year_id', 'name']],
        'subjects' => ['table' => 'subjects', 'fields' => ['name', 'code'], 'required' => ['name', 'code']],
        'standard-subjects' => ['table' => 'standard_subjects', 'fields' => ['standard_id', 'subject_id'], 'required' => ['standard_id', 'subject_id']],
        'teacher-subjects' => ['table' => 'teacher_subjects', 'fields' => ['teacher_id', 'subject_id'], 'required' => ['teacher_id', 'subject_id']],
        'lecture-slots' => ['table' => 'lecture_slots', 'fields' => ['name', 'start_time', 'end_time', 'slot_order', 'is_teaching_slot', 'is_active'], 'required' => ['name', 'start_time', 'end_time', 'slot_order']],
        'enrollments' => ['table' => 'enrollments', 'fields' => ['student_id', 'batch_id', 'academic_year_id', 'enrollment_date', 'end_date', 'status'], 'required' => ['student_id', 'batch_id', 'academic_year_id', 'enrollment_date']],
        'standard-fees' => ['table' => 'standard_fees', 'fields' => ['standard_id', 'academic_year_id', 'total_amount_payable'], 'required' => ['standard_id', 'academic_year_id', 'total_amount_payable']],
        'fee-payments' => ['table' => 'fee_payments', 'fields' => ['enrollment_id', 'amount', 'payment_date', 'payment_method', 'reference_no', 'receipt_no', 'notes'], 'required' => ['enrollment_id', 'amount', 'payment_date', 'payment_method', 'receipt_no']],
    ];

    public function __construct(
        private readonly PDO $db,
        private readonly Jwt $jwt,
        private readonly Request $request
    ) {
    }

    public function dispatch(): never
    {
        $method = $this->request->method;
        $path = $this->request->path;

        if ($method === 'GET' && ($path === '/' || $path === '/api')) {
            Response::json(['name' => 'School Management API', 'status' => 'ok']);
        }
        if ($method === 'POST' && $path === '/api/auth/login') {
            $this->login();
        }

        $user = $this->authenticate();
        if ($method === 'GET' && $path === '/api/auth/me') {
            Response::json(['data' => $user]);
        }
        if ($method === 'POST' && $path === '/api/auth/change-password') {
            $this->changePassword($user);
        }

        if (preg_match('#^/api/admin/users(?:/(\d+))?$#', $path, $matches)) {
            $this->requireRole($user, ['admin']);
            $this->users(isset($matches[1]) ? (int) $matches[1] : null);
        }
        if (preg_match('#^/api/admin/lecture-changes(?:/(\d+))?$#', $path, $matches)) {
            $this->requireRole($user, ['admin']);
            $this->lectureChanges($user, isset($matches[1]) ? (int) $matches[1] : null);
        }
        if (preg_match('#^/api/admin/attendance/(\d+)$#', $path, $matches)) {
            $this->requireRole($user, ['admin']);
            $this->adminAttendance($user, (int) $matches[1]);
        }
        if ($method === 'GET' && $path === '/api/admin/attendance-reports') {
            $this->requireRole($user, ['admin']);
            $this->adminAttendanceReports();
        }
        if ($method === 'GET' && $path === '/api/admin/lecture-attendance') {
            $this->requireRole($user, ['admin']);
            $this->adminLectureAttendance();
        }
        if ($method === 'GET' && $path === '/api/admin/homework') {
            $this->requireRole($user, ['admin']);
            $this->adminHomework();
        }
        if (preg_match('#^/api/admin/([a-z-]+)(?:/(\d+))?$#', $path, $matches)) {
            $this->requireRole($user, ['admin']);
            $this->resource($matches[1], isset($matches[2]) ? (int) $matches[2] : null);
        }

        if ($method === 'GET' && $path === '/api/teacher/schedule') {
            $this->requireRole($user, ['teacher']);
            $this->teacherSchedule($user);
        }
        if ($method === 'GET' && $path === '/api/teacher/students') {
            $this->requireRole($user, ['teacher']);
            $this->teacherStudents($user);
        }
        if ($method === 'GET' && $path === '/api/teacher/teaching-scope') {
            $this->requireRole($user, ['teacher']);
            $this->teacherTeachingScope($user);
        }
        if (preg_match('#^/api/teacher/homework/(\d+)/submissions(?:/(\d+))?$#', $path, $matches)) {
            $this->requireRole($user, ['teacher']);
            $this->teacherHomeworkSubmissions($user, (int) $matches[1], isset($matches[2]) ? (int) $matches[2] : null);
        }
        if (preg_match('#^/api/teacher/homework(?:/(\d+))?$#', $path, $matches)) {
            $this->requireRole($user, ['teacher']);
            $this->teacherHomework($user, isset($matches[1]) ? (int) $matches[1] : null);
        }
        if ($method === 'GET' && $path === '/api/teacher/attendance/lectures') {
            $this->requireRole($user, ['teacher']);
            $this->teacherAttendanceLectures($user);
        }
        if ($method === 'GET' && $path === '/api/teacher/attendance/history') {
            $this->requireRole($user, ['teacher']);
            $this->teacherAttendanceHistory($user);
        }
        if ($method === 'GET' && $path === '/api/teacher/attendance/roster') {
            $this->requireRole($user, ['teacher']);
            $this->teacherAttendanceRoster($user);
        }
        if ($method === 'POST' && ($path === '/api/teacher/attendance' || $path === '/api/teacher/attendance/submit')) {
            $this->requireRole($user, ['teacher']);
            $this->saveTeacherAttendance($user, str_ends_with($path, '/submit'));
        }
        if ($method === 'GET' && $path === '/api/student/profile') {
            $this->requireRole($user, ['student']);
            $this->studentProfile($user);
        }
        if ($method === 'GET' && $path === '/api/student/enrollments') {
            $this->requireRole($user, ['student']);
            $this->studentEnrollments($user);
        }
        if ($method === 'GET' && $path === '/api/student/fees') {
            $this->requireRole($user, ['student']);
            $this->studentFees($user);
        }
        if (preg_match('#^/api/student/homework/(\d+)/submit$#', $path, $matches)) {
            $this->requireRole($user, ['student']);
            $this->studentHomeworkSubmit($user, (int) $matches[1]);
        }
        if (preg_match('#^/api/student/homework(?:/(\d+))?$#', $path, $matches)) {
            $this->requireRole($user, ['student']);
            $this->studentHomework($user, isset($matches[1]) ? (int) $matches[1] : null);
        }

        throw new ApiException(404, 'Endpoint not found.');
    }

    private function login(): never
    {
        $data = $this->request->json();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $requestedRole = strtolower(trim((string) ($data['role'] ?? '')));
        if ($username === '' || $password === '') {
            throw new ApiException(422, 'Username and password are required.');
        }

        $statement = $this->db->prepare('SELECT id, username, password_hash, is_active FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$username]);
        $user = $statement->fetch();
        if (!$user || !(bool) $user['is_active'] || !password_verify($password, $user['password_hash'])) {
            usleep(250000);
            throw new ApiException(401, 'Invalid username or password.');
        }

        $rolesStatement = $this->db->prepare('SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.name');
        $rolesStatement->execute([$user['id']]);
        $roles = $rolesStatement->fetchAll(PDO::FETCH_COLUMN);
        if ($roles === []) {
            throw new ApiException(403, 'No role is assigned to this account.');
        }
        $role = $requestedRole !== '' ? $requestedRole : (count($roles) === 1 ? $roles[0] : '');
        if ($role === '' || !in_array($role, $roles, true)) {
            throw new ApiException(422, 'Select one of the roles assigned to this account.', ['roles' => $roles]);
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        Response::json([
            'token' => $this->jwt->issue((int) $user['id'], $user['username'], $role),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $role, 'roles' => $roles],
        ]);
    }

    private function authenticate(): array
    {
        $claims = $this->jwt->verify($this->request->bearerToken());
        $statement = $this->db->prepare(
            'SELECT u.id, u.username, r.name AS role FROM users u '
            . 'INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id '
            . 'WHERE u.id = ? AND u.is_active = 1 AND r.name = ? LIMIT 1'
        );
        $statement->execute([(int) $claims['sub'], (string) $claims['role']]);
        $user = $statement->fetch();
        if (!$user) {
            throw new ApiException(401, 'The account is inactive or no longer has this role.');
        }

        return ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $user['role']];
    }

    private function requireRole(array $user, array $roles): void
    {
        if (!in_array($user['role'], $roles, true)) {
            throw new ApiException(403, 'You do not have permission to access this endpoint.');
        }
    }

    private function changePassword(array $user): never
    {
        $data = $this->request->json();
        $current = (string) ($data['current_password'] ?? '');
        $new = (string) ($data['new_password'] ?? '');
        if (strlen($new) < 10) {
            throw new ApiException(422, 'New password must contain at least 10 characters.');
        }
        $statement = $this->db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $statement->execute([$user['id']]);
        if (!password_verify($current, (string) $statement->fetchColumn())) {
            throw new ApiException(422, 'Current password is incorrect.');
        }
        $update = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        Response::json(['message' => 'Password changed successfully.']);
    }

    private function users(?int $id): never
    {
        if ($this->request->method === 'GET') {
            if ($id !== null) {
                $statement = $this->db->prepare('SELECT id, username, is_active, created_at, updated_at FROM users WHERE id = ?');
                $statement->execute([$id]);
                $user = $statement->fetch();
                if (!$user) {
                    throw new ApiException(404, 'User not found.');
                }
                $user['roles'] = $this->userRoles($id);
                Response::json(['data' => $user]);
            }
            $rows = $this->db->query(
                'SELECT u.id, u.username, u.is_active, u.created_at, u.updated_at, GROUP_CONCAT(r.name ORDER BY r.name) roles '
                . 'FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id '
                . 'GROUP BY u.id ORDER BY u.id DESC LIMIT 200'
            )->fetchAll();
            foreach ($rows as &$row) {
                $row['roles'] = $row['roles'] ? explode(',', $row['roles']) : [];
            }
            Response::json(['data' => $rows]);
        }

        $data = $this->request->json();
        if ($this->request->method === 'POST' && $id === null) {
            $username = trim((string) ($data['username'] ?? ''));
            $password = (string) ($data['password'] ?? '');
            $roles = $this->normalizeRoles($data['roles'] ?? []);
            if ($username === '' || strlen($password) < 10 || $roles === []) {
                throw new ApiException(422, 'Username, a password of at least 10 characters, and at least one valid role are required.');
            }
            $this->db->beginTransaction();
            try {
                $insert = $this->db->prepare('INSERT INTO users (username, password_hash, is_active) VALUES (?, ?, ?)');
                $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT), isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1]);
                $newId = (int) $this->db->lastInsertId();
                $this->syncRoles($newId, $roles);
                $this->db->commit();
                Response::json(['data' => ['id' => $newId, 'username' => $username, 'roles' => $roles]], 201);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }
        if ($this->request->method === 'PATCH' && $id !== null) {
            $sets = [];
            $values = [];
            if (array_key_exists('username', $data)) {
                $sets[] = 'username = ?';
                $values[] = trim((string) $data['username']);
            }
            if (array_key_exists('is_active', $data)) {
                $sets[] = 'is_active = ?';
                $values[] = (int) (bool) $data['is_active'];
            }
            if (isset($data['password'])) {
                if (strlen((string) $data['password']) < 10) {
                    throw new ApiException(422, 'Password must contain at least 10 characters.');
                }
                $sets[] = 'password_hash = ?';
                $values[] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            }
            $this->db->beginTransaction();
            try {
                if ($sets !== []) {
                    $values[] = $id;
                    $this->db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
                }
                if (array_key_exists('roles', $data)) {
                    $roles = $this->normalizeRoles($data['roles']);
                    if ($roles === []) {
                        throw new ApiException(422, 'At least one valid role is required.');
                    }
                    $this->syncRoles($id, $roles);
                }
                $this->db->commit();
                Response::json(['message' => 'User updated successfully.']);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }
        if ($this->request->method === 'DELETE' && $id !== null) {
            $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            Response::json(['message' => 'User deleted successfully.']);
        }
        throw new ApiException(405, 'Method not allowed.');
    }

    private function resource(string $name, ?int $id): never
    {
        if ($name === 'students' || $name === 'teachers') {
            $this->people($name, $id);
        }
        if ($name === 'timetable') {
            $this->timetable($id);
        }
        if ($name === 'standard-subjects' && $this->request->method === 'POST' && $id === null) {
            $this->createStandardSubjects();
        }
        if ($name === 'fee-payments' && $this->request->method === 'POST' && $id === null) {
            $this->createFeePayment();
        }
        if ($name === 'promotions') {
            $this->promotions();
        }

        $definition = self::RESOURCES[$name] ?? null;
        if (!$definition) {
            throw new ApiException(404, 'Resource not found.');
        }
        $table = $definition['table'];

        if ($this->request->method === 'GET') {
            if ($id !== null) {
                $statement = $this->db->prepare("SELECT * FROM `$table` WHERE id = ?");
                $statement->execute([$id]);
                $row = $statement->fetch();
                if (!$row) {
                    throw new ApiException(404, 'Record not found.');
                }
                Response::json(['data' => $row]);
            }
            $limit = min(max((int) ($this->request->query['limit'] ?? 50), 1), 200);
            $offset = max((int) ($this->request->query['offset'] ?? 0), 0);
            $rows = $this->resourceListRows($name, $table, $limit, $offset);
            $total = (int) $this->db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            Response::json(['data' => $rows, 'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]]);
        }

        $data = $this->request->json();
        $values = array_intersect_key($data, array_flip($definition['fields']));
        foreach (['is_active', 'is_teaching_slot'] as $booleanField) {
            if (array_key_exists($booleanField, $values)) {
                $values[$booleanField] = (int) (bool) $values[$booleanField];
            }
        }
        if ($this->request->method === 'POST' && $id === null) {
            $missing = array_values(array_filter($definition['required'], static fn (string $field): bool => !array_key_exists($field, $values) || $values[$field] === ''));
            if ($missing !== []) {
                throw new ApiException(422, 'Required fields are missing.', ['fields' => $missing]);
            }
            if ($name === 'enrollments' && !isset($values['status'])) {
                $values['status'] = 'active';
            }
            $columns = array_keys($values);
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $this->db->prepare($sql)->execute(array_values($values));
            Response::json(['data' => ['id' => (int) $this->db->lastInsertId()] + $values], 201);
        }
        if ($this->request->method === 'PATCH' && $id !== null) {
            if ($values === []) {
                throw new ApiException(422, 'No valid fields were supplied.');
            }
            $sets = array_map(static fn (string $field): string => "`$field` = ?", array_keys($values));
            $this->db->prepare("UPDATE `$table` SET " . implode(', ', $sets) . ' WHERE id = ?')->execute([...array_values($values), $id]);
            Response::json(['message' => 'Record updated successfully.']);
        }
        if ($this->request->method === 'DELETE' && $id !== null) {
            $this->db->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
            Response::json(['message' => 'Record deleted successfully.']);
        }
        throw new ApiException(405, 'Method not allowed.');
    }

    private function createStandardSubjects(): never
    {
        $data = $this->request->json();
        $standardId = (int) ($data['standard_id'] ?? 0);
        $subjectIds = $this->standardIds($data['subject_ids'] ?? (isset($data['subject_id']) ? [$data['subject_id']] : null));
        if ($standardId < 1 || $subjectIds === []) {
            throw new ApiException(422, 'A standard and at least one subject are required.');
        }

        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare('INSERT IGNORE INTO standard_subjects (standard_id, subject_id) VALUES (?, ?)');
            $created = 0;
            foreach ($subjectIds as $subjectId) {
                $insert->execute([$standardId, $subjectId]);
                $created += $insert->rowCount();
            }
            $this->db->commit();
            Response::json([
                'data' => ['standard_id' => $standardId, 'subject_ids' => $subjectIds],
                'message' => $created > 0 ? "$created subject mapping(s) created." : 'All selected subjects were already assigned.',
            ], $created > 0 ? 201 : 200);
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function createFeePayment(): never
    {
        $data = $this->request->json();
        $enrollmentId = (int) ($data['enrollment_id'] ?? 0);
        $amount = trim((string) ($data['amount'] ?? ''));
        $paymentDate = trim((string) ($data['payment_date'] ?? ''));
        $methodKey = strtolower(str_replace([' ', '-'], '_', trim((string) ($data['payment_method'] ?? ''))));
        $paymentMethod = ['cash' => 'cash', 'card' => 'card', 'upi' => 'UPI', 'bank_transfer' => 'bank_transfer'][$methodKey] ?? null;
        $receiptNo = trim((string) ($data['receipt_no'] ?? ''));
        $referenceNo = trim((string) ($data['reference_no'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($enrollmentId < 1) {
            throw new ApiException(422, 'Select an enrollment.');
        }
        if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            throw new ApiException(422, 'Enter an amount greater than zero.');
        }
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if (!$parsedDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            throw new ApiException(422, 'Enter a valid payment date.');
        }
        if ($paymentMethod === null) {
            throw new ApiException(422, 'Select a valid payment method.');
        }
        if ($receiptNo === '') {
            throw new ApiException(422, 'Enter a receipt number.');
        }

        $enrollment = $this->db->prepare('SELECT id FROM enrollments WHERE id = ?');
        $enrollment->execute([$enrollmentId]);
        if (!$enrollment->fetchColumn()) {
            throw new ApiException(422, 'The selected enrollment is no longer available.');
        }
        $duplicate = $this->db->prepare('SELECT id FROM fee_payments WHERE receipt_no = ?');
        $duplicate->execute([$receiptNo]);
        if ($duplicate->fetchColumn()) {
            throw new ApiException(409, 'This receipt number has already been used.');
        }

        $statement = $this->db->prepare(
            'INSERT INTO fee_payments (enrollment_id, amount, payment_date, payment_method, reference_no, receipt_no, notes) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $enrollmentId,
            number_format((float) $amount, 2, '.', ''),
            $parsedDate->format('Y-m-d'),
            $paymentMethod,
            $referenceNo === '' ? null : $referenceNo,
            $receiptNo,
            $notes === '' ? null : $notes,
        ]);
        Response::json([
            'data' => ['id' => (int) $this->db->lastInsertId()],
            'message' => 'Payment recorded successfully.',
        ], 201);
    }

    private function resourceListRows(string $name, string $table, int $limit, int $offset): array
    {
        $sql = match ($name) {
            'standard-subjects' =>
                'SELECT ss.*, s.name standard, sub.name subject FROM standard_subjects ss '
                . 'INNER JOIN standards s ON s.id = ss.standard_id INNER JOIN subjects sub ON sub.id = ss.subject_id '
                . "ORDER BY ss.id DESC LIMIT $limit OFFSET $offset",
            'teacher-subjects' =>
                'SELECT ts.*, t.name teacher, t.employee_no, sub.name subject, sub.code subject_code FROM teacher_subjects ts '
                . 'INNER JOIN teachers t ON t.id = ts.teacher_id INNER JOIN subjects sub ON sub.id = ts.subject_id '
                . "ORDER BY t.name, sub.name LIMIT $limit OFFSET $offset",
            'lecture-slots' =>
                "SELECT * FROM lecture_slots ORDER BY slot_order LIMIT $limit OFFSET $offset",
            'batches' =>
                'SELECT b.*, s.name standard, ay.name academic_year FROM batches b '
                . 'INNER JOIN standards s ON s.id = b.standard_id INNER JOIN academic_years ay ON ay.id = b.academic_year_id '
                . "ORDER BY ay.start_date DESC, s.name, b.name LIMIT $limit OFFSET $offset",
            'enrollments' =>
                "SELECT e.*, st.name student, b.name batch, b.standard_id, s.name standard, ay.name academic_year, "
                . "CONCAT(st.name, ' / ', s.name, ' / ', b.name, ' / ', ay.name) enrollment FROM enrollments e "
                . 'INNER JOIN students st ON st.id = e.student_id INNER JOIN batches b ON b.id = e.batch_id '
                . 'INNER JOIN standards s ON s.id = b.standard_id '
                . "INNER JOIN academic_years ay ON ay.id = e.academic_year_id ORDER BY e.id DESC LIMIT $limit OFFSET $offset",
            'standard-fees' =>
                'SELECT sf.*, s.name standard, ay.name academic_year FROM standard_fees sf '
                . 'INNER JOIN standards s ON s.id = sf.standard_id INNER JOIN academic_years ay ON ay.id = sf.academic_year_id '
                . "ORDER BY sf.id DESC LIMIT $limit OFFSET $offset",
            'fee-payments' =>
                "SELECT fp.*, CONCAT(st.name, ' / ', s.name, ' / ', b.name, ' / ', ay.name) enrollment FROM fee_payments fp "
                . 'INNER JOIN enrollments e ON e.id = fp.enrollment_id INNER JOIN students st ON st.id = e.student_id '
                . 'INNER JOIN batches b ON b.id = e.batch_id INNER JOIN standards s ON s.id = b.standard_id '
                . 'INNER JOIN academic_years ay ON ay.id = e.academic_year_id '
                . "ORDER BY fp.id DESC LIMIT $limit OFFSET $offset",
            default => "SELECT * FROM `$table` ORDER BY id DESC LIMIT $limit OFFSET $offset",
        };
        return $this->db->query($sql)->fetchAll();
    }

    private function timetable(?int $id): never
    {
        if ($this->request->method === 'GET' && $id === null) {
            $batchId = (int) ($this->request->query['batch_id'] ?? 0);
            if ($batchId < 1) {
                throw new ApiException(422, 'batch_id is required.');
            }
            $statement = $this->db->prepare(
                'SELECT te.*, sub.name subject, sub.code subject_code, t.name teacher, t.employee_no, '
                . 'ls.name slot, ls.start_time, ls.end_time FROM timetable_entries te '
                . 'INNER JOIN subjects sub ON sub.id = te.subject_id INNER JOIN teachers t ON t.id = te.teacher_id '
                . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id WHERE te.batch_id = ? '
                . 'ORDER BY ls.slot_order, te.day_of_week'
            );
            $statement->execute([$batchId]);
            Response::json(['data' => $statement->fetchAll()]);
        }

        if ($this->request->method === 'POST' && $id === null) {
            $data = $this->request->json();
            $batchId = (int) ($data['batch_id'] ?? 0);
            $day = (int) ($data['day_of_week'] ?? 0);
            $slotId = (int) ($data['slot_id'] ?? 0);
            $subjectId = (int) ($data['subject_id'] ?? 0);
            $teacherId = (int) ($data['teacher_id'] ?? 0);
            $replaceConflict = (bool) ($data['replace_conflict'] ?? false);
            $replacementTeacherId = (int) ($data['replacement_teacher_id'] ?? 0);
            if ($batchId < 1 || $day < 1 || $day > 7 || $slotId < 1 || $subjectId < 1 || $teacherId < 1) {
                throw new ApiException(422, 'Batch, day, slot, subject, and teacher are required.');
            }

            $contextStatement = $this->db->prepare(
                'SELECT b.academic_year_id, b.name batch, s.name standard FROM batches b '
                . 'INNER JOIN standards s ON s.id = b.standard_id WHERE b.id = ?'
            );
            $contextStatement->execute([$batchId]);
            $context = $contextStatement->fetch();
            if (!$context) {
                throw new ApiException(404, 'Batch not found.');
            }
            $academicYearId = (int) $context['academic_year_id'];

            $slotStatement = $this->db->prepare('SELECT id FROM lecture_slots WHERE id = ? AND is_active = 1 AND is_teaching_slot = 1');
            $slotStatement->execute([$slotId]);
            if (!$slotStatement->fetchColumn()) {
                throw new ApiException(422, 'Select an active teaching slot.');
            }
            $eligibility = $this->db->prepare('SELECT id FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?');
            $eligibility->execute([$teacherId, $subjectId]);
            if (!$eligibility->fetchColumn()) {
                throw new ApiException(422, 'The selected teacher is not allowed to teach this subject.');
            }

            $currentStatement = $this->db->prepare(
                'SELECT id FROM timetable_entries WHERE academic_year_id = ? AND batch_id = ? AND day_of_week = ? AND slot_id = ?'
            );
            $currentStatement->execute([$academicYearId, $batchId, $day, $slotId]);
            $currentId = (int) ($currentStatement->fetchColumn() ?: 0);

            $conflictStatement = $this->db->prepare(
                'SELECT te.id, te.batch_id, te.day_of_week, te.slot_id, te.subject_id, t.name teacher, sub.name subject, '
                . 'b.name batch, s.name standard, ls.name slot FROM timetable_entries te '
                . 'INNER JOIN teachers t ON t.id = te.teacher_id INNER JOIN subjects sub ON sub.id = te.subject_id '
                . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
                . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id '
                . 'WHERE te.academic_year_id = ? AND te.teacher_id = ? AND te.day_of_week = ? AND te.slot_id = ? AND te.id <> ? LIMIT 1'
            );
            $conflictStatement->execute([$academicYearId, $teacherId, $day, $slotId, $currentId]);
            $conflict = $conflictStatement->fetch();
            $replacementTeachers = $conflict
                ? $this->availableReplacementTeachers($academicYearId, (int) $conflict['subject_id'], $day, $slotId, $teacherId, (int) $conflict['id'])
                : [];
            if ($conflict) {
                $conflict['available_teachers'] = $replacementTeachers;
            }
            if ($conflict && !$replaceConflict) {
                throw new ApiException(409, 'This teacher is already assigned during that slot.', ['conflict' => $conflict]);
            }
            if ($conflict && !in_array($replacementTeacherId, array_map(static fn (array $teacher): int => (int) $teacher['id'], $replacementTeachers), true)) {
                throw new ApiException(422, 'Choose an available replacement teacher for the existing lecture.', ['conflict' => $conflict]);
            }

            $this->db->beginTransaction();
            try {
                if ($conflict) {
                    $this->db->prepare('UPDATE timetable_entries SET teacher_id = ? WHERE id = ?')
                        ->execute([$replacementTeacherId, $conflict['id']]);
                }
                if ($currentId > 0) {
                    $this->db->prepare('UPDATE timetable_entries SET subject_id = ?, teacher_id = ? WHERE id = ?')
                        ->execute([$subjectId, $teacherId, $currentId]);
                    $entryId = $currentId;
                } else {
                    $this->db->prepare(
                        'INSERT INTO timetable_entries (academic_year_id, batch_id, day_of_week, slot_id, subject_id, teacher_id) '
                        . 'VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$academicYearId, $batchId, $day, $slotId, $subjectId, $teacherId]);
                    $entryId = (int) $this->db->lastInsertId();
                }
                $this->db->commit();
                Response::json(['data' => ['id' => $entryId], 'message' => $conflict ? 'Teacher moved and the previous lecture was reassigned.' : 'Lecture saved.'], $currentId > 0 ? 200 : 201);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }

        if ($this->request->method === 'DELETE' && $id !== null) {
            $this->db->prepare('DELETE FROM timetable_entries WHERE id = ?')->execute([$id]);
            Response::json(['message' => 'Lecture removed.']);
        }

        throw new ApiException(405, 'Method not allowed.');
    }

    private function availableReplacementTeachers(int $academicYearId, int $subjectId, int $day, int $slotId, int $movedTeacherId, int $conflictId): array
    {
        $statement = $this->db->prepare(
            'SELECT t.id, t.name, t.employee_no FROM teacher_subjects ts '
            . 'INNER JOIN teachers t ON t.id = ts.teacher_id INNER JOIN users u ON u.id = t.user_id AND u.is_active = 1 '
            . 'WHERE ts.subject_id = ? AND t.id <> ? AND NOT EXISTS ('
            . 'SELECT 1 FROM timetable_entries busy WHERE busy.academic_year_id = ? AND busy.teacher_id = t.id '
            . 'AND busy.day_of_week = ? AND busy.slot_id = ? AND busy.id <> ?) ORDER BY t.name'
        );
        $statement->execute([$subjectId, $movedTeacherId, $academicYearId, $day, $slotId, $conflictId]);
        return $statement->fetchAll();
    }

    private function promotions(): never
    {
        if ($this->request->method === 'GET') {
            $sourceBatchId = (int) ($this->request->query['source_batch_id'] ?? 0);
            if ($sourceBatchId < 1) {
                throw new ApiException(422, 'source_batch_id is required.');
            }
            $statement = $this->db->prepare(
                "SELECT e.id enrollment_id, e.student_id, st.admission_no, st.name, e.status FROM enrollments e "
                . "INNER JOIN students st ON st.id = e.student_id WHERE e.batch_id = ? AND e.status = 'active' "
                . 'AND NOT EXISTS (SELECT 1 FROM enrollments promoted WHERE promoted.promoted_from_enrollment_id = e.id) ORDER BY st.name'
            );
            $statement->execute([$sourceBatchId]);
            Response::json(['data' => $statement->fetchAll()]);
        }

        if ($this->request->method === 'POST') {
            $data = $this->request->json();
            $sourceBatchId = (int) ($data['source_batch_id'] ?? 0);
            $destinationBatchId = (int) ($data['destination_batch_id'] ?? 0);
            $studentIds = $this->standardIds($data['student_ids'] ?? null);
            $enrollmentDate = trim((string) ($data['enrollment_date'] ?? ''));
            if ($sourceBatchId < 1 || $destinationBatchId < 1 || $sourceBatchId === $destinationBatchId || $studentIds === [] || $enrollmentDate === '') {
                throw new ApiException(422, 'Source batch, destination batch, enrollment date, and at least one student are required.');
            }

            $batchStatement = $this->db->prepare(
                'SELECT b.id, b.academic_year_id, ay.start_date FROM batches b INNER JOIN academic_years ay ON ay.id = b.academic_year_id WHERE b.id IN (?, ?)'
            );
            $batchStatement->execute([$sourceBatchId, $destinationBatchId]);
            $batches = [];
            foreach ($batchStatement->fetchAll() as $batch) {
                $batches[(int) $batch['id']] = $batch;
            }
            if (!isset($batches[$sourceBatchId], $batches[$destinationBatchId])) {
                throw new ApiException(404, 'Source or destination batch not found.');
            }
            if ($batches[$destinationBatchId]['start_date'] <= $batches[$sourceBatchId]['start_date']) {
                throw new ApiException(422, 'The destination batch must belong to a later academic year.');
            }

            $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
            $sourceStatement = $this->db->prepare(
                "SELECT id, student_id FROM enrollments WHERE batch_id = ? AND status = 'active' AND student_id IN ($placeholders)"
            );
            $sourceStatement->execute([$sourceBatchId, ...$studentIds]);
            $sourceEnrollments = $sourceStatement->fetchAll();
            if (count($sourceEnrollments) !== count($studentIds)) {
                throw new ApiException(422, 'One or more selected students are not active in the source batch.');
            }

            $this->db->beginTransaction();
            try {
                $insert = $this->db->prepare(
                    "INSERT INTO enrollments (student_id, batch_id, academic_year_id, enrollment_date, status, promoted_from_enrollment_id) VALUES (?, ?, ?, ?, 'active', ?)"
                );
                $complete = $this->db->prepare("UPDATE enrollments SET status = 'completed' WHERE id = ?");
                foreach ($sourceEnrollments as $sourceEnrollment) {
                    $insert->execute([
                        $sourceEnrollment['student_id'],
                        $destinationBatchId,
                        $batches[$destinationBatchId]['academic_year_id'],
                        $enrollmentDate,
                        $sourceEnrollment['id'],
                    ]);
                    $complete->execute([$sourceEnrollment['id']]);
                }
                $this->db->commit();
                Response::json(['message' => count($sourceEnrollments) . ' student(s) promoted successfully.'], 201);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }

        throw new ApiException(405, 'Method not allowed.');
    }

    private function people(string $name, ?int $id): never
    {
        $isStudent = $name === 'students';
        $table = $isStudent ? 'students' : 'teachers';
        $role = $isStudent ? 'student' : 'teacher';
        $fields = $isStudent
            ? ['admission_no', 'name', 'date_of_birth', 'gender', 'phone', 'email', 'address']
            : ['employee_no', 'name', 'phone', 'email'];
        $required = $isStudent ? ['admission_no', 'name'] : ['employee_no', 'name'];

        if ($this->request->method === 'GET') {
            $where = $id === null ? '' : ' WHERE p.id = ?';
            $limit = $id === null ? ' ORDER BY p.id DESC LIMIT 200' : '';
            $statement = $this->db->prepare(
                "SELECT p.*, u.username, u.is_active account_is_active FROM `$table` p "
                . "LEFT JOIN users u ON u.id = p.user_id$where$limit"
            );
            $statement->execute($id === null ? [] : [$id]);
            if ($id !== null) {
                $row = $statement->fetch();
                if (!$row) {
                    throw new ApiException(404, ucfirst($role) . ' not found.');
                }
                Response::json(['data' => $row]);
            }
            $rows = $statement->fetchAll();
            Response::json(['data' => $rows, 'meta' => ['total' => count($rows), 'limit' => 200, 'offset' => 0]]);
        }

        $data = $this->request->json();
        $values = array_intersect_key($data, array_flip($fields));
        if ($this->request->method === 'POST' && $id === null) {
            $username = trim((string) ($data['username'] ?? ''));
            $password = (string) ($data['password'] ?? '');
            $missing = array_values(array_filter($required, static fn (string $field): bool => !isset($values[$field]) || $values[$field] === ''));
            if ($username === '' || strlen($password) < 10 || $missing !== []) {
                throw new ApiException(422, 'Username, a password of at least 10 characters, and all required profile fields are required.', ['fields' => $missing]);
            }

            $this->db->beginTransaction();
            try {
                $this->db->prepare('INSERT INTO users (username, password_hash, is_active) VALUES (?, ?, 1)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int) $this->db->lastInsertId();
                $this->syncRoles($userId, [$role]);
                $columns = ['user_id', ...array_keys($values)];
                $insert = "INSERT INTO `$table` (`" . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
                $this->db->prepare($insert)->execute([$userId, ...array_values($values)]);
                $profileId = (int) $this->db->lastInsertId();
                $this->db->commit();
                Response::json(['data' => ['id' => $profileId, 'user_id' => $userId, 'username' => $username] + $values], 201);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }

        if ($this->request->method === 'PATCH' && $id !== null) {
            if ($values === []) {
                throw new ApiException(422, 'No valid profile fields were supplied.');
            }
            $sets = array_map(static fn (string $field): string => "`$field` = ?", array_keys($values));
            $this->db->prepare("UPDATE `$table` SET " . implode(', ', $sets) . ' WHERE id = ?')->execute([...array_values($values), $id]);
            Response::json(['message' => ucfirst($role) . ' updated successfully.']);
        }

        if ($this->request->method === 'DELETE' && $id !== null) {
            $statement = $this->db->prepare("SELECT user_id FROM `$table` WHERE id = ?");
            $statement->execute([$id]);
            $userId = (int) $statement->fetchColumn();
            if ($userId < 1) {
                throw new ApiException(404, ucfirst($role) . ' not found.');
            }
            $this->db->beginTransaction();
            try {
                $this->db->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
                $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
                $this->db->commit();
                Response::json(['message' => ucfirst($role) . ' and login account deleted successfully.']);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }

        throw new ApiException(405, 'Method not allowed.');
    }

    private function standardIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $value), static fn (int $id): bool => $id > 0)));
    }

    private function adminAttendance(array $user, int $sessionId): never
    {
        if ($this->request->method === 'GET') {
            $session = $this->db->prepare(
                'SELECT ats.*, marker.username marked_by, submitter.username submitted_by, lo.lecture_date, '
                . 'lo.batch_id, b.name batch, s.name standard, ls.name slot, sub.name planned_subject '
                . 'FROM attendance_sessions ats INNER JOIN lecture_occurrences lo ON lo.id = ats.lecture_occurrence_id '
                . 'INNER JOIN users marker ON marker.id = ats.marked_by_user_id '
                . 'LEFT JOIN users submitter ON submitter.id = ats.submitted_by_user_id '
                . 'INNER JOIN batches b ON b.id = lo.batch_id INNER JOIN standards s ON s.id = b.standard_id '
                . 'INNER JOIN lecture_slots ls ON ls.id = lo.slot_id INNER JOIN subjects sub ON sub.id = lo.planned_subject_id '
                . 'WHERE ats.id = ?'
            );
            $session->execute([$sessionId]);
            $sessionData = $session->fetch();
            if (!$sessionData) {
                throw new ApiException(404, 'Attendance session not found.');
            }
            $records = $this->db->prepare(
                'SELECT ar.*, st.name student, st.admission_no, marker.username marked_by, updater.username last_updated_by '
                . 'FROM attendance_records ar INNER JOIN enrollments e ON e.id = ar.enrollment_id '
                . 'INNER JOIN students st ON st.id = e.student_id INNER JOIN users marker ON marker.id = ar.marked_by_user_id '
                . 'INNER JOIN users updater ON updater.id = ar.last_updated_by_user_id '
                . 'WHERE ar.attendance_session_id = ? ORDER BY st.name'
            );
            $records->execute([$sessionId]);
            $history = $this->db->prepare(
                'SELECT h.*, u.username changed_by FROM attendance_record_history h '
                . 'INNER JOIN users u ON u.id = h.changed_by_user_id WHERE h.attendance_session_id = ? ORDER BY h.changed_at, h.id'
            );
            $history->execute([$sessionId]);
            Response::json(['data' => ['session' => $sessionData, 'records' => $records->fetchAll(), 'history' => $history->fetchAll()]]);
        }

        if ($this->request->method === 'PATCH') {
            $data = $this->request->json();
            $records = $data['records'] ?? null;
            $reason = trim((string) ($data['correction_reason'] ?? ''));
            if (!is_array($records) || $records === [] || $reason === '') {
                throw new ApiException(422, 'Attendance records and a correction reason are required.');
            }
            $this->db->beginTransaction();
            try {
                $session = $this->db->prepare('SELECT id FROM attendance_sessions WHERE id = ? FOR UPDATE');
                $session->execute([$sessionId]);
                if (!$session->fetchColumn()) {
                    throw new ApiException(404, 'Attendance session not found.');
                }
                foreach ($records as $record) {
                    $enrollmentId = (int) ($record['enrollment_id'] ?? 0);
                    $status = (string) ($record['status'] ?? '');
                    if ($enrollmentId < 1 || !in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                        throw new ApiException(422, 'Each correction requires an enrollment and valid status.');
                    }
                    $minutesLate = $status === 'late' ? (int) ($record['minutes_late'] ?? 0) : null;
                    if ($status === 'late' && $minutesLate < 1) {
                        throw new ApiException(422, 'Minutes late is required when a student is marked late.');
                    }
                    $remarks = trim((string) ($record['remarks'] ?? ''));
                    $this->upsertAttendanceRecord(
                        $sessionId, $enrollmentId, $status, $minutesLate,
                        $remarks !== '' ? $remarks : null, (int) $user['id'], 'corrected', $reason
                    );
                }
                $this->db->commit();
                Response::json(['message' => 'Attendance corrected and added to the audit history.']);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }

        throw new ApiException(405, 'Method not allowed.');
    }

    private function adminAttendanceReports(): never
    {
        $view = (string) ($this->request->query['view'] ?? 'overview');
        if (!in_array($view, ['overview', 'lectures', 'batches', 'teachers', 'students', 'student-lectures'], true)) {
            throw new ApiException(422, 'Invalid attendance report view.');
        }
        $academicYearId = (int) ($this->request->query['academic_year_id'] ?? 0);
        if ($academicYearId < 1) {
            throw new ApiException(422, 'Academic year is required.');
        }
        $yearStatement = $this->db->prepare('SELECT start_date, end_date FROM academic_years WHERE id = ?');
        $yearStatement->execute([$academicYearId]);
        $year = $yearStatement->fetch();
        if (!$year) {
            throw new ApiException(404, 'Academic year not found.');
        }
        $from = $this->requiredDate($this->request->query['date_from'] ?? null);
        $to = $this->requiredDate($this->request->query['date_to'] ?? null);
        if ($from > $to || $from < $year['start_date'] || $to > $year['end_date']) {
            throw new ApiException(422, 'Report dates must be ordered and inside the academic year.');
        }
        $days = (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days + 1;
        if ($days > 366) {
            throw new ApiException(422, 'Attendance reports are limited to 366 days.');
        }
        $filters = [
            'batch_id' => max(0, (int) ($this->request->query['batch_id'] ?? 0)),
            'teacher_id' => max(0, (int) ($this->request->query['teacher_id'] ?? 0)),
        ];
        if ($view === 'student-lectures') {
            $studentId = max(0, (int) ($this->request->query['student_id'] ?? 0));
            if ($studentId < 1) {
                throw new ApiException(422, 'Student is required.');
            }
            Response::json(['data' => $this->adminStudentLectureRows($academicYearId, $from, $to, $studentId, $filters)]);
        }
        if ($view === 'students') {
            Response::json(['data' => $this->adminStudentAttendanceRows($academicYearId, $from, $to, $filters)]);
        }

        $rows = $this->adminAttendanceLectureRows($academicYearId, $from, $to, $filters);
        if ($view === 'lectures') {
            Response::json(['data' => $rows]);
        }
        if ($view === 'overview') {
            $summary = ['scheduled' => count($rows), 'started' => 0, 'conducted' => 0, 'cancelled' => 0, 'attendance_completed' => 0, 'pending' => 0, 'proxy' => 0, 'adjustment' => 0, 'eligible_students' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
            $pending = [];
            foreach ($rows as $row) {
                $started = $this->reportLectureStarted($row);
                $complete = in_array($row['attendance_status'], ['submitted', 'locked'], true);
                $summary['started'] += $started ? 1 : 0;
                $summary['conducted'] += $row['lecture_status'] === 'conducted' ? 1 : 0;
                $summary['cancelled'] += $row['lecture_status'] === 'cancelled' ? 1 : 0;
                $summary['attendance_completed'] += $complete ? 1 : 0;
                $summary['proxy'] += $row['change_type'] === 'proxy' ? 1 : 0;
                $summary['adjustment'] += $row['change_type'] === 'adjustment' ? 1 : 0;
                $summary['eligible_students'] += (int) $row['eligible_students'];
                foreach (['present', 'absent', 'late', 'excused'] as $status) {
                    $summary[$status] += (int) $row[$status];
                }
                if ($started && !$complete && $row['lecture_status'] !== 'cancelled') {
                    $summary['pending']++;
                    if (count($pending) < 20) {
                        $pending[] = $row;
                    }
                }
            }
            $summary['completion_rate'] = $summary['started'] > 0 ? round($summary['attendance_completed'] * 100 / $summary['started'], 1) : 0;
            $marked = $summary['present'] + $summary['absent'] + $summary['late'] + $summary['excused'];
            $summary['student_attendance_rate'] = $marked > 0 ? round(($summary['present'] + $summary['late']) * 100 / $marked, 1) : 0;
            $summary['data_completion_rate'] = $summary['eligible_students'] > 0 ? round($marked * 100 / $summary['eligible_students'], 1) : 0;
            Response::json(['data' => ['summary' => $summary, 'pending' => $pending]]);
        }

        $groups = [];
        foreach ($rows as $row) {
            $isTeacher = $view === 'teachers';
            $key = (string) ($isTeacher ? $row['effective_teacher_id'] : $row['batch_id']);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => (int) $key,
                    'name' => $isTeacher ? $row['effective_teacher'] : $row['standard'] . ' / ' . $row['batch'],
                    'scheduled' => 0, 'started' => 0, 'conducted' => 0, 'attendance_completed' => 0,
                    'pending' => 0, 'cancelled' => 0, 'proxy' => 0, 'adjustment' => 0, 'eligible_students' => 0,
                    'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0,
                ];
            }
            $group = &$groups[$key];
            $started = $this->reportLectureStarted($row);
            $complete = in_array($row['attendance_status'], ['submitted', 'locked'], true);
            $group['scheduled']++;
            $group['started'] += $started ? 1 : 0;
            $group['conducted'] += $row['lecture_status'] === 'conducted' ? 1 : 0;
            $group['cancelled'] += $row['lecture_status'] === 'cancelled' ? 1 : 0;
            $group['attendance_completed'] += $complete ? 1 : 0;
            $group['pending'] += $started && !$complete && $row['lecture_status'] !== 'cancelled' ? 1 : 0;
            $group['proxy'] += $row['change_type'] === 'proxy' ? 1 : 0;
            $group['adjustment'] += $row['change_type'] === 'adjustment' ? 1 : 0;
            $group['eligible_students'] += (int) $row['eligible_students'];
            foreach (['present', 'absent', 'late', 'excused'] as $status) {
                $group[$status] += (int) $row[$status];
            }
            unset($group);
        }
        foreach ($groups as &$group) {
            $marked = $group['present'] + $group['absent'] + $group['late'] + $group['excused'];
            $group['completion_rate'] = $group['started'] > 0 ? round($group['attendance_completed'] * 100 / $group['started'], 1) : 0;
            $group['student_attendance_rate'] = $marked > 0 ? round(($group['present'] + $group['late']) * 100 / $marked, 1) : 0;
        }
        unset($group);
        Response::json(['data' => array_values($groups)]);
    }

    private function adminAttendanceLectureRows(int $academicYearId, string $from, string $to, array $filters): array
    {
        $where = '';
        if ($filters['batch_id'] > 0) {
            $where .= ' AND te.batch_id = ' . $filters['batch_id'];
        }
        if ($filters['teacher_id'] > 0) {
            $where .= ' AND COALESCE(lc.replacement_teacher_id, te.teacher_id) = ' . $filters['teacher_id'];
        }
        $statement = $this->db->prepare(
            'WITH RECURSIVE report_dates AS (SELECT CAST(? AS DATE) lecture_date UNION ALL '
            . 'SELECT DATE_ADD(lecture_date, INTERVAL 1 DAY) FROM report_dates WHERE lecture_date < CAST(? AS DATE)), '
            . 'record_counts AS (SELECT attendance_session_id, '
            . "SUM(status = 'present') present, SUM(status = 'absent') absent, SUM(status = 'late') late, SUM(status = 'excused') excused "
            . 'FROM attendance_records GROUP BY attendance_session_id) '
            . 'SELECT te.id timetable_entry_id, d.lecture_date, te.batch_id, b.name batch, s.name standard, '
            . 'ls.name slot, ls.start_time, ls.end_time, te.subject_id planned_subject_id, ps.name planned_subject, '
            . 'te.teacher_id planned_teacher_id, pt.name planned_teacher, lo.id lecture_occurrence_id, '
            . "COALESCE(lo.status, 'scheduled') lecture_status, lc.change_type, "
            . 'COALESCE(lc.replacement_teacher_id, te.teacher_id) effective_teacher_id, '
            . 'COALESCE(rt.name, pt.name) effective_teacher, '
            . "CASE WHEN lc.change_type = 'adjustment' THEN rs.name WHEN lc.change_type = 'proxy' THEN NULL ELSE ps.name END effective_subject, "
            . 'ats.id attendance_session_id, ats.status attendance_status, marker.username marked_by, ats.submitted_at, '
            . '(SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = te.batch_id AND e.enrollment_date <= d.lecture_date '
            . 'AND (e.end_date IS NULL OR e.end_date >= d.lecture_date)) eligible_students, '
            . 'COALESCE(rc.present, 0) present, COALESCE(rc.absent, 0) absent, COALESCE(rc.late, 0) late, COALESCE(rc.excused, 0) excused '
            . 'FROM report_dates d INNER JOIN timetable_entries te ON te.day_of_week = WEEKDAY(d.lecture_date) + 1 '
            . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id INNER JOIN subjects ps ON ps.id = te.subject_id '
            . 'INNER JOIN teachers pt ON pt.id = te.teacher_id '
            . 'LEFT JOIN lecture_occurrences lo ON lo.timetable_entry_id = te.id AND lo.lecture_date = d.lecture_date '
            . "LEFT JOIN lecture_changes lc ON lc.lecture_occurrence_id = lo.id AND lc.status = 'active' "
            . 'LEFT JOIN teachers rt ON rt.id = lc.replacement_teacher_id LEFT JOIN subjects rs ON rs.id = lc.replacement_subject_id '
            . 'LEFT JOIN attendance_sessions ats ON ats.lecture_occurrence_id = lo.id '
            . 'LEFT JOIN users marker ON marker.id = ats.marked_by_user_id LEFT JOIN record_counts rc ON rc.attendance_session_id = ats.id '
            . 'WHERE te.academic_year_id = ?' . $where
            . ' ORDER BY d.lecture_date DESC, ls.slot_order DESC, b.name'
        );
        $statement->execute([$from, $to, $academicYearId]);
        return $statement->fetchAll();
    }

    private function adminStudentAttendanceRows(int $academicYearId, string $from, string $to, array $filters): array
    {
        $where = '';
        if ($filters['batch_id'] > 0) {
            $where .= ' AND te.batch_id = ' . $filters['batch_id'];
        }
        if ($filters['teacher_id'] > 0) {
            $where .= ' AND COALESCE(lc.replacement_teacher_id, te.teacher_id) = ' . $filters['teacher_id'];
        }
        $statement = $this->db->prepare(
            'WITH RECURSIVE report_dates AS (SELECT CAST(? AS DATE) lecture_date UNION ALL '
            . 'SELECT DATE_ADD(lecture_date, INTERVAL 1 DAY) FROM report_dates WHERE lecture_date < CAST(? AS DATE)) '
            . 'SELECT st.id, st.name, st.admission_no, b.name batch, s.name standard, COUNT(*) eligible_lectures, '
            . 'COUNT(ar.id) marked_lectures, COALESCE(SUM(ar.status = \'present\'), 0) present, '
            . 'COALESCE(SUM(ar.status = \'absent\'), 0) absent, COALESCE(SUM(ar.status = \'late\'), 0) late, '
            . 'COALESCE(SUM(ar.status = \'excused\'), 0) excused '
            . 'FROM report_dates d INNER JOIN timetable_entries te ON te.day_of_week = WEEKDAY(d.lecture_date) + 1 '
            . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN enrollments e ON e.batch_id = te.batch_id AND e.academic_year_id = te.academic_year_id '
            . 'AND e.enrollment_date <= d.lecture_date AND (e.end_date IS NULL OR e.end_date >= d.lecture_date) '
            . 'INNER JOIN students st ON st.id = e.student_id '
            . 'LEFT JOIN lecture_occurrences lo ON lo.timetable_entry_id = te.id AND lo.lecture_date = d.lecture_date '
            . "LEFT JOIN lecture_changes lc ON lc.lecture_occurrence_id = lo.id AND lc.status = 'active' "
            . 'LEFT JOIN attendance_sessions ats ON ats.lecture_occurrence_id = lo.id '
            . 'LEFT JOIN attendance_records ar ON ar.attendance_session_id = ats.id AND ar.enrollment_id = e.id '
            . 'WHERE te.academic_year_id = ?' . $where . " AND (lo.status IS NULL OR lo.status <> 'cancelled') "
            . 'GROUP BY st.id, st.name, st.admission_no, b.name, s.name ORDER BY st.name'
        );
        $statement->execute([$from, $to, $academicYearId]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $marked = (int) $row['marked_lectures'];
            $row['attendance_rate'] = $marked > 0 ? round(((int) $row['present'] + (int) $row['late']) * 100 / $marked, 1) : 0;
            $row['data_completion_rate'] = (int) $row['eligible_lectures'] > 0 ? round($marked * 100 / (int) $row['eligible_lectures'], 1) : 0;
        }
        unset($row);
        return $rows;
    }

    private function adminStudentLectureRows(int $academicYearId, string $from, string $to, int $studentId, array $filters): array
    {
        $where = '';
        if ($filters['batch_id'] > 0) {
            $where .= ' AND te.batch_id = ' . $filters['batch_id'];
        }
        if ($filters['teacher_id'] > 0) {
            $where .= ' AND COALESCE(lc.replacement_teacher_id, te.teacher_id) = ' . $filters['teacher_id'];
        }
        $statement = $this->db->prepare(
            'WITH RECURSIVE report_dates AS (SELECT CAST(? AS DATE) lecture_date UNION ALL '
            . 'SELECT DATE_ADD(lecture_date, INTERVAL 1 DAY) FROM report_dates WHERE lecture_date < CAST(? AS DATE)) '
            . 'SELECT te.id timetable_entry_id, d.lecture_date, te.batch_id, b.name batch, s.name standard, '
            . 'ls.name slot, ls.start_time, ls.end_time, ps.name planned_subject, '
            . 'COALESCE(rt.name, pt.name) effective_teacher, '
            . "CASE WHEN lc.change_type = 'adjustment' THEN rs.name WHEN lc.change_type = 'proxy' THEN NULL ELSE ps.name END effective_subject, "
            . 'lc.change_type, ats.id attendance_session_id, ats.status attendance_status, ar.status student_status, '
            . 'ar.minutes_late, ar.remarks FROM report_dates d '
            . 'INNER JOIN timetable_entries te ON te.day_of_week = WEEKDAY(d.lecture_date) + 1 '
            . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id INNER JOIN subjects ps ON ps.id = te.subject_id '
            . 'INNER JOIN teachers pt ON pt.id = te.teacher_id '
            . 'INNER JOIN enrollments e ON e.batch_id = te.batch_id AND e.academic_year_id = te.academic_year_id '
            . 'AND e.student_id = ? AND e.enrollment_date <= d.lecture_date AND (e.end_date IS NULL OR e.end_date >= d.lecture_date) '
            . 'LEFT JOIN lecture_occurrences lo ON lo.timetable_entry_id = te.id AND lo.lecture_date = d.lecture_date '
            . "LEFT JOIN lecture_changes lc ON lc.lecture_occurrence_id = lo.id AND lc.status = 'active' "
            . 'LEFT JOIN teachers rt ON rt.id = lc.replacement_teacher_id LEFT JOIN subjects rs ON rs.id = lc.replacement_subject_id '
            . 'LEFT JOIN attendance_sessions ats ON ats.lecture_occurrence_id = lo.id '
            . 'LEFT JOIN attendance_records ar ON ar.attendance_session_id = ats.id AND ar.enrollment_id = e.id '
            . 'WHERE te.academic_year_id = ?' . $where . " AND (lo.status IS NULL OR lo.status <> 'cancelled') "
            . 'ORDER BY d.lecture_date DESC, ls.slot_order DESC'
        );
        $statement->execute([$from, $to, $studentId, $academicYearId]);
        return $statement->fetchAll();
    }

    private function adminLectureAttendance(): never
    {
        $timetableEntryId = (int) ($this->request->query['timetable_entry_id'] ?? 0);
        $date = $this->requiredDate($this->request->query['date'] ?? null);
        if ($timetableEntryId < 1) {
            throw new ApiException(422, 'Timetable lecture is required.');
        }
        $context = $this->lectureContext($timetableEntryId, $date);
        $occurrence = $this->findLectureOccurrence($timetableEntryId, $date);
        $change = $occurrence ? $this->activeLectureChange((int) $occurrence['id']) : null;
        $session = null;
        if ($occurrence) {
            $sessionStatement = $this->db->prepare(
                'SELECT ats.*, marker.username marked_by, submitter.username submitted_by FROM attendance_sessions ats '
                . 'INNER JOIN users marker ON marker.id = ats.marked_by_user_id '
                . 'LEFT JOIN users submitter ON submitter.id = ats.submitted_by_user_id WHERE ats.lecture_occurrence_id = ?'
            );
            $sessionStatement->execute([$occurrence['id']]);
            $session = $sessionStatement->fetch() ?: null;
        }
        $students = $this->db->prepare(
            'SELECT e.id enrollment_id, st.id student_id, st.admission_no, st.name, ar.id attendance_record_id, '
            . 'ar.status, ar.minutes_late, ar.remarks FROM enrollments e INNER JOIN students st ON st.id = e.student_id '
            . 'LEFT JOIN attendance_records ar ON ar.enrollment_id = e.id AND ar.attendance_session_id = ? '
            . 'WHERE e.batch_id = ? AND e.enrollment_date <= ? AND (e.end_date IS NULL OR e.end_date >= ?) ORDER BY st.name'
        );
        $students->execute([(int) ($session['id'] ?? 0), $context['batch_id'], $date, $date]);
        $history = [];
        if ($session) {
            $historyStatement = $this->db->prepare(
                'SELECT h.*, u.username changed_by FROM attendance_record_history h '
                . 'INNER JOIN users u ON u.id = h.changed_by_user_id WHERE h.attendance_session_id = ? ORDER BY h.changed_at DESC, h.id DESC'
            );
            $historyStatement->execute([$session['id']]);
            $history = $historyStatement->fetchAll();
        }
        $effectiveTeacher = $context['teacher'];
        $effectiveSubject = $context['subject'];
        if ($change) {
            $teacher = $this->db->prepare('SELECT name FROM teachers WHERE id = ?');
            $teacher->execute([$change['replacement_teacher_id']]);
            $effectiveTeacher = (string) $teacher->fetchColumn();
            if ($change['change_type'] === 'proxy') {
                $effectiveSubject = null;
            } else {
                $subject = $this->db->prepare('SELECT name FROM subjects WHERE id = ?');
                $subject->execute([$change['replacement_subject_id']]);
                $effectiveSubject = (string) $subject->fetchColumn();
            }
        }
        Response::json(['data' => [
            'lecture' => [
                'timetable_entry_id' => (int) $context['id'], 'lecture_date' => $date, 'slot' => $context['slot'],
                'start_time' => $context['start_time'], 'end_time' => $context['end_time'], 'batch' => $context['batch'],
                'standard' => $context['standard'], 'planned_subject' => $context['subject'], 'planned_teacher' => $context['teacher'],
                'effective_subject' => $effectiveSubject, 'effective_teacher' => $effectiveTeacher,
                'change_type' => $change['change_type'] ?? null,
            ],
            'session' => $session,
            'students' => $students->fetchAll(),
            'history' => $history,
        ]]);
    }

    private function reportLectureStarted(array $row): bool
    {
        return $row['lecture_status'] !== 'cancelled'
            && new \DateTimeImmutable($row['lecture_date'] . ' ' . $row['start_time']) <= new \DateTimeImmutable('now');
    }

    private function lectureChanges(array $user, ?int $id): never
    {
        if ($this->request->method === 'GET' && $id === null) {
            $date = $this->requiredDate($this->request->query['date'] ?? null);
            $batchId = (int) ($this->request->query['batch_id'] ?? 0);
            if ($batchId < 1) {
                throw new ApiException(422, 'batch_id is required.');
            }
            $day = (int) (new \DateTimeImmutable($date))->format('N');
            $statement = $this->db->prepare(
                'SELECT te.id timetable_entry_id, te.batch_id, te.slot_id, te.subject_id planned_subject_id, '
                . 'te.teacher_id planned_teacher_id, b.name batch, s.name standard, sub.name planned_subject, '
                . 'pt.name planned_teacher, ls.name slot, ls.start_time, ls.end_time, lo.id lecture_occurrence_id, '
                . 'lo.status lecture_status, lc.id change_id, lc.change_type, lc.replacement_teacher_id, '
                . 'lc.replacement_subject_id, lc.reason, rt.name replacement_teacher, rs.name replacement_subject, '
                . 'ats.status attendance_status FROM timetable_entries te '
                . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
                . 'INNER JOIN subjects sub ON sub.id = te.subject_id INNER JOIN teachers pt ON pt.id = te.teacher_id '
                . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id '
                . 'LEFT JOIN lecture_occurrences lo ON lo.timetable_entry_id = te.id AND lo.lecture_date = ? '
                . "LEFT JOIN lecture_changes lc ON lc.lecture_occurrence_id = lo.id AND lc.status = 'active' "
                . 'LEFT JOIN teachers rt ON rt.id = lc.replacement_teacher_id '
                . 'LEFT JOIN subjects rs ON rs.id = lc.replacement_subject_id '
                . 'LEFT JOIN attendance_sessions ats ON ats.lecture_occurrence_id = lo.id '
                . 'WHERE te.batch_id = ? AND te.day_of_week = ? ORDER BY ls.slot_order'
            );
            $statement->execute([$date, $batchId, $day]);
            Response::json(['data' => $statement->fetchAll()]);
        }

        if ($this->request->method === 'POST' && $id === null) {
            $data = $this->request->json();
            $timetableEntryId = (int) ($data['timetable_entry_id'] ?? 0);
            $date = $this->requiredDate($data['lecture_date'] ?? null);
            $changeType = (string) ($data['change_type'] ?? '');
            $teacherId = (int) ($data['replacement_teacher_id'] ?? 0);
            $subjectId = (int) ($data['replacement_subject_id'] ?? 0);
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($timetableEntryId < 1 || !in_array($changeType, ['proxy', 'adjustment'], true) || $teacherId < 1) {
                throw new ApiException(422, 'Lecture, change type, and replacement teacher are required.');
            }
            if ($changeType === 'adjustment' && $subjectId < 1) {
                throw new ApiException(422, 'A replacement subject is required for an adjustment.');
            }
            if ($changeType === 'proxy') {
                $subjectId = 0;
            }

            $context = $this->lectureContext($timetableEntryId, $date);
            if ($changeType === 'proxy' && $teacherId === (int) $context['teacher_id']) {
                throw new ApiException(422, 'A proxy must be a different teacher from the planned teacher.');
            }
            if ($changeType === 'adjustment' && $subjectId === (int) $context['subject_id']) {
                throw new ApiException(422, 'An adjustment must assign a different subject.');
            }
            $teacher = $this->db->prepare(
                'SELECT t.id FROM teachers t INNER JOIN users u ON u.id = t.user_id '
                . 'WHERE t.id = ? AND u.is_active = 1'
            );
            $teacher->execute([$teacherId]);
            if (!$teacher->fetchColumn()) {
                throw new ApiException(422, 'Select an active replacement teacher.');
            }
            if ($changeType === 'adjustment') {
                $eligibility = $this->db->prepare(
                    'SELECT ts.id FROM teacher_subjects ts INNER JOIN standard_subjects ss ON ss.subject_id = ts.subject_id '
                    . 'WHERE ts.teacher_id = ? AND ts.subject_id = ? AND ss.standard_id = ?'
                );
                $eligibility->execute([$teacherId, $subjectId, $context['standard_id']]);
                if (!$eligibility->fetchColumn()) {
                    throw new ApiException(422, 'The teacher is not eligible for that subject in this standard.');
                }
            }

            $this->db->beginTransaction();
            try {
                $this->db->prepare('SELECT id FROM teachers WHERE id = ? FOR UPDATE')->execute([$teacherId]);
                if (!$this->teacherIsFree($teacherId, $date, (int) $context['slot_id'], $timetableEntryId)) {
                    throw new ApiException(409, 'The replacement teacher is already assigned during this lecture.');
                }
                $occurrence = $this->ensureLectureOccurrence($context, $date);
                $attendance = $this->db->prepare('SELECT status FROM attendance_sessions WHERE lecture_occurrence_id = ?');
                $attendance->execute([$occurrence['id']]);
                if ($attendance->fetchColumn()) {
                    throw new ApiException(409, 'A teacher change cannot be made after attendance has been opened.');
                }
                $active = $this->db->prepare("SELECT id FROM lecture_changes WHERE lecture_occurrence_id = ? AND status = 'active' FOR UPDATE");
                $active->execute([$occurrence['id']]);
                $supersedesId = (int) ($active->fetchColumn() ?: 0);
                if ($supersedesId > 0) {
                    $this->db->prepare("UPDATE lecture_changes SET status = 'superseded', cancelled_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$supersedesId]);
                }
                $insert = $this->db->prepare(
                    'INSERT INTO lecture_changes (lecture_occurrence_id, change_type, replacement_teacher_id, '
                    . 'replacement_subject_id, counts_towards_subject, reason, supersedes_change_id, created_by_user_id, approved_by_user_id) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $occurrence['id'], $changeType, $teacherId, $subjectId ?: null,
                    $changeType === 'adjustment' ? 1 : 0, $reason !== '' ? $reason : null,
                    $supersedesId ?: null, $user['id'], $user['id'],
                ]);
                $changeId = (int) $this->db->lastInsertId();
                $this->db->commit();
                Response::json(['data' => ['id' => $changeId], 'message' => ucfirst($changeType) . ' assigned.'], 201);
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }

        if ($this->request->method === 'DELETE' && $id !== null) {
            $statement = $this->db->prepare(
                "SELECT lc.id, lo.id occurrence_id, lo.timetable_entry_id, lo.lecture_date, lo.slot_id, lo.planned_teacher_id "
                . "FROM lecture_changes lc INNER JOIN lecture_occurrences lo ON lo.id = lc.lecture_occurrence_id "
                . "WHERE lc.id = ? AND lc.status = 'active'"
            );
            $statement->execute([$id]);
            $change = $statement->fetch();
            if (!$change) {
                throw new ApiException(404, 'Active lecture change not found.');
            }
            $attendance = $this->db->prepare('SELECT id FROM attendance_sessions WHERE lecture_occurrence_id = ?');
            $attendance->execute([$change['occurrence_id']]);
            if ($attendance->fetchColumn()) {
                throw new ApiException(409, 'This change cannot be removed after attendance has been opened.');
            }
            if (!$this->teacherIsFree((int) $change['planned_teacher_id'], $change['lecture_date'], (int) $change['slot_id'], (int) $change['timetable_entry_id'])) {
                throw new ApiException(409, 'The planned teacher is occupied, so this change cannot be removed.');
            }
            $this->db->prepare("UPDATE lecture_changes SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$id]);
            Response::json(['message' => 'Lecture change removed.']);
        }

        throw new ApiException(405, 'Method not allowed.');
    }

    private function teacherAttendanceLectures(array $user): never
    {
        $date = $this->requiredDate($this->request->query['date'] ?? null);
        $batchId = max(0, (int) ($this->request->query['batch_id'] ?? 0));
        $teacherId = $this->teacherIdForUser($user['id']);
        $day = (int) (new \DateTimeImmutable($date))->format('N');
        $statement = $this->db->prepare(
            'SELECT te.id timetable_entry_id, lo.id lecture_occurrence_id, te.batch_id, b.name batch, s.name standard, '
            . 'ls.id slot_id, ls.name slot, ls.start_time, ls.end_time, te.subject_id planned_subject_id, '
            . 'ps.name planned_subject, te.teacher_id planned_teacher_id, pt.name planned_teacher, '
            . 'lc.id change_id, lc.change_type, lc.replacement_teacher_id, rt.name replacement_teacher, '
            . 'lc.replacement_subject_id, rs.name replacement_subject, ats.id attendance_session_id, '
            . 'ats.status attendance_status, marker.username marked_by FROM timetable_entries te '
            . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN academic_years ay ON ay.id = te.academic_year_id '
            . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id INNER JOIN subjects ps ON ps.id = te.subject_id '
            . 'INNER JOIN teachers pt ON pt.id = te.teacher_id '
            . 'LEFT JOIN lecture_occurrences lo ON lo.timetable_entry_id = te.id AND lo.lecture_date = ? '
            . "LEFT JOIN lecture_changes lc ON lc.lecture_occurrence_id = lo.id AND lc.status = 'active' "
            . 'LEFT JOIN teachers rt ON rt.id = lc.replacement_teacher_id LEFT JOIN subjects rs ON rs.id = lc.replacement_subject_id '
            . 'LEFT JOIN attendance_sessions ats ON ats.lecture_occurrence_id = lo.id '
            . 'LEFT JOIN users marker ON marker.id = ats.marked_by_user_id '
            . 'WHERE te.day_of_week = ? AND ? BETWEEN ay.start_date AND ay.end_date '
            . 'AND COALESCE(lc.replacement_teacher_id, te.teacher_id) = ? '
            . 'AND (? = 0 OR te.batch_id = ?) '
            . "AND (lo.status IS NULL OR lo.status <> 'cancelled') ORDER BY ls.slot_order, b.name"
        );
        $statement->execute([$date, $day, $date, $teacherId, $batchId, $batchId]);
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function teacherAttendanceHistory(array $user): never
    {
        $filter = (string) ($this->request->query['filter'] ?? 'all');
        $batchId = max(0, (int) ($this->request->query['batch_id'] ?? 0));
        if (!in_array($filter, ['all', 'pending'], true)) {
            throw new ApiException(422, 'History filter must be all or pending.');
        }
        $teacherId = $this->teacherIdForUser($user['id']);
        $pendingClause = $filter === 'pending' ? "AND (ats.status IS NULL OR ats.status = 'draft') " : '';
        $statement = $this->db->prepare(
            'WITH RECURSIVE lecture_dates AS ('
            . 'SELECT CURRENT_DATE lecture_date UNION ALL '
            . 'SELECT lecture_date - INTERVAL 1 DAY FROM lecture_dates '
            . 'WHERE lecture_date > CURRENT_DATE - INTERVAL 365 DAY) '
            . 'SELECT te.id timetable_entry_id, lo.id lecture_occurrence_id, d.lecture_date, te.batch_id, '
            . 'b.name batch, s.name standard, ls.id slot_id, ls.name slot, ls.start_time, ls.end_time, '
            . 'te.subject_id planned_subject_id, ps.name planned_subject, te.teacher_id planned_teacher_id, '
            . 'pt.name planned_teacher, lc.id change_id, lc.change_type, lc.replacement_teacher_id, '
            . 'rt.name replacement_teacher, lc.replacement_subject_id, rs.name replacement_subject, '
            . 'ats.id attendance_session_id, ats.status attendance_status, marker.username marked_by '
            . 'FROM lecture_dates d INNER JOIN timetable_entries te ON te.day_of_week = WEEKDAY(d.lecture_date) + 1 '
            . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN academic_years ay ON ay.id = te.academic_year_id AND d.lecture_date BETWEEN ay.start_date AND ay.end_date '
            . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id INNER JOIN subjects ps ON ps.id = te.subject_id '
            . 'INNER JOIN teachers pt ON pt.id = te.teacher_id '
            . 'LEFT JOIN lecture_occurrences lo ON lo.timetable_entry_id = te.id AND lo.lecture_date = d.lecture_date '
            . "LEFT JOIN lecture_changes lc ON lc.lecture_occurrence_id = lo.id AND lc.status = 'active' "
            . 'LEFT JOIN teachers rt ON rt.id = lc.replacement_teacher_id LEFT JOIN subjects rs ON rs.id = lc.replacement_subject_id '
            . 'LEFT JOIN attendance_sessions ats ON ats.lecture_occurrence_id = lo.id '
            . 'LEFT JOIN users marker ON marker.id = ats.marked_by_user_id '
            . 'WHERE COALESCE(lc.replacement_teacher_id, te.teacher_id) = ? '
            . 'AND (? = 0 OR te.batch_id = ?) '
            . "AND (lo.status IS NULL OR lo.status <> 'cancelled') "
            . 'AND (d.lecture_date < CURRENT_DATE OR ls.start_time <= CURRENT_TIME) '
            . $pendingClause
            . 'ORDER BY d.lecture_date DESC, ls.slot_order DESC, b.name LIMIT 100'
        );
        $statement->execute([$teacherId, $batchId, $batchId]);
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function teacherAttendanceRoster(array $user): never
    {
        $timetableEntryId = (int) ($this->request->query['timetable_entry_id'] ?? 0);
        $date = $this->requiredDate($this->request->query['date'] ?? null);
        if ($timetableEntryId < 1) {
            throw new ApiException(422, 'timetable_entry_id is required.');
        }
        $context = $this->lectureContext($timetableEntryId, $date);
        $teacherId = $this->teacherIdForUser($user['id']);
        $occurrence = $this->findLectureOccurrence($timetableEntryId, $date);
        $change = $occurrence ? $this->activeLectureChange((int) $occurrence['id']) : null;
        $effectiveTeacherId = (int) ($change['replacement_teacher_id'] ?? $context['teacher_id']);
        if ($effectiveTeacherId !== $teacherId) {
            throw new ApiException(403, 'This lecture is not assigned to you.');
        }
        $session = null;
        if ($occurrence) {
            $sessionStatement = $this->db->prepare(
                'SELECT ats.*, u.username marked_by FROM attendance_sessions ats '
                . 'INNER JOIN users u ON u.id = ats.marked_by_user_id WHERE ats.lecture_occurrence_id = ?'
            );
            $sessionStatement->execute([$occurrence['id']]);
            $session = $sessionStatement->fetch() ?: null;
        }
        $statement = $this->db->prepare(
            'SELECT e.id enrollment_id, st.id student_id, st.admission_no, st.name, '
            . 'ar.status, ar.minutes_late, ar.remarks FROM enrollments e '
            . 'INNER JOIN students st ON st.id = e.student_id '
            . 'LEFT JOIN attendance_records ar ON ar.enrollment_id = e.id AND ar.attendance_session_id = ? '
            . 'WHERE e.batch_id = ? AND e.enrollment_date <= ? AND (e.end_date IS NULL OR e.end_date >= ?) '
            . 'ORDER BY st.name'
        );
        $statement->execute([(int) ($session['id'] ?? 0), $context['batch_id'], $date, $date]);
        Response::json([
            'data' => [
                'lecture' => $this->attendanceLectureData($context, $change, $date),
                'session' => $session,
                'students' => $statement->fetchAll(),
            ],
        ]);
    }

    private function saveTeacherAttendance(array $user, bool $submit): never
    {
        $data = $this->request->json();
        $timetableEntryId = (int) ($data['timetable_entry_id'] ?? 0);
        $date = $this->requiredDate($data['lecture_date'] ?? null);
        $records = $data['records'] ?? null;
        if ($timetableEntryId < 1 || !is_array($records)) {
            throw new ApiException(422, 'Lecture date, timetable entry, and attendance records are required.');
        }
        if ($date > (new \DateTimeImmutable('today'))->format('Y-m-d')) {
            throw new ApiException(422, 'Attendance cannot be marked for a future lecture.');
        }
        $teacherId = $this->teacherIdForUser($user['id']);
        $context = $this->lectureContext($timetableEntryId, $date);
        $lectureStartsAt = new \DateTimeImmutable($date . ' ' . $context['start_time']);
        if (new \DateTimeImmutable('now') < $lectureStartsAt) {
            throw new ApiException(422, 'Attendance can be marked only after the lecture starts.');
        }

        $this->db->beginTransaction();
        try {
            $occurrence = $this->ensureLectureOccurrence($context, $date);
            $change = $this->activeLectureChange((int) $occurrence['id']);
            $effectiveTeacherId = (int) ($change['replacement_teacher_id'] ?? $context['teacher_id']);
            if ($effectiveTeacherId !== $teacherId) {
                throw new ApiException(403, 'Only the teacher assigned to this lecture can mark attendance.');
            }

            $sessionStatement = $this->db->prepare('SELECT * FROM attendance_sessions WHERE lecture_occurrence_id = ? FOR UPDATE');
            $sessionStatement->execute([$occurrence['id']]);
            $session = $sessionStatement->fetch();
            if ($session && in_array($session['status'], ['submitted', 'locked'], true)) {
                throw new ApiException(409, 'Attendance has already been submitted and locked.');
            }
            if (!$session) {
                $this->db->prepare(
                    "INSERT INTO attendance_sessions (lecture_occurrence_id, marked_by_user_id, marked_by_teacher_id, marking_role) VALUES (?, ?, ?, 'teacher')"
                )->execute([$occurrence['id'], $user['id'], $teacherId]);
                $sessionId = (int) $this->db->lastInsertId();
            } else {
                $sessionId = (int) $session['id'];
            }

            $eligibleStatement = $this->db->prepare(
                'SELECT id FROM enrollments WHERE batch_id = ? AND enrollment_date <= ? '
                . 'AND (end_date IS NULL OR end_date >= ?) ORDER BY id'
            );
            $eligibleStatement->execute([$context['batch_id'], $date, $date]);
            $eligibleIds = array_map('intval', $eligibleStatement->fetchAll(PDO::FETCH_COLUMN));
            $eligibleLookup = array_fill_keys($eligibleIds, true);
            $provided = [];
            foreach ($records as $record) {
                if (!is_array($record)) {
                    throw new ApiException(422, 'Each attendance record must be an object.');
                }
                $enrollmentId = (int) ($record['enrollment_id'] ?? 0);
                $status = (string) ($record['status'] ?? '');
                if (!isset($eligibleLookup[$enrollmentId]) || isset($provided[$enrollmentId])) {
                    throw new ApiException(422, 'Attendance contains an ineligible or duplicate enrollment.');
                }
                if (!in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                    throw new ApiException(422, 'Select a valid attendance status for every included student.');
                }
                $minutesLate = $status === 'late' ? (int) ($record['minutes_late'] ?? 0) : null;
                if ($status === 'late' && $minutesLate < 1) {
                    throw new ApiException(422, 'Minutes late is required when a student is marked late.');
                }
                $remarks = trim((string) ($record['remarks'] ?? ''));
                $provided[$enrollmentId] = true;
                $this->upsertAttendanceRecord(
                    $sessionId, $enrollmentId, $status, $minutesLate,
                    $remarks !== '' ? $remarks : null, (int) $user['id']
                );
            }
            if ($submit && count($provided) !== count($eligibleIds)) {
                throw new ApiException(422, 'Mark every eligible student before submitting attendance.');
            }
            if ($submit) {
                $this->db->prepare(
                    "UPDATE attendance_sessions SET status = 'locked', submitted_at = CURRENT_TIMESTAMP, submitted_by_user_id = ?, "
                    . 'locked_at = CURRENT_TIMESTAMP, locked_by_user_id = ? WHERE id = ?'
                )->execute([$user['id'], $user['id'], $sessionId]);
                $this->db->prepare("UPDATE lecture_occurrences SET status = 'conducted' WHERE id = ?")
                    ->execute([$occurrence['id']]);
            }
            $this->db->commit();
            Response::json(['message' => $submit ? 'Attendance submitted and locked.' : 'Attendance draft saved.']);
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function upsertAttendanceRecord(
        int $sessionId,
        int $enrollmentId,
        string $status,
        ?int $minutesLate,
        ?string $remarks,
        int $userId,
        ?string $historyAction = null,
        ?string $changeReason = null
    ): void {
        $statement = $this->db->prepare(
            'SELECT id, status, minutes_late, remarks FROM attendance_records '
            . 'WHERE attendance_session_id = ? AND enrollment_id = ? FOR UPDATE'
        );
        $statement->execute([$sessionId, $enrollmentId]);
        $existing = $statement->fetch();
        if ($existing) {
            if ($existing['status'] === $status
                && ($existing['minutes_late'] === null ? null : (int) $existing['minutes_late']) === $minutesLate
                && $existing['remarks'] === $remarks) {
                return;
            }
            $this->db->prepare(
                'UPDATE attendance_records SET status = ?, minutes_late = ?, remarks = ?, last_updated_by_user_id = ? WHERE id = ?'
            )->execute([$status, $minutesLate, $remarks, $userId, $existing['id']]);
            $recordId = (int) $existing['id'];
            $action = $historyAction ?? 'changed';
        } else {
            $this->db->prepare(
                'INSERT INTO attendance_records (attendance_session_id, enrollment_id, status, minutes_late, remarks, marked_by_user_id, last_updated_by_user_id) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$sessionId, $enrollmentId, $status, $minutesLate, $remarks, $userId, $userId]);
            $recordId = (int) $this->db->lastInsertId();
            $action = $historyAction ?? 'created';
        }
        $this->db->prepare(
            'INSERT INTO attendance_record_history (attendance_record_id, attendance_session_id, enrollment_id, '
            . 'previous_status, new_status, previous_minutes_late, new_minutes_late, previous_remarks, new_remarks, action, changed_by_user_id, change_reason) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $recordId, $sessionId, $enrollmentId, $existing['status'] ?? null, $status,
            $existing['minutes_late'] ?? null, $minutesLate, $existing['remarks'] ?? null, $remarks, $action, $userId, $changeReason,
        ]);
    }

    private function lectureContext(int $timetableEntryId, string $date): array
    {
        $statement = $this->db->prepare(
            'SELECT te.*, b.standard_id, b.name batch, s.name standard, ay.name academic_year, ay.start_date, ay.end_date, '
            . 'sub.name subject, t.name teacher, ls.name slot, ls.start_time, ls.end_time FROM timetable_entries te '
            . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN academic_years ay ON ay.id = te.academic_year_id INNER JOIN subjects sub ON sub.id = te.subject_id '
            . 'INNER JOIN teachers t ON t.id = te.teacher_id INNER JOIN lecture_slots ls ON ls.id = te.slot_id WHERE te.id = ?'
        );
        $statement->execute([$timetableEntryId]);
        $context = $statement->fetch();
        if (!$context) {
            throw new ApiException(404, 'Timetable lecture not found.');
        }
        if ($date < $context['start_date'] || $date > $context['end_date']) {
            throw new ApiException(422, 'Lecture date is outside the academic year.');
        }
        if ((int) (new \DateTimeImmutable($date))->format('N') !== (int) $context['day_of_week']) {
            throw new ApiException(422, 'The selected lecture does not occur on that date.');
        }
        return $context;
    }

    private function ensureLectureOccurrence(array $context, string $date): array
    {
        $existing = $this->findLectureOccurrence((int) $context['id'], $date);
        if ($existing) {
            return $existing;
        }
        $this->db->prepare(
            'INSERT INTO lecture_occurrences (timetable_entry_id, academic_year_id, batch_id, lecture_date, slot_id, planned_subject_id, planned_teacher_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $context['id'], $context['academic_year_id'], $context['batch_id'], $date,
            $context['slot_id'], $context['subject_id'], $context['teacher_id'],
        ]);
        return ['id' => (int) $this->db->lastInsertId(), 'timetable_entry_id' => (int) $context['id']];
    }

    private function findLectureOccurrence(int $timetableEntryId, string $date): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM lecture_occurrences WHERE timetable_entry_id = ? AND lecture_date = ?');
        $statement->execute([$timetableEntryId, $date]);
        return $statement->fetch() ?: null;
    }

    private function activeLectureChange(int $occurrenceId): ?array
    {
        $statement = $this->db->prepare("SELECT * FROM lecture_changes WHERE lecture_occurrence_id = ? AND status = 'active'");
        $statement->execute([$occurrenceId]);
        return $statement->fetch() ?: null;
    }

    private function teacherIsFree(int $teacherId, string $date, int $slotId, int $excludeTimetableEntryId): bool
    {
        $day = (int) (new \DateTimeImmutable($date))->format('N');
        $statement = $this->db->prepare(
            'SELECT te.id FROM timetable_entries te INNER JOIN academic_years ay ON ay.id = te.academic_year_id '
            . 'LEFT JOIN lecture_occurrences lo ON lo.timetable_entry_id = te.id AND lo.lecture_date = ? '
            . "LEFT JOIN lecture_changes lc ON lc.lecture_occurrence_id = lo.id AND lc.status = 'active' "
            . 'WHERE te.day_of_week = ? AND te.slot_id = ? AND ? BETWEEN ay.start_date AND ay.end_date '
            . 'AND te.id <> ? AND COALESCE(lc.replacement_teacher_id, te.teacher_id) = ? '
            . "AND (lo.status IS NULL OR lo.status <> 'cancelled') LIMIT 1"
        );
        $statement->execute([$date, $day, $slotId, $date, $excludeTimetableEntryId, $teacherId]);
        return !$statement->fetchColumn();
    }

    private function teacherIdForUser(int $userId): int
    {
        $statement = $this->db->prepare('SELECT id FROM teachers WHERE user_id = ?');
        $statement->execute([$userId]);
        $teacherId = (int) ($statement->fetchColumn() ?: 0);
        if ($teacherId < 1) {
            throw new ApiException(404, 'No teacher profile is linked to this account.');
        }
        return $teacherId;
    }

    private function requiredDate(mixed $value): string
    {
        $date = trim((string) $value);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new ApiException(422, 'A valid date in YYYY-MM-DD format is required.');
        }
        return $date;
    }

    private function attendanceLectureData(array $context, ?array $change, string $date): array
    {
        $subjectId = (int) ($change['replacement_subject_id'] ?? $context['subject_id']);
        $subjectName = $context['subject'];
        if ($change && $change['change_type'] === 'adjustment') {
            $statement = $this->db->prepare('SELECT name FROM subjects WHERE id = ?');
            $statement->execute([$subjectId]);
            $subjectName = (string) $statement->fetchColumn();
        }
        return [
            'timetable_entry_id' => (int) $context['id'],
            'lecture_date' => $date,
            'batch_id' => (int) $context['batch_id'],
            'batch' => $context['batch'],
            'standard' => $context['standard'],
            'slot' => $context['slot'],
            'start_time' => $context['start_time'],
            'end_time' => $context['end_time'],
            'subject_id' => $subjectId,
            'subject' => $subjectName,
            'change_type' => $change['change_type'] ?? null,
        ];
    }

    private function teacherHomework(array $user, ?int $id): never
    {
        $teacherId = $this->teacherIdForUser((int) $user['id']);
        if ($this->request->method === 'GET') {
            $batchFilter = max(0, (int) ($this->request->query['batch_id'] ?? 0));
            $statement = $this->db->prepare(
                "SELECT h.*, b.name batch, s.name standard, COALESCE(sub.name, 'Other') subject, sub.code subject_code, ay.name academic_year, "
                . "COUNT(DISTINCT e.student_id) eligible_students, "
                . "COUNT(DISTINCT CASE WHEN hs.status IN ('submitted','late','resubmitted','graded') THEN hs.student_id END) submitted_students "
                . 'FROM homework h INNER JOIN batches b ON b.id = h.batch_id INNER JOIN standards s ON s.id = b.standard_id '
                . 'LEFT JOIN subjects sub ON sub.id = h.subject_id INNER JOIN academic_years ay ON ay.id = h.academic_year_id '
                . 'LEFT JOIN enrollments e ON e.batch_id = h.batch_id AND e.enrollment_date <= DATE(h.due_at) '
                . 'AND (e.end_date IS NULL OR e.end_date >= DATE(h.assigned_at)) '
                . 'LEFT JOIN homework_submissions hs ON hs.homework_id = h.id AND hs.student_id = e.student_id '
                . 'WHERE h.assigned_by_teacher_id = ? AND (? = 0 OR h.batch_id = ?) ' . ($id !== null ? 'AND h.id = ? ' : '')
                . 'GROUP BY h.id ORDER BY h.due_at DESC, h.id DESC'
            );
            $statement->execute($id !== null ? [$teacherId, $batchFilter, $batchFilter, $id] : [$teacherId, $batchFilter, $batchFilter]);
            $rows = $statement->fetchAll();
            if ($id !== null && $rows === []) {
                throw new ApiException(404, 'Homework not found.');
            }
            Response::json(['data' => $id !== null ? $rows[0] : $rows]);
        }

        $data = $this->request->json();
        if ($this->request->method === 'POST' && $id === null) {
            $batchId = (int) ($data['batch_id'] ?? 0);
            $subjectValue = $data['subject_id'] ?? null;
            $subjectId = $subjectValue === null || $subjectValue === '' || $subjectValue === 'other' ? null : (int) $subjectValue;
            $title = trim((string) ($data['title'] ?? ''));
            $instructions = trim((string) ($data['instructions'] ?? ''));
            $dueAt = $this->requiredDateTime($data['due_at'] ?? null);
            $status = (string) ($data['status'] ?? 'draft');
            $submissionType = (string) ($data['submission_type'] ?? 'online');
            if ($batchId < 1 || ($subjectId !== null && $subjectId < 1) || $title === '' || $instructions === '') {
                throw new ApiException(422, 'Batch, title and instructions are required.');
            }
            if (!in_array($status, ['draft', 'published'], true) || !in_array($submissionType, ['online', 'offline', 'both'], true)) {
                throw new ApiException(422, 'Invalid homework status or submission type.');
            }
            $context = $this->teacherHomeworkContext($teacherId, $batchId, $subjectId);
            if (new \DateTimeImmutable($dueAt) <= new \DateTimeImmutable()) {
                throw new ApiException(422, 'The due date must be in the future.');
            }
            if (substr($dueAt, 0, 10) < $context['start_date'] || substr($dueAt, 0, 10) > $context['end_date']) {
                throw new ApiException(422, 'The due date must be within the academic year.');
            }
            $maxMarks = $data['max_marks'] ?? null;
            if ($maxMarks !== null && $maxMarks !== '' && (float) $maxMarks <= 0) {
                throw new ApiException(422, 'Maximum marks must be greater than zero.');
            }
            $statement = $this->db->prepare(
                'INSERT INTO homework (academic_year_id, batch_id, subject_id, assigned_by_teacher_id, title, instructions, due_at, max_marks, submission_type, allow_resubmission, status, published_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $context['academic_year_id'], $batchId, $subjectId, $teacherId, $title, $instructions, $dueAt,
                $maxMarks === '' ? null : $maxMarks, $submissionType, (int) (bool) ($data['allow_resubmission'] ?? false),
                $status, $status === 'published' ? date('Y-m-d H:i:s') : null,
            ]);
            Response::json(['data' => ['id' => (int) $this->db->lastInsertId()], 'message' => 'Homework created successfully.'], 201);
        }

        if ($this->request->method === 'PATCH' && $id !== null) {
            $current = $this->teacherOwnedHomework($teacherId, $id);
            $allowed = ['title', 'instructions', 'due_at', 'max_marks', 'submission_type', 'allow_resubmission', 'status'];
            $values = array_intersect_key($data, array_flip($allowed));
            if ($values === []) {
                throw new ApiException(422, 'No valid fields were supplied.');
            }
            if (isset($values['status']) && !in_array($values['status'], ['draft', 'published', 'closed', 'cancelled'], true)) {
                throw new ApiException(422, 'Invalid homework status.');
            }
            if (($values['status'] ?? null) === 'draft' && $current['status'] !== 'draft') {
                throw new ApiException(422, 'Published homework cannot be moved back to draft.');
            }
            if (isset($values['submission_type']) && !in_array($values['submission_type'], ['online', 'offline', 'both'], true)) {
                throw new ApiException(422, 'Invalid submission type.');
            }
            if (isset($values['due_at'])) {
                $values['due_at'] = $this->requiredDateTime($values['due_at']);
                if (substr($values['due_at'], 0, 10) < $current['academic_year_start'] || substr($values['due_at'], 0, 10) > $current['academic_year_end']) {
                    throw new ApiException(422, 'The due date must be within the academic year.');
                }
            }
            if (array_key_exists('allow_resubmission', $values)) {
                $values['allow_resubmission'] = (int) (bool) $values['allow_resubmission'];
            }
            if (($values['status'] ?? null) === 'published' && !$current['published_at']) {
                $values['published_at'] = date('Y-m-d H:i:s');
            }
            $sets = array_map(static fn (string $field): string => "`$field` = ?", array_keys($values));
            $this->db->prepare('UPDATE homework SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute([...array_values($values), $id]);
            Response::json(['message' => 'Homework updated successfully.']);
        }
        throw new ApiException(405, 'Method not allowed.');
    }

    private function teacherHomeworkSubmissions(array $user, int $homeworkId, ?int $submissionId): never
    {
        $teacherId = $this->teacherIdForUser((int) $user['id']);
        $homework = $this->teacherOwnedHomework($teacherId, $homeworkId);
        if ($this->request->method === 'GET' && $submissionId === null) {
            $statement = $this->db->prepare(
                'SELECT st.id student_id, st.admission_no, st.name, hs.id submission_id, hs.attempt_no, hs.response_text, '
                . 'hs.status, hs.submitted_at, hs.marks, hs.feedback FROM enrollments e INNER JOIN students st ON st.id = e.student_id '
                . 'LEFT JOIN homework_submissions hs ON hs.id = (SELECT MAX(latest.id) FROM homework_submissions latest WHERE latest.homework_id = ? AND latest.student_id = st.id) '
                . 'WHERE e.batch_id = ? AND e.enrollment_date <= DATE(?) AND (e.end_date IS NULL OR e.end_date >= DATE(?)) ORDER BY st.name'
            );
            $statement->execute([$homeworkId, $homework['batch_id'], $homework['due_at'], $homework['assigned_at']]);
            Response::json(['data' => ['homework' => $homework, 'students' => $statement->fetchAll()]]);
        }
        if ($this->request->method === 'PATCH' && $submissionId !== null) {
            $data = $this->request->json();
            $marks = $data['marks'] ?? null;
            if ($marks !== null && $marks !== '' && ((float) $marks < 0 || ($homework['max_marks'] !== null && (float) $marks > (float) $homework['max_marks']))) {
                throw new ApiException(422, 'Marks must be within the homework maximum.');
            }
            $statement = $this->db->prepare(
                "UPDATE homework_submissions SET marks = ?, feedback = ?, status = 'graded', graded_by_teacher_id = ?, graded_at = NOW() "
                . 'WHERE id = ? AND homework_id = ?'
            );
            $statement->execute([$marks === '' ? null : $marks, trim((string) ($data['feedback'] ?? '')), $teacherId, $submissionId, $homeworkId]);
            if ($statement->rowCount() === 0) {
                throw new ApiException(404, 'Submission not found.');
            }
            Response::json(['message' => 'Submission graded successfully.']);
        }
        throw new ApiException(405, 'Method not allowed.');
    }

    private function studentHomework(array $user, ?int $id): never
    {
        if ($this->request->method !== 'GET') {
            throw new ApiException(405, 'Method not allowed.');
        }
        $studentId = $this->studentIdForUser((int) $user['id']);
        $statement = $this->db->prepare(
            "SELECT h.*, b.name batch, s.name standard, COALESCE(sub.name, 'Other') subject, t.name teacher, ay.name academic_year, "
            . 'hs.id submission_id, hs.attempt_no, hs.response_text, hs.status submission_status, hs.submitted_at, hs.marks, hs.feedback '
            . 'FROM homework h INNER JOIN batches b ON b.id = h.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'LEFT JOIN subjects sub ON sub.id = h.subject_id INNER JOIN teachers t ON t.id = h.assigned_by_teacher_id '
            . 'INNER JOIN academic_years ay ON ay.id = h.academic_year_id INNER JOIN enrollments e ON e.batch_id = h.batch_id AND e.student_id = ? '
            . 'LEFT JOIN homework_submissions hs ON hs.id = (SELECT MAX(latest.id) FROM homework_submissions latest WHERE latest.homework_id = h.id AND latest.student_id = ?) '
            . "WHERE h.status IN ('published','closed') AND e.enrollment_date <= DATE(h.due_at) AND (e.end_date IS NULL OR e.end_date >= DATE(h.assigned_at)) "
            . ($id !== null ? 'AND h.id = ? ' : '') . 'ORDER BY h.due_at ASC, h.id DESC'
        );
        $statement->execute($id !== null ? [$studentId, $studentId, $id] : [$studentId, $studentId]);
        $rows = $statement->fetchAll();
        if ($id !== null && $rows === []) {
            throw new ApiException(404, 'Homework not found.');
        }
        Response::json(['data' => $id !== null ? $rows[0] : $rows]);
    }

    private function studentHomeworkSubmit(array $user, int $homeworkId): never
    {
        if ($this->request->method !== 'POST') {
            throw new ApiException(405, 'Method not allowed.');
        }
        $studentId = $this->studentIdForUser((int) $user['id']);
        $homeworkStatement = $this->db->prepare(
            "SELECT h.* FROM homework h INNER JOIN enrollments e ON e.batch_id = h.batch_id AND e.student_id = ? "
            . "WHERE h.id = ? AND h.status = 'published' AND e.enrollment_date <= DATE(h.due_at) "
            . 'AND (e.end_date IS NULL OR e.end_date >= DATE(h.assigned_at)) LIMIT 1'
        );
        $homeworkStatement->execute([$studentId, $homeworkId]);
        $homework = $homeworkStatement->fetch();
        if (!$homework) {
            throw new ApiException(404, 'Open homework not found for this student.');
        }
        if ($homework['submission_type'] === 'offline') {
            throw new ApiException(422, 'This homework is submitted offline.');
        }
        $data = $this->request->json();
        $response = trim((string) ($data['response_text'] ?? ''));
        $saveDraft = (bool) ($data['draft'] ?? false);
        if (!$saveDraft && $response === '') {
            throw new ApiException(422, 'A response is required before submission.');
        }
        $latestStatement = $this->db->prepare('SELECT * FROM homework_submissions WHERE homework_id = ? AND student_id = ? ORDER BY attempt_no DESC LIMIT 1');
        $latestStatement->execute([$homeworkId, $studentId]);
        $latest = $latestStatement->fetch();
        if ($latest && $latest['status'] !== 'draft' && !(bool) $homework['allow_resubmission']) {
            throw new ApiException(409, 'This homework has already been submitted.');
        }
        $late = new \DateTimeImmutable() > new \DateTimeImmutable($homework['due_at']);
        $status = $saveDraft ? 'draft' : ($late ? 'late' : (($latest && $latest['status'] !== 'draft') ? 'resubmitted' : 'submitted'));
        if ($latest && $latest['status'] === 'draft') {
            $statement = $this->db->prepare('UPDATE homework_submissions SET response_text = ?, status = ?, submitted_at = ? WHERE id = ?');
            $statement->execute([$response, $status, $saveDraft ? null : date('Y-m-d H:i:s'), $latest['id']]);
        } else {
            $attempt = $latest ? (int) $latest['attempt_no'] + 1 : 1;
            $statement = $this->db->prepare(
                'INSERT INTO homework_submissions (homework_id, student_id, submitted_by_user_id, attempt_no, response_text, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([$homeworkId, $studentId, $user['id'], $attempt, $response, $status, $saveDraft ? null : date('Y-m-d H:i:s')]);
        }
        Response::json(['message' => $saveDraft ? 'Draft saved.' : 'Homework submitted successfully.']);
    }

    private function adminHomework(): never
    {
        $statement = $this->db->query(
            "SELECT h.*, b.name batch, s.name standard, COALESCE(sub.name, 'Other') subject, t.name teacher, ay.name academic_year, "
            . "COUNT(DISTINCT e.student_id) eligible_students, COUNT(DISTINCT CASE WHEN hs.status IN ('submitted','late','resubmitted','graded') THEN hs.student_id END) submitted_students "
            . 'FROM homework h INNER JOIN batches b ON b.id = h.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'LEFT JOIN subjects sub ON sub.id = h.subject_id INNER JOIN teachers t ON t.id = h.assigned_by_teacher_id '
            . 'INNER JOIN academic_years ay ON ay.id = h.academic_year_id '
            . 'LEFT JOIN enrollments e ON e.batch_id = h.batch_id AND e.enrollment_date <= DATE(h.due_at) AND (e.end_date IS NULL OR e.end_date >= DATE(h.assigned_at)) '
            . 'LEFT JOIN homework_submissions hs ON hs.homework_id = h.id AND hs.student_id = e.student_id '
            . 'GROUP BY h.id ORDER BY h.due_at DESC, h.id DESC LIMIT 500'
        );
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function teacherHomeworkContext(int $teacherId, int $batchId, ?int $subjectId): array
    {
        $statement = $this->db->prepare(
            'SELECT te.academic_year_id, ay.start_date, ay.end_date FROM timetable_entries te INNER JOIN academic_years ay ON ay.id = te.academic_year_id '
            . 'WHERE te.teacher_id = ? AND te.batch_id = ? AND (? IS NULL OR te.subject_id = ?) LIMIT 1'
        );
        $statement->execute([$teacherId, $batchId, $subjectId, $subjectId]);
        $context = $statement->fetch();
        if (!$context) {
            throw new ApiException(403, 'You are not scheduled to teach the selected batch or subject.');
        }
        return $context;
    }

    private function teacherOwnedHomework(int $teacherId, int $homeworkId): array
    {
        $statement = $this->db->prepare(
            'SELECT h.*, ay.start_date academic_year_start, ay.end_date academic_year_end FROM homework h INNER JOIN academic_years ay ON ay.id = h.academic_year_id '
            . 'WHERE h.id = ? AND h.assigned_by_teacher_id = ? LIMIT 1'
        );
        $statement->execute([$homeworkId, $teacherId]);
        $homework = $statement->fetch();
        if (!$homework) {
            throw new ApiException(404, 'Homework not found.');
        }
        return $homework;
    }

    private function studentIdForUser(int $userId): int
    {
        $statement = $this->db->prepare('SELECT id FROM students WHERE user_id = ?');
        $statement->execute([$userId]);
        $studentId = (int) ($statement->fetchColumn() ?: 0);
        if ($studentId < 1) {
            throw new ApiException(404, 'No student profile is linked to this account.');
        }
        return $studentId;
    }

    private function requiredDateTime(mixed $value): string
    {
        $dateTime = str_replace('T', ' ', trim((string) $value));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dateTime)
            ?: \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateTime);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ApiException(422, 'A valid date and time are required.');
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    private function teacherSchedule(array $user): never
    {
        $statement = $this->db->prepare(
            'SELECT te.id, te.batch_id, te.day_of_week, b.name batch, s.name standard, '
            . 'sub.id subject_id, sub.name subject, ay.id academic_year_id, ay.name academic_year, '
            . 'ay.start_date academic_year_start, ay.end_date academic_year_end, '
            . 'ls.id slot_id, ls.name slot, ls.start_time, ls.end_time FROM teachers t '
            . 'INNER JOIN timetable_entries te ON te.teacher_id = t.id INNER JOIN batches b ON b.id = te.batch_id '
            . 'INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN subjects sub ON sub.id = te.subject_id '
            . 'INNER JOIN academic_years ay ON ay.id = te.academic_year_id '
            . 'INNER JOIN lecture_slots ls ON ls.id = te.slot_id WHERE t.user_id = ? '
            . 'ORDER BY ay.start_date DESC, te.day_of_week, ls.slot_order'
        );
        $statement->execute([$user['id']]);
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function teacherTeachingScope(array $user): never
    {
        $teacherId = $this->teacherIdForUser((int) $user['id']);
        $statement = $this->db->prepare(
            "SELECT ay.id academic_year_id, ay.name academic_year, ay.start_date, ay.end_date, ay.is_active, "
            . "s.id standard_id, s.name standard, b.id batch_id, b.name batch, "
            . "GROUP_CONCAT(DISTINCT sub.name ORDER BY sub.name SEPARATOR ', ') subjects, "
            . "COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.student_id END) student_count, "
            . "COUNT(DISTINCT CASE WHEN h.status = 'published' THEN h.id END) active_homework "
            . 'FROM timetable_entries te INNER JOIN academic_years ay ON ay.id = te.academic_year_id '
            . 'INNER JOIN batches b ON b.id = te.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN subjects sub ON sub.id = te.subject_id '
            . 'LEFT JOIN enrollments e ON e.batch_id = b.id AND e.academic_year_id = ay.id '
            . 'LEFT JOIN homework h ON h.batch_id = b.id AND h.assigned_by_teacher_id = ? '
            . 'WHERE te.teacher_id = ? GROUP BY ay.id, s.id, b.id '
            . 'ORDER BY ay.start_date DESC, s.name, b.name'
        );
        $statement->execute([$teacherId, $teacherId]);
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function teacherStudents(array $user): never
    {
        $batchId = (int) ($this->request->query['batch_id'] ?? 0);
        if ($batchId < 1) {
            throw new ApiException(422, 'batch_id is required.');
        }
        $statement = $this->db->prepare(
            'SELECT DISTINCT st.id, st.admission_no, st.name, st.date_of_birth, st.gender, st.phone, st.email, e.status '
            . 'FROM teachers t INNER JOIN timetable_entries te ON te.teacher_id = t.id '
            . 'INNER JOIN enrollments e ON e.batch_id = te.batch_id AND e.academic_year_id = te.academic_year_id '
            . 'INNER JOIN students st ON st.id = e.student_id WHERE t.user_id = ? AND te.batch_id = ? ORDER BY st.name'
        );
        $statement->execute([$user['id'], $batchId]);
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function studentProfile(array $user): never
    {
        $statement = $this->db->prepare('SELECT id, admission_no, name, date_of_birth, gender, phone, email, address, created_at, updated_at FROM students WHERE user_id = ?');
        $statement->execute([$user['id']]);
        $student = $statement->fetch();
        if (!$student) {
            throw new ApiException(404, 'No student profile is linked to this account.');
        }
        Response::json(['data' => $student]);
    }

    private function studentEnrollments(array $user): never
    {
        $statement = $this->db->prepare(
            'SELECT e.id, e.enrollment_date, e.status, b.id batch_id, b.name batch, s.id standard_id, s.name standard, '
            . 'ay.id academic_year_id, ay.name academic_year FROM students st INNER JOIN enrollments e ON e.student_id = st.id '
            . 'INNER JOIN batches b ON b.id = e.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN academic_years ay ON ay.id = e.academic_year_id WHERE st.user_id = ? ORDER BY ay.start_date DESC'
        );
        $statement->execute([$user['id']]);
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function studentFees(array $user): never
    {
        $statement = $this->db->prepare(
            'SELECT e.id enrollment_id, ay.name academic_year, s.name standard, sf.total_amount_payable, '
            . 'COALESCE(SUM(fp.amount), 0) amount_paid, sf.total_amount_payable - COALESCE(SUM(fp.amount), 0) balance '
            . 'FROM students st INNER JOIN enrollments e ON e.student_id = st.id '
            . 'INNER JOIN batches b ON b.id = e.batch_id INNER JOIN standards s ON s.id = b.standard_id '
            . 'INNER JOIN academic_years ay ON ay.id = e.academic_year_id '
            . 'LEFT JOIN standard_fees sf ON sf.standard_id = b.standard_id AND sf.academic_year_id = e.academic_year_id '
            . 'LEFT JOIN fee_payments fp ON fp.enrollment_id = e.id WHERE st.user_id = ? '
            . 'GROUP BY e.id, ay.name, s.name, sf.total_amount_payable ORDER BY e.id DESC'
        );
        $statement->execute([$user['id']]);
        Response::json(['data' => $statement->fetchAll()]);
    }

    private function normalizeRoles(mixed $roles): array
    {
        if (!is_array($roles)) {
            return [];
        }
        $allowed = ['admin', 'teacher', 'student'];
        return array_values(array_unique(array_intersect($allowed, array_map(static fn ($role): string => strtolower(trim((string) $role)), $roles))));
    }

    private function syncRoles(int $userId, array $roles): void
    {
        $this->db->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        $statement = $this->db->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?');
        foreach ($roles as $role) {
            $statement->execute([$userId, $role]);
        }
    }

    private function userRoles(int $userId): array
    {
        $statement = $this->db->prepare('SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.name');
        $statement->execute([$userId]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function handleDatabaseException(PDOException $exception): ApiException
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        if ($sqlState === '23000') {
            if ($driverCode === 1062 && str_contains(strtolower((string) ($exception->errorInfo[2] ?? '')), 'receipt_no')) {
                return new ApiException(409, 'This receipt number has already been used.');
            }
            if ($driverCode === 1452) {
                return new ApiException(422, 'A selected item is no longer available. Refresh the page and try again.');
            }
            if ($driverCode === 3819) {
                return new ApiException(422, 'One of the entered values is not allowed.');
            }
            return new ApiException(409, 'The operation conflicts with an existing or related record.');
        }
        if ($sqlState === '45000') {
            return new ApiException(422, $exception->errorInfo[2] ?? 'The record is inconsistent.');
        }
        return new ApiException(500, 'A database error occurred.');
    }
}
