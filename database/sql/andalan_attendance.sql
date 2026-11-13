/*
   ANDALAN attendance foundation for the MINEPRO SQL Server database.
   These tables are intentionally prefixed with `andalan_` and do not alter
   the existing MINEPRO tables.
*/

IF OBJECT_ID(N'dbo.andalan_attendance_locations', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.andalan_attendance_locations (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_andalan_attendance_locations PRIMARY KEY,
        location_name NVARCHAR(255) NOT NULL,
        location_code NVARCHAR(100) NULL,
        project_id NVARCHAR(100) NULL,
        department NVARCHAR(150) NULL,
        description NVARCHAR(500) NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        radius_meters INT NOT NULL CONSTRAINT DF_andalan_attendance_locations_radius DEFAULT (100),
        is_default BIT NOT NULL CONSTRAINT DF_andalan_attendance_locations_default DEFAULT (0),
        is_active BIT NOT NULL CONSTRAINT DF_andalan_attendance_locations_active DEFAULT (1),
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_andalan_attendance_locations_created DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_andalan_attendance_locations_updated DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_andalan_attendance_locations_scope
        ON dbo.andalan_attendance_locations (project_id, location_code, is_active);
END;

IF OBJECT_ID(N'dbo.andalan_attendance_logs', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.andalan_attendance_logs (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_andalan_attendance_logs PRIMARY KEY,
        salary_number NVARCHAR(100) NOT NULL,
        employee_id NVARCHAR(100) NULL,
        employee_name NVARCHAR(255) NULL,
        attendance_date DATE NOT NULL,
        location_id INT NOT NULL,
        check_in_at DATETIME2(0) NULL,
        check_out_at DATETIME2(0) NULL,
        check_in_latitude DECIMAL(10,7) NULL,
        check_in_longitude DECIMAL(10,7) NULL,
        check_out_latitude DECIMAL(10,7) NULL,
        check_out_longitude DECIMAL(10,7) NULL,
        distance_meters INT NULL,
        status NVARCHAR(30) NOT NULL CONSTRAINT DF_andalan_attendance_logs_status DEFAULT (N'on_time'),
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_andalan_attendance_logs_created DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_andalan_attendance_logs_updated DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_andalan_attendance_logs_employee_date UNIQUE (salary_number, attendance_date)
    );

    CREATE INDEX IX_andalan_attendance_logs_date
        ON dbo.andalan_attendance_logs (attendance_date, status);
END;

/*
   WebAuthn credentials and attendance verification audit.
   Face ID/Touch ID is represented by a public-key credential; no biometric
   image, Face ID template, or private key is stored in SQL Server.
*/
IF OBJECT_ID(N'dbo.andalan_webauthn_credentials', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.andalan_webauthn_credentials (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_andalan_webauthn_credentials PRIMARY KEY,
        user_id NVARCHAR(255) NOT NULL,
        salary_number NVARCHAR(100) NULL,
        credential_id NVARCHAR(512) NOT NULL,
        public_key NVARCHAR(MAX) NOT NULL,
        signature_counter BIGINT NULL,
        user_verified BIT NOT NULL CONSTRAINT DF_andalan_webauthn_credentials_verified DEFAULT (0),
        last_used_at DATETIME2(0) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_andalan_webauthn_credentials_created DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_andalan_webauthn_credentials_updated DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_andalan_webauthn_credentials_credential UNIQUE (credential_id)
    );

    CREATE INDEX IX_andalan_webauthn_credentials_user
        ON dbo.andalan_webauthn_credentials (user_id);
END;

IF OBJECT_ID(N'dbo.andalan_attendance_biometric_events', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.andalan_attendance_biometric_events (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_andalan_attendance_biometric_events PRIMARY KEY,
        attendance_log_id BIGINT NULL,
        user_id NVARCHAR(255) NOT NULL,
        salary_number NVARCHAR(100) NULL,
        operation NVARCHAR(20) NOT NULL,
        credential_id NVARCHAR(512) NULL,
        verified_at DATETIME2(0) NOT NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_andalan_attendance_biometric_events_created DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_andalan_attendance_biometric_events_user
        ON dbo.andalan_attendance_biometric_events (user_id, created_at);
    CREATE INDEX IX_andalan_attendance_biometric_events_log
        ON dbo.andalan_attendance_biometric_events (attendance_log_id);
END;
