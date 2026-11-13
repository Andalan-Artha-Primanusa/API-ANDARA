# Admin / Super Admin HRIS API curl smoke test.
#
# Usage:
#   $env:HRIS_BASE_URL = "http://127.0.0.1:8000/api"
#   $env:HRIS_ADMIN_TOKEN = "<bearer-token>"
#   powershell -ExecutionPolicy Bypass -File .\curl-tests\admin-curl.ps1

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
$token = Get-EnvValue -Name 'HRIS_ADMIN_TOKEN'

if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'Set HRIS_ADMIN_TOKEN before running this script.'
}

$broadcastBody = @'
{
  "title": "System Maintenance",
  "message": "System will be under maintenance tonight at 22:00.",
  "type": "system.maintenance",
  "category": "broadcast",
  "data": {
    "starts_at": "2026-04-15 22:00:00"
  }
}
'@

$emailNotificationBody = @'
{
  "recipient_email": "employee@example.com",
  "user_id": 2,
  "subject": "Welcome to HRIS",
  "body": "Your account has been activated.",
  "type": "notification",
  "reference_type": "employee",
  "reference_id": 2
}
'@

$emailTemplateBody = @'
{
  "key": "welcome-template",
  "name": "Welcome Template",
  "description": "Welcome email for new employees",
  "subject": "Welcome to the company",
  "html_body": "<p>Hello {{name}}, welcome aboard!</p>",
  "text_body": "Hello {{name}}, welcome aboard!",
  "placeholders": ["name"]
}
'@

$biometricBody = @'
{
  "name": "Front Door Scanner",
  "device_type": "fingerprint",
  "vendor": "ZKTeco",
  "serial_number": "BIO-001",
  "endpoint_url": "https://device.local/api",
  "api_key": "device-api-key",
  "active": true,
  "location_id": 1
}
'@

Invoke-Curl -Title 'Admin notification summary' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/notifications/summary",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Broadcast notification' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/admin/notifications/broadcast",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $broadcastBody
)

Invoke-Curl -Title 'Send email notification' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/admin/email-notifications",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $emailNotificationBody
)

Invoke-Curl -Title 'Email logs' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/email-notifications/logs",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create email template' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/admin/email-templates",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $emailTemplateBody
)

Invoke-Curl -Title 'Email templates' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/email-templates",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Audit logs' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/audit-logs",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Users' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/users",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Roles' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/roles",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Permissions' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/permissions",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Biometric device create' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/biometric/devices",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $biometricBody
)

Invoke-Curl -Title 'Import template' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/admin/import/template",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Write-Host "`nDone." -ForegroundColor Green