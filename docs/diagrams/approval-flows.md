<!-- Mermaid diagrams for approval flows: Leave, Overtime, Payroll -->

```mermaid
sequenceDiagram
    autonumber
    participant Employee
    participant Frontend
    participant API as Backend API
    participant ApprovalEngine as Approval Engine
    participant Approver

    Employee->>Frontend: Submit request (leave/overtime/payroll generate)
    Frontend->>API: POST /api/{resource} (payload)
    API->>ApprovalEngine: createRequest(resource, user, metadata)
    ApprovalEngine->>Approver: Notify approver(s)
    Approver->>Frontend: Open approval UI
    Approver->>API: POST /api/{resource}/{id}/approve
    API->>ApprovalEngine: markApproved(requestId, approver)
    ApprovalEngine->>API: trigger post-approval actions (balance update, payroll run, payment)
    API->>Employee: notify result (websocket/email)

    Note over ApprovalEngine,API: Approval flow may be multi-level
```

Contoh untuk `Leave` flow:

```mermaid
sequenceDiagram
    participant Emp as Employee
    participant UI as Leave UI
    participant API
    participant Engine as Approval Engine
    participant Manager

    Emp->>UI: Create leave request
    UI->>API: POST /api/leave
    API->>Engine: createLeaveRequest()
    Engine->>Manager: Notify manager for approval
    Manager->>API: POST /api/leave/{id}/approve
    API->>Engine: recordApproval()
    Engine-->>API: update leave balance & status
    API-->>Emp: send notification
```

Contoh untuk `Overtime` flow (manager approval):

```mermaid
sequenceDiagram
    participant Emp
    participant UI
    participant API
    participant Engine
    participant Manager

    Emp->>UI: Submit overtime request
    UI->>API: POST /api/overtime
    API->>Engine: createOvertimeRequest()
    Engine->>Manager: send approval task
    Manager->>API: POST /api/overtime/{id}/approve
    API->>Engine: finalize overtime (hours, payrate)
    Engine->>Payroll: optionally add to payroll queue
    API-->>Emp: notify approval
```

Contoh untuk `Payroll generate` flow:

```mermaid
sequenceDiagram
    participant PayrollAdmin
    participant UI
    participant API
    participant PayrollService
    participant Finance

    PayrollAdmin->>UI: Click Generate payroll
    UI->>API: POST /api/payroll/generate
    API->>PayrollService: run calculation (attendance, allowances, tax)
    PayrollService-->>API: return preview
    API->>PayrollAdmin: show preview
    PayrollAdmin->>API: POST /api/payroll/{id}/approve
    API->>Finance: notify to process payments
```

Simpan file ini di repo untuk referensi developer.
