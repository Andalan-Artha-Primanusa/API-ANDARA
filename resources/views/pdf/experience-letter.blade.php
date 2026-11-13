@component('pdf.layout')
@slot('title')SURAT PENGALAMAN KERJA (PAKLARING)@endslot

<div style="margin-bottom: 25px;">
    <strong>Nomor:</strong> PK/{{ date('Y/m') }}/{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}
</div>

<p>Yang bertanda tangan di bawah ini, Manajemen PT Andalan Artha Primanusa, dengan ini menerangkan bahwa:</p>

<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
    <tr>
        <td style="width: 150px; padding: 8px 0;">Nama</td>
        <td style="width: 20px; padding: 8px 0;">:</td>
        <td style="padding: 8px 0;"><strong>{{ $employee->user->name }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 8px 0;">Jabatan Terakhir</td>
        <td style="padding: 8px 0;">:</td>
        <td style="padding: 8px 0;">{{ $employee->position }}</td>
    </tr>
    <tr>
        <td style="padding: 8px 0;">Departemen</td>
        <td style="padding: 8px 0;">:</td>
        <td style="padding: 8px 0;">{{ $employee->department }}</td>
    </tr>
    <tr>
        <td style="padding: 8px 0;">Masa Kerja</td>
        <td style="padding: 8px 0;">:</td>
        <td style="padding: 8px 0;">
            {{ optional($employee->hire_date)->format('d M Y') }} sampai dengan {{ optional($employee->termination_date)->format('d M Y') ?? 'sekarang' }}
        </td>
    </tr>
</table>

<p style="text-align: justify; margin-top: 20px;">
    Saudara/i yang bersangkutan benar-benar telah bekerja pada PT Andalan Artha Primanusa. Selama masa kerjanya, beliau telah menunjukkan dedikasi, integritas, dan kontribusi yang baik bagi perkembangan perusahaan.
</p>

<p style="text-align: justify;">
    Kami mengucapkan terima kasih atas segala upaya dan jasa-jasanya selama bekerja di perusahaan kami. Semoga kesuksesan senantiasa menyertai langkah beliau di masa yang akan datang.
</p>

<p style="margin-top: 30px;">
    Demikian surat keterangan ini kami buat agar dapat dipergunakan sebagaimana mestinya.
</p>

@endcomponent
