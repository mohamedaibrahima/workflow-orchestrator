# Dynamic Workflow Orchestrator

A full-stack workflow orchestration system built with Laravel 11 and Vue 3. The application supports configurable approval pipelines with sequential and parallel steps, role-based approvals, JWT authentication, background workers, failed-job retry, and Firebase-powered real-time updates. The solution is designed around per-request step snapshots so workflow definitions can evolve without affecting in-flight requests. [file:1737][file:1826][file:1827][file:1828]

## Stack

- Backend: Laravel 11, PHP 8.2+, MySQL/SQLite, JWT auth, Spatie Permission, Laravel Queues. [file:1737][file:1832][file:1836]
- Frontend: Vue 3, Pinia, Vue Router, Axios. [file:1825][file:1829][file:1830]
- Real-time: Firebase Realtime Database. [file:1828][file:1831]
- Testing: PHPUnit feature tests for sequential, parallel, approval modes, idempotency, role-change behavior, and worker flows. [file:1737]

## Features

- Admin can manage users, roles, workflow definitions, and failed jobs. [file:1737][file:1824]
- Employees can create requests bound to workflow types and track progress. [file:1737][file:1833]
- Approvers can see pending approvals assigned to them and submit approve/reject actions. [file:1737][file:1834]
- Request execution is processed through queued jobs rather than long-running synchronous controller logic. [file:1826][file:1827]
- Request state is synchronized to Firebase for live request details, summaries, and approval inbox updates. [file:1828]

## Architecture summary

### Workflow model

Workflow definitions are stored as workflow types with ordered workflow steps. When a request is created, the active workflow steps are snapshotted into per-request step instances and assignments so later changes to roles or workflow definitions do not mutate in-flight requests. This implements a snapshot-at-creation policy for membership and step structure. [file:1737][file:1833]

### Approval execution

Approver actions are accepted by the API and queued through `ProcessStepApproval`, which validates the current user against pending assignments, records append-only step actions with an `idempotency_key`, updates assignment and step state inside a database transaction, and dispatches orchestration when a step is completed. [file:1834][file:1827]

### Orchestration

`OrchestrateWorkflow` evaluates grouped steps by `sequence_order`, keeps active stages in progress, advances only when all steps in the current stage are complete, and marks the request rejected or completed when terminal conditions are reached. This allows mixed sequential and parallel execution while preserving deterministic state transitions. [file:1826][file:1737]

### Real-time sync

After workflow changes, request details and request summaries are pushed to Firebase Realtime Database. Pending approvals are also projected per user under approvals paths, which supports live employee and approver views in the frontend. [file:1828][file:1831]

### Authentication and routing

The backend uses JWT authentication endpoints for register, login, me, and logout. The frontend stores the bearer token, restores the session on boot, and routes users by role to admin, approvals, or requests pages. [file:1832][file:1829][file:1825]

### Failed jobs and retry

Admin endpoints expose failed jobs and allow retry by UUID through Laravel's queue retry command. Queue configuration supports database-backed jobs and database UUID failed-job storage for local execution. [file:1835][file:1836][file:1824]

## Important implementation decisions

- Role membership policy: snapshot at request creation. Assignments are created when the request is created, and later role membership changes do not affect existing request assignments. [file:1833][file:1737]
- Concurrency strategy: database transactions plus `lockForUpdate()` on request and step rows protect workflow state during approval handling. [file:1827][file:1737]
- Idempotency strategy: repeated approval requests using the same `idempotency_key` are ignored after the first effective action. [file:1827][file:1737]
- Queue separation: orchestration and step processing are explicitly queued to dedicated names such as `orchestration` and `steps`. [file:1826][file:1827]

## Backend setup

1. Install dependencies:

```bash
composer install
```

2. Copy environment file and generate app key:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database, JWT, queue, and Firebase values in `.env`. The project reads Firebase settings from `FIREBASE_DATABASE_URL` and `FIREBASE_DATABASE_SECRET`. Queue execution defaults to the database driver unless overridden. [file:1837][file:1836]

4. Generate JWT secret if needed by your environment:

```bash
php artisan jwt:secret
```

5. Run migrations and seeders:

```bash
php artisan migrate --seed
```

`DatabaseSeeder` should call the seeders in this order so dependencies are satisfied: `RoleSeeder`, then `UserSeeder`, then `WorkflowSeeder`. Roles must exist before users can be assigned roles, and users plus roles must exist before the seeded workflow can reference them. [web:1850][web:1720][file:1737]

6. Start the API server:

```bash
php artisan serve
```

7. Start queue workers. For local testing, one worker can listen to all important queues:

```bash
php artisan queue:work --queue=orchestration,steps,default
```

8. Optional maintenance trigger for stuck workflows is exposed through the admin maintenance endpoint. [file:1824]

## Frontend setup

1. Install dependencies:

```bash
npm install
```

2. Create the frontend environment values for Firebase and API access. Firebase client config uses `VITE_FIREBASE_API_KEY`, `VITE_FIREBASE_AUTH_DOMAIN`, `VITE_FIREBASE_DATABASE_URL`, `VITE_FIREBASE_PROJECT_ID`, `VITE_FIREBASE_STORAGE_BUCKET`, `VITE_FIREBASE_MESSAGING_SENDER_ID`, and `VITE_FIREBASE_APP_ID`. [file:1831]

3. Update API base URL if needed. The current Axios client points to `http://127.0.0.1:8000/api`. [file:1830]

4. Run the frontend:

```bash
npm run dev
```

## Seeders

### `DatabaseSeeder`

`DatabaseSeeder` is the root seeder used by `php artisan db:seed` and `php artisan migrate --seed`. Its responsibility is to call the project seeders in dependency order. [web:1850][web:1720]

### `RoleSeeder`

Seeds the core API roles used by the system: `admin`, `employee`, `department_manager`, `finance_approver`, and `procurement_approver`. [file:1737]

### `UserSeeder`

Seeds demo users and assigns the correct roles through Spatie Permission. [file:1737]

### `WorkflowSeeder`

Seeds a sample `Purchase Request` workflow with mixed sequential and parallel steps, including a finalization step assigned to admin. [file:1737]

## Demo accounts

The seeders create these sample users with password `password`: [file:1737]

- `admin@example.com` — admin
- `employee1@example.com` — employee
- `employee2@example.com` — employee
- `manager1@example.com` — department manager
- `manager2@example.com` — department manager
- `finance1@example.com` — finance approver
- `finance2@example.com` — finance approver
- `procurement@example.com` — procurement approver

## Seeded workflow

A sample `Purchase Request` workflow is seeded with the following stages: [file:1737]

1. Department Manager Review — sequential, `any`
2. Finance Review — parallel group `group_1`, `all`
3. Procurement Review — parallel group `group_1`, `any`
4. Finalization Step — sequential, `any`

This seeded definition demonstrates mixed sequential and parallel orchestration. [file:1737]

## Main API routes

### Auth

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout` [file:1824][file:1832]

### Admin

- `GET /api/admin/users`
- `POST /api/admin/users`
- `PATCH /api/admin/users/{id}`
- `PATCH /api/admin/users/{id}/toggle-active`
- `GET /api/admin/roles`
- `POST /api/admin/roles`
- `DELETE /api/admin/roles/{id}`
- `GET /api/admin/workflows`
- `GET /api/admin/workflows/{id}`
- `POST /api/admin/workflows`
- `PATCH /api/admin/workflows/{id}`
- `GET /api/admin/failed-jobs`
- `POST /api/admin/failed-jobs/{uuid}/retry` [file:1824]

### Requests and approvals

- `GET /api/requests/workflow-types`
- `GET /api/requests`
- `POST /api/requests`
- `GET /api/requests/{id}`
- `GET /api/requests/{id}/pending`
- `POST /api/requests/{id}/steps/{stepId}/action`
- `POST /api/requests/{id}/admin/retry`
- `GET /api/approvals` [file:1824]

## Manual test flow

1. Login as `employee1@example.com` and create a new purchase request. [file:1737][file:1833]
2. Login as `manager1@example.com` or `manager2@example.com` and approve the first step. [file:1737]
3. Login as `finance1@example.com` and `finance2@example.com`; both must approve the finance step because it uses `approval_mode = all`. [file:1737]
4. Login as `procurement@example.com` and approve the procurement step. [file:1737]
5. Login as `admin@example.com` and complete the finalization step if it is assigned to the admin role. [file:1737]
6. Verify live updates in the employee request details page and the approver inbox. [file:1828][file:1831]
7. Trigger a failed job scenario if needed, then inspect `/api/admin/failed-jobs` and retry by UUID. [file:1835][file:1824]

## Automated tests

Feature tests included in the project cover: [file:1737]

- Sequential workflow creation and completion.
- Parallel workflow gating.
- `approval_mode = any` first approver wins.
- `approval_mode = all` requires all assignees.
- Idempotency with duplicate approval keys.
- Snapshot role assignment behavior after membership changes.
- Worker dispatch and admin retry behavior.

Run tests with:

```bash
php artisan test
```

## Notes and trade-offs

- Local development uses database queues for simplicity, though Redis would be a stronger production choice for visibility and throughput. [file:1737][file:1836]
- Firebase is used as the real-time delivery channel instead of Laravel broadcasting. [file:1737][file:1828]
- The frontend currently stores JWT in `localStorage`, which is simple for the assignment but would need stronger hardening in production. [file:1830][file:1829]

## AI usage note

AI assistance was used to accelerate implementation review, refactoring support, and documentation drafting. All architectural and behavioral decisions were validated against the actual project code and assignment requirements before finalizing the deliverables. [file:1737]
