<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $trip->title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            border-bottom: 2px solid #3b82f6;
            margin-bottom: 20px;
            padding-bottom: 15px;
        }
        h1 {
            margin: 0 0 5px 0;
            color: #1f2937;
        }
        .trip-info {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            background-color: #f3f4f6;
            padding: 10px 15px;
            margin: 0 0 15px 0;
            font-size: 16px;
            border-left: 4px solid #3b82f6;
            color: #1f2937;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        .activity {
            margin-bottom: 12px;
            padding: 10px;
            background-color: #f9fafb;
            border-left: 3px solid #3b82f6;
        }
        .activity-time {
            font-weight: bold;
            color: #1f2937;
            font-size: 14px;
        }
        .activity-title {
            margin: 3px 0;
            font-size: 13px;
        }
        .activity-type {
            color: #666;
            font-size: 12px;
        }
        .activity-cost {
            color: #059669;
            font-weight: bold;
        }
        .activity-description {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            font-style: italic;
        }
        .budget-summary {
            background-color: #eff6ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .budget-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #dbeafe;
        }
        .budget-row.total {
            border-bottom: none;
            font-weight: bold;
            color: #1f2937;
            margin-top: 5px;
            padding-top: 10px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            color: #999;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <h1>{{ $trip->title }}</h1>
        <div class="trip-info">
            <strong>Dates:</strong> {{ $trip->start_date->format('M d, Y') }} — {{ $trip->end_date->format('M d, Y') }}<br>
            <strong>Duration:</strong> {{ $trip->start_date->diffInDays($trip->end_date) + 1 }} days<br>
            @if($destinations->count())
                <strong>Destinations:</strong> {{ $destinations->pluck('name')->join(', ') }}<br>
            @endif
            @if($trip->description)
                <strong>Description:</strong> {{ Str::limit($trip->description, 150) }}
            @endif
        </div>
    </div>

    <!-- Destinations Section -->
    @if($destinations->count())
    <div class="section">
        <h2>Destinations</h2>
        <table>
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Country</th>
                    <th>Timezone</th>
                </tr>
            </thead>
            <tbody>
                @foreach($destinations as $dest)
                <tr>
                    <td><strong>{{ $dest->name }}</strong></td>
                    <td>{{ $dest->country ?? '—' }}</td>
                    <td>{{ $dest->timezone ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Itineraries & Activities Section -->
    @if($itineraries->count())
    <div class="section">
        <h2>Itineraries & Activities</h2>
        @foreach($itineraries as $itinerary)
            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 14px;">
                    {{ $itinerary->date->format('l, M d, Y') }}
                    @if($itinerary->destination)
                        — {{ $itinerary->destination->name }}
                    @endif
                </h3>
                
                @if($itinerary->activities->count())
                    @foreach($itinerary->activities as $activity)
                    <div class="activity">
                        <div class="activity-time">
                            @if($activity->start_time)
                                {{ \Carbon\Carbon::parse($activity->start_time)->format('h:i A') }}
                            @else
                                All day
                            @endif
                        </div>
                        <div class="activity-title">{{ $activity->title }}</div>
                        <div class="activity-type">
                            Type: {{ $activity->type ?? '—' }}
                            @if($activity->cost)
                                <span class="activity-cost">${{ number_format($activity->cost, 2) }}</span>
                            @endif
                        </div>
                        @if($activity->description)
                        <div class="activity-description">{{ Str::limit($activity->description, 100) }}</div>
                        @endif
                    </div>
                    @endforeach
                @else
                    <p style="color: #999; font-size: 13px;">No activities scheduled</p>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <!-- Budget Section -->
    @if($budget)
    <div class="section">
        <h2>Budget Summary</h2>
        <div class="budget-summary">
            <div class="budget-row">
                <span>Initial Budget:</span>
                <span>${{ number_format($budget->initial_amount, 2) }}</span>
            </div>
            @php
                $totalExpenses = $expenses ? $expenses->sum('amount') : 0;
                $remaining = $budget->initial_amount - $totalExpenses;
            @endphp
            <div class="budget-row">
                <span>Total Expenses:</span>
                <span>${{ number_format($totalExpenses, 2) }}</span>
            </div>
            <div class="budget-row total">
                <span>Remaining:</span>
                <span style="color: {{ $remaining >= 0 ? '#059669' : '#dc2626' }};">
                    ${{ number_format($remaining, 2) }}
                </span>
            </div>
        </div>
    </div>
    @endif

    <!-- Participants Section -->
    @if($participants->count())
    <div class="section">
        <h2>Participants</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                @foreach($participants as $participant)
                <tr>
                    <td>{{ $participant->name }}</td>
                    <td>{{ $participant->email ?? '—' }}</td>
                    <td>{{ $participant->role ?? 'Participant' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        Generated on {{ now()->format('M d, Y \a\t h:i A') }} | Second Brain Travel System
    </div>
</body>
</html>
