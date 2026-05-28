<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan {{ $suratJalan->code }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; background: #f5f5f5; }
        .page { background: #fff; max-width: 800px; margin: 20px auto; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .no-print { margin-bottom: 16px; display: flex; gap: 8px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #1a56db; color: #fff; }
        .btn-light { background: #e9ecef; color: #333; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 16px; margin-bottom: 20px; }
        .company-name { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
        .company-sub { font-size: 12px; color: #666; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #1a56db; }
        .doc-title .code { font-size: 14px; font-weight: 600; margin-top: 4px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; margin-bottom: 24px; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px; background: #fafafa; }
        .meta-item .label { font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: .5px; }
        .meta-item .value { font-size: 13px; color: #222; margin-top: 2px; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead tr { background: #1a56db; color: #fff; }
        thead th { padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
        thead th.text-center { text-align: center; }
        tbody tr { border-bottom: 1px solid #eee; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tbody td { padding: 10px 12px; }
        tfoot tr { border-top: 2px solid #222; background: #f0f0f0; }
        tfoot td { padding: 10px 12px; font-weight: 700; }
        .signature-section { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 40px; }
        .signature-box { text-align: center; }
        .signature-box .title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: #555; margin-bottom: 60px; }
        .signature-box .line { border-top: 1px solid #333; padding-top: 8px; font-size: 12px; }
        .note-section { background: #fffde7; border: 1px solid #f0c040; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 12px; }
        .note-section .note-label { font-weight: 700; margin-bottom: 4px; }
        .footer-note { font-size: 11px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 12px; margin-top: 24px; }
        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; padding: 24px; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button class="btn btn-primary" onclick="window.print()">&#128438; Cetak</button>
    <a href="{{ $backUrl }}" class="btn btn-light">&#8592; Kembali</a>
</div>

<div class="page">
    <div class="header">
        <div>
            <div class="company-name">WAREHOUSE 29</div>
            <div class="company-sub">Sistem Manajemen Gudang</div>
        </div>
        <div class="doc-title">
            <h2>Surat Jalan</h2>
            <div class="code">{{ $suratJalan->code }}</div>
            <div style="font-size:12px;color:#888;margin-top:4px;">{{ $suratJalan->created_at?->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-item">
            <div class="label">No. Transaksi</div>
            <div class="value">{{ $transaction->code }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Ref No</div>
            <div class="value">{{ $transaction->ref_no ?: '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Tanggal Transaksi</div>
            <div class="value">{{ $transaction->transacted_at?->format('d/m/Y H:i') }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Tanggal Surat Jalan</div>
            <div class="value">{{ $suratJalan->created_at?->format('d/m/Y H:i') }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Dibuat Oleh</div>
            <div class="value">{{ $suratJalan->creator?->name ?? '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Disetujui Oleh</div>
            <div class="value">{{ $transaction->approver?->name ?? '-' }}</div>
        </div>
    </div>

    @if($transaction->note)
    <div class="note-section">
        <div class="note-label">Catatan:</div>
        <div>{{ $transaction->note }}</div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>SKU</th>
                <th>Nama Item</th>
                <th class="text-center" style="width:80px">Qty</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->item?->sku ?? '-' }}</td>
                <td>{{ $row->item?->name ?? '-' }}</td>
                <td style="text-align:center;font-weight:600">{{ $row->qty }}</td>
                <td>{{ $row->note ?: '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right">Total</td>
                <td style="text-align:center">{{ $totalQty }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="title">Dibuat Oleh</div>
            <div class="line">{{ $suratJalan->creator?->name ?? '.........................' }}</div>
        </div>
        <div class="signature-box">
            <div class="title">Diserahkan Oleh</div>
            <div class="line">.............................</div>
        </div>
        <div class="signature-box">
            <div class="title">Diterima Oleh</div>
            <div class="line">.............................</div>
        </div>
    </div>

    <div class="footer-note">
        Dokumen ini dicetak secara otomatis oleh sistem. &mdash; {{ $suratJalan->code }} &mdash; {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
