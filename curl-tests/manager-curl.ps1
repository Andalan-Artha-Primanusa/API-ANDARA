# Manager HRIS API curl smoke test.
#
# Usage:
#   $env:HRIS_BASE_URL = "http://127.0.0.1:8000/api"
#   $env:HRIS_MANAGER_TOKEN = "<bearer-token>"
#   powershell -ExecutionPolicy Bypass -File .\curl-tests\manager-curl.ps1

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
$token = Get-EnvValue -Name 'HRIS_MANAGER_TOKEN'

if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'Set HRIS_MANAGER_TOKEN before running this script.'
}

$kpiBody = @'
{
  "employee_id": 1,
  "title": "Sales Target",
  "description": "Achieve 100 new customers",
  "target": 100,
  "period": "2026-Q2"
}
'@

$reimbursementBody = @'
{
  "employee_id": 1,
  "title": "Office Supplies",
  "description": "Monthly office supplies",
  "amount": 1000000,
  "category": "office_supplies",
  "expense_date": "2026-04-09",
  "receipt_path": "/receipts/office_001.pdf"
}
'@

$surveyBody = @'
{
  "title": "Q2 Engagement Pulse",
  "survey_type": "pulse",
  "start_date": "2026-04-15",
  "end_date": "2026-04-30",
  "anonymous": true,
  "status": "draft",
  "questions": [
    {
      "question_type": "rating",
      "question_text": "How satisfied are you with your work environment?",
      "required": true
    },
    {
      "question_type": "text",
      "question_text": "What could improve your experience?",
      "required": false
    }
  ]
}
'@

$idpBody = @'
{
  "employee_id": 1,
  "review_cycle_id": 1,
  "goal_title": "Move into team lead role",
  "goal_description": "Build leadership and planning skills",
  "status": "draft",
  "target_date": "2026-12-31",
  "mentor_user_id": 2
}
'@

Invoke-Curl -Title 'Organization directory' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/organization/directory",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Leaves pending' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/leaves/pending",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Approve leave' -Args @(
    '-sS', '-X', 'PUT', "$baseUrl/leaves/1/approve",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', '{"status":"approved"}'
)

Invoke-Curl -Title 'Reject leave' -Args @(
    '-sS', '-X', 'PUT', "$baseUrl/leaves/1/reject",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', '{"note":"Cannot approve at this time"}'
)

Invoke-Curl -Title 'KPI list' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/kpis",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create KPI' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/kpis",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $kpiBody
)

Invoke-Curl -Title 'Reimbursements list' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/reimbursements",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create reimbursement' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/reimbursements",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $reimbursementBody
)

Invoke-Curl -Title 'People insights' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/insights/people/detailed?window_days=30&expiring_days=30",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Performance summary' -Args @(
    '-sS', '-X', 'GET', "$baseUrl/performance/summary",
    '-H', "Authorization: Bearer $token", '-H', 'Accept: application/json'
)

Invoke-Curl -Title 'Create survey' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/engagement/surveys",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $surveyBody
)

Invoke-Curl -Title 'Create IDP' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/career/idps",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', $idpBody
)

Invoke-Curl -Title 'Create open job opening' -Args @(
    '-sS', '-X', 'POST', "$baseUrl/recruitment/openings",
    '-H', "Authorization: Bearer $token",
    '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
    '-d', '{"title":"Software Engineer","department":"IT","position_level":"Mid","employment_type":"Full-time","headcount":1,"status":"open"}'
)

Write-Host "`nDone." -ForegroundColor Green