<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Growing Cycle Report — {{ $cycle->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
        .header { background: linear-gradient(135deg, #166534 0%, #15803d 100%); color: #fff; padding: 28px 32px; margin-bottom: 24px; }
        .header h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .header p { font-size: 11px; opacity: 0.85; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .badge-active   { background: #dcfce7; color: #15803d; }
        .badge-completed { background: #dbeafe; color: #1d4ed8; }
        .badge-cancelled { background: #fee2e2; color: #b91c1c; }
        .section { margin: 0 32px 20px; }
        .section-title { font-size: 13px; font-weight: 700; color: #166534; border-bottom: 2px solid #bbf7d0; padding-bottom: 6px; margin-bottom: 12px; }
        .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .meta-item { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; }
        .meta-label { font-size: 9px; font-weight: 600; text-transform: uppercase; color: #6b7280; margin-bottom: 3px; }
        .meta-value { font-size: 13px; font-weight: 700; color: #166534; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead tr { background: #166534; color: #fff; }
        thead th { padding: 7px 10px; text-align: left; font-weight: 600; font-size: 10px; }
        tbody tr:nth-child(even) { background: #f0fdf4; }
        tbody td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; }
        .breach-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .breach-card { background: #fef9c3; border: 1px solid #fde047; border-radius: 8px; padding: 10px 14px; text-align: center; }
        .breach-card.ok { background: #f0fdf4; border-color: #bbf7d0; }
        .breach-num { font-size: 22px; font-weight: 700; color: #ca8a04; }
        .breach-card.ok .breach-num { color: #15803d; }
        .breach-label { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .footer { margin: 24px 32px 0; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 9px; color: #94a3b8; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $cycle->name }}</h1>
    <p>Growing Cycle Report &bull; Generated {{ now()->format('F d, Y \a\t h:i A') }} &bull; Cotabato State University — BS Agriculture</p>
</div>

<!-- Cycle Info -->
<div class="section">
    <div class="section-title">Cycle Information</div>
    <div class="meta-grid">
        <div class="meta-item">
            <div class="meta-label">Mushroom Variety</div>
            <div class="meta-value">{{ $cycle->mushroom_variety }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Substrate Type</div>
            <div class="meta-value">{{ $cycle->substrate_type }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Status</div>
            <div class="meta-value"><span class="badge badge-{{ $cycle->status }}">{{ ucfirst($cycle->status) }}</span></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Start Date</div>
            <div class="meta-value">{{ \Carbon\Carbon::parse($cycle->start_date)->format('M d, Y') }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">End Date</div>
            <div class="meta-value">{{ $cycle->end_date ? \Carbon\Carbon::parse($cycle->end_date)->format('M d, Y') : 'Ongoing' }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Duration</div>
            <div class="meta-value">{{ $dayCount }} days</div>
        </div>
    </div>
    @if($cycle->notes)
    <div style="margin-top:10px; background:#f8fafc; border-left:3px solid #15803d; padding:8px 12px; font-size:10px; color:#475569;">
        <strong>Notes:</strong> {{ $cycle->notes }}
    </div>
    @endif
</div>

<!-- Threshold Breaches -->
<div class="section">
    <div class="section-title">Threshold Breach Summary ({{ number_format($breachSummary['total_readings']) }} total readings)</div>
    <div class="breach-grid">
        <div class="breach-card {{ $breachSummary['temperature'] === 0 ? 'ok' : '' }}">
            <div class="breach-num">{{ number_format($breachSummary['temperature']) }}</div>
            <div class="breach-label">Temperature Breaches<br>(outside 24–30°C)</div>
        </div>
        <div class="breach-card {{ $breachSummary['humidity'] === 0 ? 'ok' : '' }}">
            <div class="breach-num">{{ number_format($breachSummary['humidity']) }}</div>
            <div class="breach-label">Humidity Breaches<br>(below 80%)</div>
        </div>
        <div class="breach-card {{ $breachSummary['co2'] === 0 ? 'ok' : '' }}">
            <div class="breach-num">{{ number_format($breachSummary['co2']) }}</div>
            <div class="breach-label">CO₂ Breaches<br>(above 1000 ppm)</div>
        </div>
        <div class="breach-card {{ $breachSummary['soil_moisture'] === 0 ? 'ok' : '' }}">
            <div class="breach-num">{{ number_format($breachSummary['soil_moisture']) }}</div>
            <div class="breach-label">Soil Moisture Alerts<br>(below 30%)</div>
        </div>
    </div>
</div>

<!-- Sensor Averages -->
@if($dailyAverages->count())
<div class="section">
    <div class="section-title">Daily Sensor Averages</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Avg Temperature (°C)</th>
                <th>Avg Humidity (%)</th>
                <th>Avg CO₂ (ppm)</th>
                <th>Avg Light (lux)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyAverages as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row->date)->format('M d, Y') }}</td>
                <td>{{ $row->avg_temperature ?? '—' }}</td>
                <td>{{ $row->avg_humidity ?? '—' }}</td>
                <td>{{ $row->avg_co2 ?? '—' }}</td>
                <td>{{ $row->avg_light ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- Measurements -->
@if($measurements->count())
<div class="section">
    <div class="section-title">Mushroom Measurements ({{ $measurements->count() }} records)</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Flush #</th>
                <th>Height (cm)</th>
                <th>Cap Diam. (cm)</th>
                <th>Fruiting Bodies</th>
                <th>Logged By</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($measurements as $m)
            <tr>
                <td>{{ \Carbon\Carbon::parse($m->observed_date)->format('M d, Y') }}</td>
                <td>Flush {{ $m->flush_number }}</td>
                <td>{{ $m->height_cm ?? '—' }}</td>
                <td>{{ $m->cap_diameter_cm ?? '—' }}</td>
                <td>{{ $m->fruiting_body_count ?? '—' }}</td>
                <td>{{ $m->user?->name ?? '—' }}</td>
                <td>{{ $m->notes ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="footer">
    <span>Cotabato State University — IoT Mushroom Monitoring System</span>
    <span>Gray Oyster Mushroom (Pleurotus sajor-caju)</span>
    <span>Printed: {{ now()->format('Y-m-d H:i') }}</span>
</div>

</body>
</html>
