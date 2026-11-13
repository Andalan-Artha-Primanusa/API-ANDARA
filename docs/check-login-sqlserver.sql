USE [MINEPRO];
GO

/*
   Pemeriksaan akun login aplikasi.
   Login aplikasi memakai email + password pada tabel dbo.users.
   Password tersimpan sebagai hash Laravel, bukan plaintext.
*/

DECLARE @Email nvarchar(255) = N'user@example.com';

-- Jalankan bagian ini lebih dulu untuk memastikan database yang dipilih benar.
SELECT DB_NAME() AS current_database;

SELECT
    TABLE_SCHEMA,
    TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_TYPE = 'BASE TABLE'
  AND TABLE_NAME IN ('users', 'roles', 'user_roles')
ORDER BY TABLE_SCHEMA, TABLE_NAME;

-- 1) Cek apakah akun dengan email tersebut ada.
SELECT
    u.id,
    u.name,
    u.email,
    u.email_verified_at,
    u.created_at,
    u.updated_at,
    CASE WHEN u.password IS NULL OR LTRIM(RTRIM(u.password)) = N''
         THEN 0 ELSE 1 END AS has_password_hash,
    LEFT(u.password, 12) AS password_hash_prefix,
    LEN(u.password) AS password_hash_length
FROM dbo.users AS u
WHERE u.email = @Email;

-- 2) Cek role akun melalui tabel pivot user_roles.
SELECT
    u.id AS user_id,
    u.email,
    r.id AS role_id,
    r.name AS role_name
FROM dbo.users AS u
LEFT JOIN dbo.user_roles AS ur ON ur.user_id = u.id
LEFT JOIN dbo.roles AS r ON r.id = ur.role_id
WHERE u.email = @Email
ORDER BY r.name;

-- 3) Ringkasan apakah akun ditemukan dan memiliki hash password.
SELECT
    CASE WHEN EXISTS (
        SELECT 1
        FROM dbo.users AS u
        WHERE u.email = @Email
    ) THEN 1 ELSE 0 END AS user_exists,
    CASE WHEN EXISTS (
        SELECT 1
        FROM dbo.users AS u
        WHERE u.email = @Email
          AND u.password IS NOT NULL
          AND LTRIM(RTRIM(u.password)) <> N''
    ) THEN 1 ELSE 0 END AS password_hash_exists;

/*
   Verifikasi password dilakukan oleh Laravel, bukan query SQL Server.
   Tes login melalui API:

   POST /api/login
   {
       "email": "user@example.com",
       "password": "password-yang-diuji"
   }
*/
