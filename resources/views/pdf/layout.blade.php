<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .page-wrapper {
            padding: 2cm;
        }
        .letterhead {
            border-bottom: 3px solid #1e40af;
            padding-bottom: 20px;
            margin-bottom: 40px;
            position: relative;
        }
        .logo-placeholder {
            float: left;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 15px;
            color: white;
            text-align: center;
            line-height: 70px;
            font-weight: bold;
            font-size: 24pt;
            margin-right: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .company-info {
            overflow: hidden;
        }
        .company-name {
            font-size: 20pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
            letter-spacing: 1px;
        }
        .company-details {
            font-size: 9pt;
            color: #64748b;
            margin-top: 5px;
        }
        .document-title-box {
            text-align: center;
            margin-bottom: 40px;
        }
        .document-title {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
            padding-bottom: 5px;
        }
        .content {
            min-height: 500px;
        }
        .signature-section {
            margin-top: 60px;
            width: 100%;
        }
        .signature-box {
            float: right;
            width: 250px;
            text-align: center;
        }
        .signature-space {
            height: 80px;
            margin-bottom: 10px;
            position: relative;
        }
        .signature-line {
            border-top: 1px solid #1e293b;
            margin-top: 5px;
            padding-top: 5px;
            font-weight: bold;
        }
        .stamp-placeholder {
            position: absolute;
            width: 100px;
            height: 100px;
            border: 3px double rgba(30, 64, 175, 0.3);
            border-radius: 50%;
            top: -10px;
            left: 50%;
            transform: translateX(-50%) rotate(-15deg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(30, 64, 175, 0.3);
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #f8fafc;
            padding: 15px 2cm;
            font-size: 8pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        .clear {
            clear: both;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="letterhead">
            <div class="logo-placeholder">A</div>
            <div class="company-info">
                <div class="company-name">Andalan Artha Primanusa</div>
                <div class="company-details">
                    Gedung Artha Lt. 12, Kav. 45-46, Sudirman Central Business District<br>
                    Jl. Jend. Sudirman No. 123, Jakarta Selatan 12190<br>
                    Phone: +62 21 1234 5678 | Email: corporate@andalanartha.com | Web: www.andalanartha.com
                </div>
            </div>
            <div class="clear"></div>
        </div>

        <div class="document-title-box">
            <div class="document-title">
                {{ $title ?? 'SURAT KETERANGAN' }}
            </div>
        </div>

        <div class="content">
            {{ $slot }}
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <div style="margin-bottom: 10px;">Jakarta, {{ $date ?? date('d F Y') }}</div>
                <div style="font-weight: bold; margin-bottom: 10px;">Hormat Kami,</div>
                <div class="signature-space">
                    <div class="stamp-placeholder">OFFICIAL STAMP</div>
                    <!-- Signature image can be placed here -->
                    <div style="font-family: 'Brush Script MT', cursive; font-size: 24pt; color: #1e3a8a; padding-top: 15px;">
                        Artha Admin
                    </div>
                </div>
                <div class="signature-line">
                    ( Bpk. Artha Wijaya )
                </div>
                <div style="font-size: 9pt; color: #64748b;">Direktur Utama / HR Director</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>

    <div class="footer">
        Dokumen ini diterbitkan secara digital oleh HRIS Andalan Artha Primanusa.<br>
        Keaslian dokumen ini dapat diverifikasi melalui sistem internal perusahaan.
    </div>
</body>
</html>
