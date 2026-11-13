@component('pdf.layout')
@slot('title')SURAT TUGAS@endslot

<div style="margin-bottom: 30px;">
    <strong>Nomor Surat:</strong> ST/{{ date('Y/m') }}/{{ str_pad($letter->id, 4, '0', STR_PAD_LEFT) }}
</div>

<p>Direksi PT Andalan Artha Primanusa dengan ini memberikan tugas kepada:</p>

<table style="width: 100%; margin-bottom: 25px; border-collapse: collapse;">
    <tr>
        <td style="width: 150px; padding: 5px 0;">Nama</td>
        <td style="width: 20px; padding: 5px 0;">:</td>
        <td style="padding: 5px 0;"><strong>{{ $letter->user->name }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 5px 0;">Jabatan</td>
        <td style="padding: 5px 0;">:</td>
        <td style="padding: 5px 0;">{{ $letter->user->employee->position ?? 'Karyawan' }}</td>
    </tr>
    <tr>
        <td style="padding: 5px 0;">Departemen</td>
        <td style="padding: 5px 0;">:</td>
        <td style="padding: 5px 0;">{{ $letter->user->employee->department ?? '-' }}</td>
    </tr>
</table>

<p>Untuk melaksanakan tugas sebagai berikut:</p>

<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
    <div style="font-weight: bold; margin-bottom: 10px; color: #1e40af;">{{ $letter->title }}</div>
    <div style="font-size: 10pt; color: #475569;">
        {{ $letter->description }}
    </div>
</div>

<table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;">
    <tr>
        <td style="width: 150px; padding: 5px 0;">Lokasi Tugas</td>
        <td style="width: 20px; padding: 5px 0;">:</td>
        <td style="padding: 5px 0;">{{ $letter->location }}</td>
    </tr>
    <tr>
        <td style="padding: 5px 0;">Tanggal Pelaksanaan</td>
        <td style="padding: 5px 0;">:</td>
        <td style="padding: 5px 0;">{{ $letter->start_date->format('d M Y') }} s/d {{ $letter->end_date->format('d M Y') }}</td>
    </tr>
</table>

<p>Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab. Atas perhatian dan kerjasamanya kami ucapkan terima kasih.</p>

@endcomponent
