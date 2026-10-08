@php
    $num = fn ($value, $digits) => $value === null ? '-' : number_format((float) $value, $digits, ',', '.');
    $cbm = fn ($value) => $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast CBM{{ $title ? ' - '.$title : '' }} {{ $printedAt->format('Ymd-His') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; background: #f5f5f5; }
        .page { background: #fff; max-width: 1000px; margin: 20px auto; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .no-print { max-width: 1000px; margin: 20px auto 0; display: flex; gap: 8px; align-items: center; }
        .no-print .hint { color: #666; font-size: 12px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #1a56db; color: #fff; }
        .btn-light { background: #e9ecef; color: #333; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 14px; margin-bottom: 18px; }
        .company-name { font-size: 20px; font-weight: 700; letter-spacing: 1px; }
        .company-sub { font-size: 12px; color: #666; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 17px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #1a56db; }
        .doc-title .ref { font-size: 13px; font-weight: 600; margin-top: 4px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
        .summary .box { border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px 12px; background: #fafafa; }
        .summary .label { font-size: 10px; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: .5px; }
        .summary .value { font-size: 16px; font-weight: 700; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        thead tr { background: #1a56db; color: #fff; }
        thead th { padding: 8px 8px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .3px; }
        tbody tr { border-bottom: 1px solid #eee; page-break-inside: avoid; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tbody td { padding: 7px 8px; vertical-align: top; }
        tfoot tr { border-top: 2px solid #222; background: #f0f0f0; }
        tfoot td { padding: 8px; font-weight: 700; }
        .text-end { text-align: right; }
        .muted { color: #888; font-size: 11px; }
        .warn { color: #b45309; font-size: 11px; font-weight: 600; }
        .containers { border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
        .containers .title { font-weight: 700; margin-bottom: 6px; }
        .containers span { display: inline-block; margin-right: 24px; }
        .footer-note { font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; margin-top: 18px; }
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            thead { display: table-header-group; }
            thead tr, .summary .box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button class="btn btn-primary" onclick="window.print()">&#128438; Cetak / Simpan PDF</button>
    <button class="btn btn-light" onclick="window.close()">Tutup</button>
    <span class="hint">Pilih tujuan <b>"Save as PDF"</b> / <b>"Simpan sebagai PDF"</b> pada dialog cetak.</span>
</div>

<div class="page">
    <div class="header">
        <div>
            <div class="company-name">WAREHOUSE 29</div>
            <div class="company-sub">Sistem Manajemen Gudang</div>
        </div>
        <div class="doc-title">
            <h2>Forecast CBM</h2>
            @if($title)<div class="ref">{{ $title }}</div>@endif
            <div class="muted">Dicetak {{ $printedAt->format('d/m/Y H:i') }}{{ $printedBy ? ' oleh '.$printedBy : '' }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="box"><div class="label">Baris SKU</div><div class="value">{{ $num($summary['sku_count'], 0) }}</div></div>
        <div class="box"><div class="label">Total Koli</div><div class="value">{{ $num($summary['total_koli'], 0) }}</div></div>
        <div class="box"><div class="label">Total CBM</div><div class="value">{{ $num($summary['total_cbm'], 4) }} m³</div></div>
        <div class="box"><div class="label">Tanpa CBM</div><div class="value">{{ $summary['incomplete_count'] }} baris</div></div>
    </div>

    <div class="containers">
        <div class="title">Estimasi kontainer (kapasitas nominal)</div>
        @foreach($containers as $container)
            <span>{{ $container['label'] }} ({{ $container['capacity'] }} m³): <b>{{ $summary['total_cbm'] > 0 ? (int) ceil($summary['total_cbm'] / $container['capacity']) : 0 }}</b> kontainer</span>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:32px">No</th>
                <th>SKU</th>
                <th>Nama Item</th>
                <th class="text-end">P × L × T (cm)</th>
                <th class="text-end">CBM / Koli</th>
                <th class="text-end">Koli</th>
                <th class="text-end">Total CBM</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><b>{{ $row['sku'] }}</b></td>
                    <td>
                        {{ $row['name'] ?? '-' }}
                        @if($row['found'] && !$row['is_active'])<span class="muted">(Nonaktif)</span>@endif
                        @if($row['note'])<div class="warn">{{ $row['note'] }}</div>@endif
                    </td>
                    <td class="text-end">
                        @if($row['length'] !== null && $row['width'] !== null && $row['height'] !== null)
                            {{ $num($row['length'], 2) }} × {{ $num($row['width'], 2) }} × {{ $num($row['height'], 2) }}
                        @else - @endif
                    </td>
                    <td class="text-end">{{ $cbm($row['cbm_per_koli']) }}</td>
                    <td class="text-end">{{ $num($row['koli'], 0) }}</td>
                    <td class="text-end"><b>{{ $row['total_cbm'] === null ? '-' : $num($row['total_cbm'], 4) }}</b></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-end">TOTAL</td>
                <td class="text-end">{{ $num($summary['total_koli'], 0) }}</td>
                <td class="text-end">{{ $num($summary['total_cbm'], 4) }} m³</td>
            </tr>
        </tfoot>
    </table>

    @if($summary['incomplete_count'] > 0)
        <div class="warn">Catatan: {{ $summary['incomplete_count'] }} baris ({{ $num($summary['incomplete_koli'], 0) }} koli) tidak terhitung karena SKU tidak ditemukan atau dimensi koli belum diisi di Master Item.</div>
    @endif

    <div class="footer-note">CBM per koli = Panjang × Lebar × Tinggi (cm) ÷ 1.000.000, berdasarkan dimensi koli di Master Item saat dokumen dicetak. Estimasi kontainer menggunakan kapasitas nominal dan belum memperhitungkan ruang kosong saat penataan.</div>
</div>

<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
</body>
</html>
