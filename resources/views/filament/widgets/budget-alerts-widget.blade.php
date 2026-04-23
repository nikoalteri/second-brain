<x-filament-widgets::widget>
    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1rem;">
            <div>
                <h3 style="font-size: 1rem; font-weight: 700; margin: 0; color: #111827;">Budget Alerts</h3>
                <p style="margin: 0.25rem 0 0; color: #6b7280; font-size: 0.875rem;">Current month alerts for warning, exceeded, and critical categories.</p>
            </div>
            <span style="font-size: 0.875rem; color: #6b7280;">{{ $selectedMonthLabel }}</span>
        </div>

        @if (empty($alerts))
            <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">No current budget alerts.</p>
        @else
            <div style="display: grid; gap: 0.75rem;">
                @foreach ($alerts as $alert)
                    @php
                        $color = match ($alert['alert_status']) {
                            'warning' => '#d97706',
                            'exceeded' => '#dc2626',
                            'critical' => '#991b1b',
                            default => '#6b7280',
                        };
                    @endphp
                    <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: 0.85rem 1rem; border-radius: 0.5rem; background: {{ $color }}10; border: 1px solid {{ $color }}30;">
                        <div>
                            <div style="font-weight: 700; color: #111827;">{{ $alert['name'] }}</div>
                            <div style="font-size: 0.75rem; color: #6b7280;">{{ $alert['parent_name'] ?? 'Uncategorized' }}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.75rem; font-weight: 700; text-transform: lowercase; color: {{ $color }};">{{ $alert['alert_status'] }}</div>
                            <div style="font-size: 0.8rem; color: #374151;">€{{ number_format($alert['spent_amount'], 2, ',', '.') }} / {{ $alert['budget_amount'] === null ? '—' : '€' . number_format($alert['budget_amount'], 2, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
