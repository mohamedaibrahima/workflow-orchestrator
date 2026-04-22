# Manual Testing Guide

## Test accounts

Use the seeded accounts with password `password`:

- `admin@example.com`
- `employee1@example.com`
- `employee2@example.com`
- `manager1@example.com`
- `manager2@example.com`
- `finance1@example.com`
- `finance2@example.com`
- `procurement@example.com` [file:1737]

## Pre-run checklist

1. Run migrations and seeders.
2. Start the Laravel API.
3. Start queue workers.
4. Start the Vue frontend.
5. Make sure Firebase env values are set so the sync service can write live data. [file:1836][file:1837][file:1831]

## Employee flow

1. Log in as `employee1@example.com`.
2. Open the Requests page.
3. Create a new request with a valid workflow type and payload.
4. Verify that the request appears in the list and opens in the details page.
5. Confirm the request status changes in Firebase-backed live views. [file:1825][file:1833][file:1828]

## Approval flow

1. Log in as the assigned approver for the first step.
2. Open the approvals page.
3. Approve the current step.
4. Confirm the job queue processes the action and the request advances.
5. For a reject path, submit a reject action and confirm the request moves to rejected. [file:1834][file:1827][file:1826]

## Parallel stage flow

1. Use the seeded purchase workflow.
2. Create a request that enters the parallel stage.
3. Approve only one of the parallel steps.
4. Confirm the request stays in progress.
5. Approve the second parallel step.
6. Confirm the workflow advances to the next stage. [file:1737][file:1826]

## Approval mode any

1. Create or use a workflow step with `approval_mode = any`.
2. Have one assigned approver approve the step.
3. Confirm the step becomes approved immediately.
4. Confirm the remaining assignments become skipped. [file:1737][file:1827]

## Approval mode all

1. Create or use a workflow step with `approval_mode = all`.
2. Have the first assignee approve the step.
3. Confirm the step remains pending until every snapshot assignee approves.
4. After the final required approval, confirm the step becomes approved. [file:1737][file:1827]

## Idempotency check

1. Call the approval endpoint twice with the same `idempotency_key`.
2. Confirm that only one effective action is stored.
3. Confirm the step state changes only once. [file:1827][file:1737]

## Snapshot membership check

1. Create a request.
2. Change role membership after request creation.
3. Confirm the existing request assignments do not change.
4. Confirm new role members are not added to that in-flight request. [file:1833][file:1737]

## Failed jobs check

1. Trigger or simulate a failure in a queued job.
2. Log in as admin.
3. Open the failed jobs page.
4. Retry the failed job by UUID.
5. Confirm the retry endpoint returns accepted/ok and the job is re-queued. [file:1835][file:1824]

## Real-time check

1. Open the request details page in one browser.
2. Perform an approval in another browser.
3. Confirm the first browser updates after Firebase synchronization.
4. Confirm the approver inbox also reflects the new state. [file:1828][file:1831]

## Suggested verification commands

```bash
php artisan test
php artisan queue:work --queue=orchestration,steps,default
php artisan serve
npm run dev
```
