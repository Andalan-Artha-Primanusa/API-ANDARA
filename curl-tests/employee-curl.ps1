# Employee HRIS API curl smoke test.
#
# Usage:
#   $env:HRIS_BASE_URL = "http://127.0.0.1:8000/api"
#   $env:HRIS_EMPLOYEE_TOKEN = "<bearer-token>"
#   powershell -ExecutionPolicy Bypass -File .\curl-tests\employee-curl.ps1

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
$token = Get-EnvValue -Name 'HRIS_EMPLOYEE_TOKEN'

if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'Set HRIS_EMPLOYEE_TOKEN before running this script.'
}

$reimbursementBody = @'
{
  "title": "Business Trip Expenses",
  "description": "Travel to Jakarta for client meeting",
  "amount": 500000,
  "category": "travel",
  "expense_date": "2026-04-15",
  "receipt_path": "/receipts/travel_001.pdf"
}
'@

$leaveBody = @'
{
  "type": "annual",
  "start_date": "2026-05-01",
  "end_date": "2026-05-05",
  "reason": "Family vacation"
}
'@

$checkInBody = @'
{
  "latitude": -6.200000,
  "longitude": 106.816666
}
'@

Invoke-Curl -Title 'Me' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/me",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'My profile' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/profiles",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'My KPI' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/my/kpi",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'My reimbursements' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/my/reimbursements",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create my reimbursement' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/my/reimbursements",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $reimbursementBody
)

Invoke-Curl -Title 'My payroll' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/my/payroll",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'My leaves' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/leaves/my",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Leave balance' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/leaves/balance",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create leave request' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/leaves",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $leaveBody
)

Invoke-Curl -Title 'Attendance today' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/attendance/today",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Check in' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/attendance/check-in",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $checkInBody
)

Invoke-Curl -Title 'Check out' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/attendance/check-out",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Attendance history' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/attendance/history",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Notifications' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/notifications",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Unread notification count' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/notifications/unread-count",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'My documents' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/my/documents",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'My requests' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/my/requests",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Write-Host "`nDone." -ForegroundColor Green