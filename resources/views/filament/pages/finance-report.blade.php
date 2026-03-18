<x-filament-panels::page>

    {{-- SELETTORE ANNO --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.5rem; font-weight: bold; margin: 0;">📊 Report Finance {{ $selectedYear }}</h2>
        <div style="border: 1px solid #d1d5db; border-radius: 0.375rem; background: white; padding: 0.25rem 0.5rem;">
            <select wire:model.live="selectedYear" style="border: none; font-size: 1rem; outline: none;">
                @foreach ($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- FILTRI (come Excel) --}}
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; align-items: center;">
        <div style="display: flex; align-items: center; gap: 0.5rem;" x-data="{ open: false }" @click.away="open = false">
            <label style="font-size: 0.875rem; font-weight: 500; color: #374151;">Type</label>
            <div style="position: relative;">
                <button type="button" @click="open = !open"
                    style="border: 1px solid #d1d5db; border-radius: 0.375rem; background: white; padding: 0.375rem 0.75rem; font-size: 0.875rem; min-width: 180px; text-align: left; display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                    <span
                        style="color: #374151;">{{ count($selectedTypes) > 0 ? count($selectedTypes) . ' selected' : 'All' }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20"
                        fill="currentColor" style="flex-shrink: 0;">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="open" x-transition x-cloak
                    style="position: absolute; top: 100%; left: 0; margin-top: 0.25rem; background: white; border: 1px solid #e5e7eb; border-radius: 0.375rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 50; min-width: 100%; padding: 0.25rem;">
                    @foreach ($this->getTypeOptions() as $id => $name)
                        <label
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.5rem; cursor: pointer; font-size: 0.875rem;">
                            <input type="checkbox" wire:model.live="selectedTypes" value="{{ $id }}"
                                style="rounded border-gray-300;">
                            <span>{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label style="font-size: 0.875rem; font-weight: 500; color: #374151;">Notes</label>
            <div style="border: 1px solid #d1d5db; border-radius: 0.375rem; background: white; min-width: 180px;">
                <select wire:model.live="selectedNote"
                    style="border: none; font-size: 0.875rem; outline: none; padding: 0.375rem 0.5rem; width: 100%;">
                    <option value="">(All)</option>
                    @foreach ($this->getNoteOptions() as $note => $label)
                        <option value="{{ $note }}">{{ Str::limit($note, 40) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- GRAND TOTAL --}}
    @php
        $pivotForTotals = $this->getPivotData();
        $gt = $pivotForTotals['grandTotal'];
    @endphp
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <div
            style="flex: 1; min-width: 220px; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div
                style="font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                Grand Total {{ $selectedYear }}</div>
            <div
                style="font-size: 1.5rem; font-weight: 700; font-family: monospace; color: {{ $gt >= 0 ? '#16a34a' : '#ef4444' }};">
                €{{ number_format($gt, 2, ',', '.') }}</div>
        </div>
        <div style="flex: 2; min-width: 280px; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            @foreach ($pivotForTotals['monthTotals'] as $m => $mt)
                <div
                    style="display: flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: #f9fafb; border-radius: 0.375rem; font-size: 0.8rem;">
                    <span
                        style="color: #6b7280;">{{ \Carbon\Carbon::create($selectedYear, $m)->translatedFormat('M') }}:</span>
                    <span
                        style="font-family: monospace; font-weight: 600; color: {{ $mt >= 0 ? '#16a34a' : '#ef4444' }};">€{{ number_format($mt, 2, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- LAYOUT: left table, right pie --}}
    <div style="display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; align-items: start;">

        {{-- PIVOT TABLE --}}
        @php
            $pivot = $this->getPivotData();
            $months = range(1, 12);
            $monthNames = [];
            foreach ($months as $m) {
                $monthNames[$m] = \Carbon\Carbon::create($selectedYear, $m)->translatedFormat('M');
            }
        @endphp

        <div
            style="overflow-x: auto; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                <thead>
                    <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                        <th
                            style="padding: 0.6rem 1rem; text-align: left; font-weight: 700; color: #374151; white-space: nowrap;">
                            Category</th>
                        @foreach ($monthNames as $m => $name)
                            <th
                                style="padding: 0.6rem 0.5rem; text-align: right; font-weight: 600; color: #6b7280; white-space: nowrap;">
                                {{ $name }}</th>
                        @endforeach
                        <th
                            style="padding: 0.6rem 1rem; text-align: right; font-weight: 700; color: #374151; white-space: nowrap;">
                            Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pivot['tree'] as $node)
                        @php
                            $catKey = $node['key'];
                            $catData = [];
                            $catTotal = 0;
                            if ($node['has_children']) {
                                foreach ($node['children'] as $child) {
                                    $cd = $pivot['pivot'][$child['key']] ?? [];
                                    foreach ($months as $m) {
                                        $catData[$m] = ($catData[$m] ?? 0) + ($cd[$m] ?? 0);
                                    }
                                    $catTotal += $cd['total'] ?? 0;
                                }
                            } else {
                                $catData = $pivot['pivot'][$catKey] ?? [];
                                $catTotal = $catData['total'] ?? 0;
                            }
                            $isExpanded = in_array($catKey, $expandedCategories);
                        @endphp
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 0.6rem 1rem; font-weight: 500; color: #111827; white-space: nowrap;">
                                @if ($node['has_children'])
                                    <button type="button" wire:click="toggleExpand('{{ $catKey }}')"
                                        style="background: none; border: none; cursor: pointer; padding: 0 0.25rem; margin-right: 0.25rem; display: inline-flex; align-items: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 20 20" fill="currentColor"
                                            style="transform: {{ $isExpanded ? 'rotate(90deg)' : 'rotate(0)' }}; transition: transform 0.15s;">
                                            <path fill-rule="evenodd"
                                                d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                @else
                                    <span style="display: inline-block; width: 22px;"></span>
                                @endif
                                {{ $node['label'] }}
                            </td>
                            @foreach ($months as $m)
                                @php $val = $catData[$m] ?? 0; @endphp
                                <td @dblclick="$wire.openDetail({{ $m }}, @js($catKey), @js($node['label']))"
                                    style="padding: 0.6rem 0.5rem; text-align: right; font-family: monospace; color: {{ $val >= 0 ? '#16a34a' : '#ef4444' }}; white-space: nowrap; cursor: pointer;"
                                    title="Double click for detail">
                                    {{ $val != 0 ? number_format($val, 2, ',', '.') : '-' }}
                                </td>
                            @endforeach
                            <td
                                style="padding: 0.6rem 1rem; text-align: right; font-family: monospace; font-weight: 700; color: {{ $catTotal >= 0 ? '#16a34a' : '#ef4444' }}; white-space: nowrap;">
                                {{ number_format($catTotal, 2, ',', '.') }}
                            </td>
                        </tr>
                        @if ($node['has_children'] && $isExpanded)
                            @foreach ($node['children'] as $child)
                                @php $childData = $pivot['pivot'][$child['key']] ?? []; @endphp
                                <tr style="border-bottom: 1px solid #f3f4f6; background: #fafafa;">
                                    <td
                                        style="padding: 0.5rem 1rem 0.5rem 2.5rem; font-size: 0.75rem; color: #6b7280; white-space: nowrap;">
                                        {{ $child['label'] }}
                                    </td>
                                    @foreach ($months as $m)
                                        @php $val = $childData[$m] ?? 0; @endphp
                                        <td @dblclick="$wire.openDetail({{ $m }}, @js($child['key']), @js(str_replace('|', ' › ', $child['key'])))"
                                            style="padding: 0.5rem 0.5rem; text-align: right; font-family: monospace; font-size: 0.75rem; color: {{ $val >= 0 ? '#16a34a' : '#ef4444' }}; white-space: nowrap; cursor: pointer;"
                                            title="Double click for detail">
                                            {{ $val != 0 ? number_format($val, 2, ',', '.') : '-' }}
                                        </td>
                                    @endforeach
                                    @php $childTot = $childData['total'] ?? 0; @endphp
                                    <td
                                        style="padding: 0.5rem 1rem; text-align: right; font-family: monospace; font-weight: 600; font-size: 0.75rem; color: {{ $childTot >= 0 ? '#16a34a' : '#ef4444' }}; white-space: nowrap;">
                                        {{ number_format($childTot, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #f9fafb; border-top: 2px solid #d1d5db; font-weight: 700;">
                        <td style="padding: 0.7rem 1rem; color: #374151;">GRAND TOTAL</td>
                        @foreach ($months as $m)
                            @php $mt = $pivot['monthTotals'][$m] ?? 0; @endphp
                            <td
                                style="padding: 0.7rem 0.5rem; text-align: right; font-family: monospace; color: {{ $mt >= 0 ? '#16a34a' : '#ef4444' }}; white-space: nowrap;">
                                {{ $mt != 0 ? number_format($mt, 2, ',', '.') : '-' }}
                            </td>
                        @endforeach
                        @php $gt = $pivot['grandTotal']; @endphp
                        <td
                            style="padding: 0.7rem 1rem; text-align: right; font-family: monospace; color: {{ $gt >= 0 ? '#16a34a' : '#ef4444' }}; white-space: nowrap;">
                            {{ number_format($gt, 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- GRAFICO TORTA --}}
        @php
            $pieData = $this->getPieData();
            $pieColors = [
                '#3b82f6',
                '#f97316',
                '#22c55e',
                '#a855f7',
                '#ef4444',
                '#eab308',
                '#14b8a6',
                '#ec4899',
                '#64748b',
                '#f43f5e',
            ];
        @endphp

        <div
            style="background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem 0; color: #374151;">Distribution
                {{ $selectedYear }}</h3>
            <canvas id="pieChart" style="max-height: 280px;"></canvas>

            {{-- Legend --}}
            <div style="margin-top: 1rem;">
                @foreach ($pieData as $i => $item)
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; padding: 0.25rem 0; font-size: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span
                                style="width: 12px; height: 12px; border-radius: 2px; background: {{ $pieColors[$i % count($pieColors)] }}; display: inline-block;"></span>
                            <span style="color: #374151;">{{ $item['label'] }}</span>
                        </div>
                        <span
                            style="font-family: monospace; color: #6b7280;">€{{ number_format($item['amount'], 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- SCRIPT CHART.JS --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let chartInstance = null;

        function renderPie() {
            const canvas = document.getElementById('pieChart');
            if (!canvas) return;

            // Distruggi istanza precedente se esiste
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }

            const data = @json($this->getPieData());
            const colors = ['#3b82f6', '#f97316', '#22c55e', '#a855f7', '#ef4444', '#eab308', '#14b8a6', '#ec4899',
                '#64748b', '#f43f5e'
            ];

            chartInstance = new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        data: data.map(d => d.amount),
                        backgroundColor: data.map((_, i) => colors[i % colors.length]),
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ' €' + (ctx.raw ?? ctx.parsed).toLocaleString('it-IT', {
                                    minimumFractionDigits: 2
                                })
                            }
                        }
                    }
                }
            });
        }

        // Prima inizializzazione
        document.addEventListener('DOMContentLoaded', renderPie);

        // Re-render dopo aggiornamenti Livewire
        document.addEventListener('livewire:updated', renderPie);
    </script>

    {{-- TRANSACTIONS DETAIL MODAL --}}
    @if ($showDetailModal && $detailMonth && $detailCategoryKey)
        <div style="position: fixed; inset: 0; z-index: 9999;">
            <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.5);" wire:click="closeDetail"></div>
            <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 0.5rem; max-width: 800px; width: calc(100vw - 2rem); max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);"
                @click.stop>
                <div
                    style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: #111827;">
                        {{ $detailCategoryLabel }} –
                        {{ \Carbon\Carbon::create($selectedYear, $detailMonth)->translatedFormat('F Y') }}
                    </h3>
                    <button type="button" wire:click="closeDetail"
                        style="background: none; border: none; cursor: pointer; padding: 0.25rem; color: #6b7280;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div style="overflow: auto; flex: 1; padding: 1rem 1.5rem;">
                    @php $transactions = $this->getDetailTransactions(); @endphp
                    @if ($transactions->isEmpty())
                        <p style="color: #6b7280; font-size: 0.875rem;">No transaction found.</p>
                    @else
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                            <thead>
                                <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                                    <th style="padding: 0.5rem 0.75rem; text-align: left;">Date</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: left;">Description</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: left;">Account</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: right;">Amount</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: center;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $tx)
                                    <tr style="border-bottom: 1px solid #f3f4f6;">
                                        <td style="padding: 0.5rem 0.75rem;">{{ $tx->date->format('d/m/Y') }}</td>
                                        <td style="padding: 0.5rem 0.75rem;">{{ $tx->description ?: '-' }}</td>
                                        <td style="padding: 0.5rem 0.75rem;">{{ $tx->account?->name ?? '-' }}</td>
                                        <td
                                            style="padding: 0.5rem 0.75rem; text-align: right; font-family: monospace; color: {{ $tx->amount >= 0 ? '#16a34a' : '#ef4444' }};">
                                            €{{ number_format($tx->amount, 2, ',', '.') }}
                                        </td>
                                        <td style="padding: 0.5rem 0.75rem; text-align: center;">
                                            <a href="{{ route('filament.admin.resources.transactions.edit', ['record' => $tx]) }}"
                                                style="color: #3b82f6; font-size: 0.75rem;">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr style="background: #f9fafb; font-weight: 700; border-top: 2px solid #e5e7eb;">
                                    <td colspan="3" style="padding: 0.75rem;">Total</td>
                                    <td
                                        style="padding: 0.75rem; text-align: right; font-family: monospace; color: {{ $transactions->sum('amount') >= 0 ? '#16a34a' : '#ef4444' }};">
                                        €{{ number_format($transactions->sum('amount'), 2, ',', '.') }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    @endif



</x-filament-panels::page>
