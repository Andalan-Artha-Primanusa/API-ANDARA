# Role-based curl smoke tests

Use these scripts to test the HRIS API by role and by feature order, so you do not have to jump around.

## Scripts

- `admin-curl.ps1` for `admin` and `super_admin` routes
- `hr-curl.ps1` for `hr` routes
- `manager-curl.ps1` for `manager` routes
- `employee-curl.ps1` for `employee` self-service routes

## Environment Variables

- `HRIS_BASE_URL` defaults to `http://127.0.0.1:8000/api`
- `HRIS_ADMIN_TOKEN` for `admin` and `super_admin`
- `HRIS_HR_TOKEN` for `hr`
- `HRIS_MANAGER_TOKEN` for `manager`
- `HRIS_EMPLOYEE_TOKEN` for `employee`

## super_admin

Script: `admin-curl.ps1`

Feature order:
- `admin/notifications` summary and broadcast
- `admin/email-notifications` send and logs
- `admin/email-templates` list and create
- `admin/audit-logs` list
- `biometric/devices` create
- `admin/import/template`

Recommended token:
- `HRIS_ADMIN_TOKEN`

## admin

Script: `admin-curl.ps1`

Feature order:
- `admin/users`
- `admin/roles`
- `admin/permissions`
- `admin/notifications`
- `admin/email-notifications`
- `admin/email-templates`
- `admin/audit-logs`

Recommended token:
- `HRIS_ADMIN_TOKEN`

## hr

Script: `hr-curl.ps1`

Feature order:
- `employees` create and list
- `leave-policies` list and create
- `payroll` create and list
- `payroll-details` create and detail
- `reports/dashboard-summary`
- `compliance/overview`
- `compliance/audit-summary`
- `compliance/expiring-documents`

Recommended token:
- `HRIS_HR_TOKEN`

## manager

Script: `manager-curl.ps1`

Feature order:
- `organization/directory`
- `leaves/pending`, approve, reject
- `kpis` list and create
- `reimbursements` list and create
- `insights/people/detailed`
- `performance/summary`
- `performance/cycles`
- `performance/reviews`
- `performance/okrs`
- `performance/360-reviews`
- `performance/calibration`
- `engagement/surveys`
- `career/idps`
- `career/succession`
- `recruitment/openings`

Recommended token:
- `HRIS_MANAGER_TOKEN`

## employee

Script: `employee-curl.ps1`

Feature order:
- `me`
- `profiles`
- `my/kpi`
- `my/reimbursements`
- `my/payroll`
- `leaves/my`
- `leaves/balance`
- `leaves` create
- `attendance/check-in`
- `attendance/check-out`
- `attendance/history`
- `notifications`
- `my/documents`
- `my/requests`

Recommended token:
- `HRIS_EMPLOYEE_TOKEN`

## Run Examples

```powershell
powershell -ExecutionPolicy Bypass -File .\curl-tests\admin-curl.ps1
powershell -ExecutionPolicy Bypass -File .\curl-tests\hr-curl.ps1
powershell -ExecutionPolicy Bypass -File .\curl-tests\manager-curl.ps1
powershell -ExecutionPolicy Bypass -File .\curl-tests\employee-curl.ps1
```