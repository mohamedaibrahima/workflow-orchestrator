# Architecture Notes

## Product goal

This project implements a dynamic workflow orchestrator for purchase-style requests. Admins manage users, roles, and workflow definitions; employees submit requests; approvers act on assigned steps; workers move the workflow forward; and Firebase keeps the UI live. [file:1737][file:1824][file:1826][file:1827][file:1828]

## System shape

The system is split into a Laravel 11 backend and a Vue 3 frontend. The backend exposes JWT-protected REST endpoints and queue jobs, while the frontend uses Vue Router, Pinia, Axios, and Firebase client config to consume the API and reflect live state. [file:1832][file:1829][file:1830][file:1825][file:1831]

## Workflow lifecycle

A workflow type stores the reusable definition, and workflow steps define the ordered pipeline. When a request is created, the selected workflow type is loaded with active steps, and the app snapshots those steps into per-request step instances and per-request assignments. This means in-flight requests keep their original assignees and step structure even if roles or definitions change later. [file:1833][file:1737]

## Approval handling

Approvals are not processed inside controllers. The approvals controller validates that the current user has a pending assignment on the active request step, then dispatches `ProcessStepApproval` with an idempotency key and returns 202 Accepted. The job locks the request and step rows, checks duplicate keys, records a step action, updates assignment state, and either closes the request or triggers orchestration. [file:1834][file:1827]

## Orchestration logic

`OrchestrateWorkflow` is responsible for advancing the request through stages. It groups steps by `sequence_order`, treats every group as one stage, and only advances when all steps in the stage are approved. If any step is rejected, the request becomes rejected. If all stages finish, the request becomes completed. [file:1826]

## Parallel and sequential behavior

Sequential flow is handled by stage order, where the next stage is only considered after the previous one is complete. Parallel behavior is implemented by placing multiple steps in the same `sequence_order` group, so they must all finish before the workflow advances. The seeded purchase workflow uses this mixed pattern to demonstrate a real pipeline. [file:1826][file:1737]

## Real-time layer

Firebase is used as the real-time delivery channel. The sync service loads the request with related workflow, steps, actions, assignments, and audit events, then writes detailed request data, compact summaries, and per-user approval inbox snapshots to Firebase paths. The frontend initializes Firebase from Vite env values and can subscribe to these live records. [file:1828][file:1831]

## Authentication and routing

Login returns a bearer token and the frontend keeps it in `localStorage`. The auth store restores session state by calling `/auth/me`, while the router sends authenticated users to the correct area based on their role: admin, approvals, or requests. [file:1832][file:1829][file:1825]

## Queue architecture

Queue configuration defaults to the database driver and database-backed failed jobs. Jobs are separated conceptually by purpose, with orchestration, step processing, retry, compensation, projection rebuild, and stuck-workflow maintenance all represented as queued jobs. This keeps long-running logic out of controllers and makes failures retryable. [file:1836][file:1826][file:1827][file:1737]

## Failure handling

Admin users can list failed jobs and retry them by UUID. There is also an admin request retry path that dispatches retry and projection jobs. This matches the assignment’s requirement for a visible dead-letter and recovery path. [file:1835][file:1824][file:1737]

## Test coverage

The test suite covers sequential completion, parallel gating, approval modes, idempotency, snapshot membership behavior, and worker dispatch paths. The tests are important because the assignment explicitly evaluates concurrency, parallelism, idempotency, worker retries, and role-change behavior. [file:1737]

## Known documentation choice

The project’s role membership policy is snapshot-at-creation. That choice is already reflected in request creation and in the tests, so it should be documented clearly in the final handoff as an explicit trade-off rather than an accident of implementation. [file:1833][file:1737]

## What to remember for future explanations

When you ask later about any part of the project, these are the reference files to treat as the source of truth:

- Backend API and orchestration: `routes/api.php`, `RequestController`, `ApprovalsController`, `OrchestrateWorkflow`, `ProcessStepApproval`, `FirebaseRealtimeSyncService`. [file:1824][file:1833][file:1834][file:1826][file:1827][file:1828]
- Frontend behavior: `src/router/index.ts`, `src/stores/auth.ts`, `src/api/client.ts`, `src/firebase.ts`, and the admin/employee/approver views. [file:1825][file:1829][file:1830][file:1831]
- Seed data and demo accounts: `RoleSeeder`, `UserSeeder`, `WorkflowSeeder`. [file:1737]
- Quality proof: the feature tests under `tests/Feature`. [file:1737]
