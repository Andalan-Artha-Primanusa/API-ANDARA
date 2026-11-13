# HR HRIS API curl smoke test.
#
# Usage:
#   $env:HRIS_BASE_URL = "http://127.0.0.1:8000/api"
#   $env:HRIS_HR_TOKEN = "<bearer-token>"
#   powershell -ExecutionPolicy Bypass -File .\curl-tests\hr-curl.ps1

$ErrorActionPreference = 'Stop'

function Get-EnvValue {
    param([string]$Name, [string]$Default = '')
    $value = [System.Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($value)) { return $Default }
    return $value
}

function Invoke-Curl {
    param([string]$Title, [string[]]$Args)
    Write-Host "`n=== $Title ===" -ForegroundColor Cyan
    & curl.exe @Args
    if ($LASTEXITCODE -ne 0) { throw "curl failed for: $Title" }
}

$baseUrl = Get-EnvValue -Name 'HRIS_BASE_URL' -Default 'http://127.0.0.1:8000/api'
$token = Get-EnvValue -Name 'HRIS_HR_TOKEN'

if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'Set HRIS_HR_TOKEN before running this script.'
}

$employeeBody = @'
{
  "user_id": 2,
  "manager_id": 1,
  "employee_code": "EMP-0002",
  "position": "Manager",
  "department": "IT",
  "hire_date": "2025-01-15",
  "salary": 50000000,
  "status": "active",
  "location_id": 1,
  "work_schedule_id": 1
}
'@

$leavePolicyBody = @'
{
  "year": 2026,
  "annual_allowance": 14,
  "carry_over_allowance": 3,
  "max_pending_days": 30,
  "active": true,
  "notes": "HR default leave policy"
}
'@

$payrollBody = @'
{
  "employee_id": 1,
  "period": "2026-04",
  "allowance": 2000000,
  "bonus": 500000
}
'@

$payrollDetailBody = @'
{
  "payroll_id": 1,
  "details": [
    { "type": "allowance", "name": "Housing Allowance", "amount": 2000000 },
    { "type": "deduction", "name": "Tax", "amount": 500000 }
  ]
}
'@

Invoke-Curl -Title 'Employees' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/employees",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create employee' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/employees",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $employeeBody
)

Invoke-Curl -Title 'Leave policies' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/leave-policies",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create leave policy' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/leave-policies",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $leavePolicyBody
)

Invoke-Curl -Title 'Payroll list' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/payroll",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create payroll' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/payroll",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $payrollBody
)

Invoke-Curl -Title 'Payroll details by payroll' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/payroll-details/1",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create payroll details' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/payroll-details",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $payrollDetailBody
)

Invoke-Curl -Title 'Reports dashboard summary' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/reports/dashboard-summary",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Compliance overview' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/compliance/overview",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Compliance audit summary' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/compliance/audit-summary",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Expiring documents' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/compliance/expiring-documents?days=30",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Write-Host "`nDone." -ForegroundColor Green