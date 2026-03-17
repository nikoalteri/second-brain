<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">📊 Cashflow Report</x-slot>

        <div style="overflow-x: auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #e5e7eb;">
                        <th
                            style="text-align:left; padding:10px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">
                            Periodo</th>
                        <th
                            style="text-align:right; padding:10px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#16a34a;">
                            Entrate</th>
                        <th
                            style="text-align:right; padding:10px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#ef4444;">
                            Uscite</th>
                        <th
                            style="text-align:right; padding:10px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">
                            Netto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding:10px 16px; font-weight:500;">
                                {{ \Carbon\Carbon::create($row->year, $row->month)->translatedFormat('F Y') }}
                            </td>
                            <td style="padding:10px 16px; text-align:right; color:#16a34a; font-family:monospace;">
                                € {{ number_format($row->earnings, 2, ',', '.') }}
                            </td>
                            <td style="padding:10px 16px; text-align:right; color:#ef4444; font-family:monospace;">
                                € {{ number_format($row->expenses, 2, ',', '.') }}
                            </td>
                            <td
                                style="padding:10px 16px; text-align:right; font-family:monospace; font-weight:600; color:{{ $row->net >= 0 ? '#16a34a' : '#ef4444' }};">
                                {{ $row->net >= 0 ? '+' : '' }}€ {{ number_format($row->net, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center; padding:2rem; color:#9ca3af;">
                                No data available
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid #d1d5db; background:#f9fafb;">
                        <td
                            style="padding:10px 16px; font-weight:700; font-size:0.75rem; text-transform:uppercase; color:#374151;">
                            Totale (12 mesi)</td>
                        <td
                            style="padding:10px 16px; text-align:right; font-weight:700; color:#16a34a; font-family:monospace;">
                            € {{ number_format($totalEarnings, 2, ',', '.') }}
                        </td>
                        <td
                            style="padding:10px 16px; text-align:right; font-weight:700; color:#ef4444; font-family:monospace;">
                            € {{ number_format($totalExpenses, 2, ',', '.') }}
                        </td>
                        <td
                            style="padding:10px 16px; text-align:right; font-weight:700; font-family:monospace; color:{{ $totalNet >= 0 ? '#16a34a' : '#ef4444' }};">
                            {{ $totalNet >= 0 ? '+' : '' }}€ {{ number_format($totalNet, 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </x-filament::section>
</x-filament-widgets::widget>
