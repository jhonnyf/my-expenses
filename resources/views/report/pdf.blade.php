<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Gastos</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { font-size: 20px; text-align: center; margin-bottom: 4px; }
        .period { text-align: center; font-size: 11px; color: #666; margin-bottom: 20px; }
        .summary { margin-bottom: 20px; }
        .summary span { display: inline-block; margin-right: 30px; }
        .summary strong { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 11px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        .section-title { font-size: 14px; font-weight: bold; margin: 15px 0 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
    </style>
</head>
<body>
    <h1>Relatório de Gastos</h1>
    <p class="period">
        Período: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }}
        a {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
    </p>

    <div class="summary">
        <span>Total Gasto: <strong>R$ {{ number_format($summary->total_amount ?? 0, 2, ',', '.') }}</strong></span>
        <span>Total de Itens: <strong>{{ $summary->total_items ?? 0 }}</strong></span>
        <span>Total de Notas: <strong>{{ $summary->total_invoices ?? 0 }}</strong></span>
    </div>

    @if($categoryBreakdown->isNotEmpty())
        <p class="section-title">Resumo por Categoria</p>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                @php $catTotal = $categoryBreakdown->sum('total') ?: 1; @endphp
                @foreach($categoryBreakdown as $cat)
                    <tr>
                        <td>{{ $cat->category_name }}</td>
                        <td class="text-right">R$ {{ number_format($cat->total, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format(($cat->total / $catTotal) * 100, 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="section-title">Itens Detalhados</p>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Emissor</th>
                <th>Produto</th>
                <th>Categoria</th>
                <th class="text-right">Qtd</th>
                <th class="text-right">Preço Unit.</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item->issued_at)->format('d/m/Y') }}</td>
                    <td>{{ $item->issuer_name }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->category_name }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Nenhum item encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
