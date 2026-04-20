<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .sub { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .summary { margin: 12px 0; }
        .summary td { border: none; padding: 2px 8px 2px 0; }
        footer { margin-top: 24px; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if(!empty($subtitle))
        <div class="sub">{{ $subtitle }}</div>
    @endif
    <div><strong>{{ $company['name'] ?? 'Company' }}</strong></div>

    @if(!empty($summary))
        <table class="summary">
            @foreach($summary as $k => $v)
                <tr>
                    <td><strong>{{ $k }}</strong></td>
                    <td>{{ $v }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($rows))
        @php $cols = array_keys($rows[0]); @endphp
        <table>
            <thead>
            <tr>
                @foreach($cols as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($cols as $col)
                        <td>{{ $row[$col] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <footer>Generated {{ now()->toDateTimeString() }} — Loom PMS</footer>
</body>
</html>
