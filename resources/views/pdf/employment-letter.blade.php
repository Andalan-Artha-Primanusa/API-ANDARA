@component('pdf.layout')
@slot('title')SURAT KETERANGAN BEKERJA@endslot

<div style="margin-bottom: 25px;">
    <strong>Nomor:</strong> SKB/{{ date('Y/m') }}/{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}
</div>

<p>Yang bertanda tangan di bawah ini, HR Department PT Testing, menerangkan bahwa:</p>

<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
    <tr>
        <td style="width: 150px; padding: 8px 0;">Nama</td>
        <td style="width: 20px; padding: 8px 0;">:</td>
        <td style="padding: 8px 0;"><strong>{{ $employee->user->name }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 8px 0;">NIK</td>
        <td style="padding: 8px 0;">:</td>
        <td style="padding: 8px 0;">{{ $employee->employee_id }}</td>
    </tr>
    <tr>
        <td style="padding: 8px 0;">Jabatan</td>
        <td style="padding: 8px 0;">:</td>
        <td style="padding: 8px 0;">{{ $employee->position }}</td>
    </tr>
    <tr>
        <td style="padding: 8px 0;">Departemen</td>
        <td style="padding: 8px 0;">:</td>
        <td style="padding: 8px 0;">{{ $employee->department }}</td>
    </tr>
</table>

<p style="text-align: justify; margin-top: 20px;">
    Menerangkan bahwa yang bersangkutan adalah benar karyawan aktif di PT Andalan Artha Primanusa sejak tanggal <strong>{{ optional($employee->hire_date)->format('d M Y') }}</strong> sampai dengan saat ini.
</p>

<p style="text-align: justify;">
    Surat keterangan ini diberikan atas permintaan yang bersangkutan untuk dipergunakan sebagai kelengkapan administrasi. Kami berharap keterangan ini dapat digunakan sebagaimana mestinya.
</p>

<p style="margin-top: 30px;">
    Demikian surat keterangan ini kami buat dengan sebenarnya agar dapat dipergunakan sebagaimana mestinya.
</p>

@endcomponent
