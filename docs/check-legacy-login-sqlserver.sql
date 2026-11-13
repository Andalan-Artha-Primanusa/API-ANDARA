USE [MINEPRO];
GO

DECLARE @UserID varchar(100) = 'ISI_USER_ID';
DECLARE @EmailAddress varchar(255) = 'ISI_EMAIL';

-- Cek akun berdasarkan UserID atau email.
SELECT
    UserID,
    EmailAddress,
    isActiveUser,
    isFirstLogin,
    UserRoleID,
    EmployeeSN,
    SAPUserFlag,
    isHeadOfficeUser,
    CASE
        WHEN Password IS NULL OR LTRIM(RTRIM(Password)) = '' THEN 0
        ELSE 1
    END AS has_password,
    LEN(RTRIM(Password)) AS password_length
FROM dbo.ms_sa_permission
WHERE UserID = @UserID
   OR EmailAddress = @EmailAddress;

-- Cek apakah UserID aktif.
SELECT
    CASE WHEN EXISTS (
        SELECT 1
        FROM dbo.ms_sa_permission
        WHERE UserID = @UserID
          AND isActiveUser = 1
    ) THEN 1 ELSE 0 END AS active_user_exists;

-- Jika sistem lama memakai password plaintext, gunakan hanya untuk pengujian lokal.
-- Jangan menyimpan password plaintext di source code atau log.
DECLARE @Password varchar(255) = 'ISI_PASSWORD';

SELECT
    UserID,
    EmailAddress,
    isActiveUser,
    CASE WHEN RTRIM(Password) = @Password THEN 1 ELSE 0 END AS password_matches
FROM dbo.ms_sa_permission
WHERE UserID = @UserID
  AND isActiveUser = 1;
