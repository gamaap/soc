<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Formulir Penerimaan Tamu - {{ $visitor->name }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #000;
            margin: 0;
            padding: 8px;
        }

        .sheet {
            width: 72mm;
            margin: 0 auto;
        }

        .title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td { vertical-align: top; padding: 1px 0; }
        table.fields td.label { width: 40%; white-space: nowrap; }
        table.fields td.sep { width: 4%; }
        table.fields td.value { width: 56%; font-weight: bold; }

        .place-date {
            margin-top: 14px;
            font-weight: bold;
        }

        table.signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.signatures td {
            border: 1px solid #000;
            width: 50%;
            text-align: center;
            font-size: 8pt;
            padding: 4px 2px;
            vertical-align: top;
        }
        table.signatures .sign-space { height: 46px; }
        table.signatures .sign-name { font-weight: bold; }

        .actions { text-align: center; margin-top: 14px; }
        .actions button {
            padding: 6px 16px;
            font-size: 10pt;
            cursor: pointer;
        }

        @media print {
            .actions { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="title">Formulir Penerimaan Tamu</div>

        <table class="fields">
            <tr>
                <td class="label">Nama Lengkap</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->name }}</td>
            </tr>
            <tr>
                <td class="label">Asal (Perusahaan / Instansi)</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->company ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Orang yang Dituju</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->visiting }}</td>
            </tr>
            <tr>
                <td class="label">Bagian</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->visiting_section ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal dan Waktu Masuk</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->formatted_date }} {{ $visitor->entry_time }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal dan Waktu Keluar</td>
                <td class="sep">:</td>
                <td class="value">&nbsp;</td>
            </tr>
            <tr>
                <td class="label">No. Kartu Visitor</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->card_number ?: '-' }}</td>
            </tr>
        </table>

        <div class="place-date">Bandung, {{ now()->format('d/m/Y') }}</div>

        <table class="signatures">
            <tr>
                <td>
                    Tanda Tangan
                    <div class="sign-space"></div>
                    <div class="sign-name">{{ $visitor->name }}</div>
                </td>
                <td>
                    Tanda Tangan
                    <div class="sign-space"></div>
                    <div class="sign-name">{{ $visitor->visiting }}</div>
                </td>
            </tr>
        </table>

        <div class="actions">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Tutup</button>
        </div>
    </div>
</body>
</html>
