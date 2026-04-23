<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Report {{ $snapshot['selected_year'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        h2 { font-size: 16px; margin: 24px 0 8px; }
        p { margin: 4px 0; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Finance Report {{ $snapshot['selected_year'] }}</h1>
    <p>Exported finance report data only. Budget and alert sections are intentionally excluded.</p>

    @foreach ($snapshot['export_sections'] as $section)
        <h2>{{ $section['heading'] }}</h2>

        <table>
            <tbody>
                @foreach ($section['rows'] as $rowIndex => $row)
                    @if ($rowIndex === 0)
                        <tr>
                            @foreach ($row as $cell)
                                <th>{{ $cell }}</th>
                            @endforeach
                        </tr>
                    @else
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
