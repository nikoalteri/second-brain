<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">📊 Cashflow Report</x-slot>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-2 font-semibold text-gray-600 dark:text-gray-400">Periodo</th>
                    <th class="text-right py-2 font-semibold text-green-600">Entrate</th>
                    <th class="text-right py-2 font-semibold text-red-600">Uscite</th>
                    <th class="text-right py-2 font-semibold text-gray-600 dark:text-gray-400">Netto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr
                        class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <td class="py-2 text-gray-700 dark:text-gray-300">
                            {{ \Carbon\Carbon::create($row->year, $row->month)->translatedFormat('F Y') }}
                        </td>
                        <td class="text-right py-2 text-green-600">
                            € {{ number_format($row->earnings, 2, ',', '.') }}
                        </td>
                        <td class="text-right py-2 text-red-600">
                            € {{ number_format($row->expenses, 2, ',', '.') }}
                        </td>
                        <td
                            class="text-right py-2 font-medium {{ $row->net >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            € {{ number_format($row->net, 2, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-6 text-gray-400">
                            Nessun dato disponibile
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                    <td class="py-3 text-gray-700 dark:text-gray-300">Totale (12 mesi)</td>
                    <td class="text-right py-3 text-green-600">
                        € {{ number_format($totalEarnings, 2, ',', '.') }}
                    </td>
                    <td class="text-right py-3 text-red-600">
                        € {{ number_format($totalExpenses, 2, ',', '.') }}
                    </td>
                    <td class="text-right py-3 {{ $totalNet >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        € {{ number_format($totalNet, 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
