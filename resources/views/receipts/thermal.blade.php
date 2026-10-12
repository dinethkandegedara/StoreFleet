<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $receipt['sale']['sale_number'] }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            line-height: 1.35;
            color: #000;
            background: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 12px;
        }

        .actions-bar {
            width: 80mm;
            max-width: 100%;
            margin-bottom: 16px;
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .btn {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .btn-close {
            background: #4b5563;
        }

        .btn-close:hover {
            background: #374151;
        }

        .receipt-card {
            width: 80mm;
            max-width: 100%;
            background: #fff;
            padding: 16px 14px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .cancelled-banner {
            border: 2px dashed #dc2626;
            background: #fef2f2;
            color: #b91c1c;
            text-align: center;
            padding: 6px;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .italic { font-style: italic; }

        .business-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .branch-info {
            font-size: 12px;
            color: #222;
            margin-bottom: 8px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .double-divider {
            border-top: 2px solid #000;
            margin: 8px 0;
        }

        .meta-table, .items-table, .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .meta-table td {
            padding: 2px 0;
        }

        .items-table th {
            text-align: left;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .items-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .item-row-sub {
            font-size: 11px;
            color: #444;
            padding-left: 4px;
        }

        .totals-table td {
            padding: 2px 0;
        }

        .total-amount-row {
            font-size: 15px;
            font-weight: bold;
        }

        .receipt-header-text, .receipt-footer-text {
            font-size: 11px;
            text-align: center;
            margin: 6px 0;
            color: #333;
            white-space: pre-line;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }

            .actions-bar {
                display: none !important;
            }

            .receipt-card {
                box-shadow: none;
                padding: 4px 2px;
                width: 100%;
                max-width: 80mm;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="actions-bar">
        <button class="btn" onclick="window.print()">Print Receipt</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    <div class="receipt-card">
        @if($receipt['sale']['is_cancelled'])
            <div class="cancelled-banner">
                *** CANCELLED / VOIDED ***
                <div style="font-size: 10px; font-weight: normal; margin-top: 2px;">
                    Reason: {{ $receipt['sale']['cancellation_reason'] }}
                </div>
            </div>
        @endif

        <div class="text-center">
            @if(!empty($receipt['business']['logo_path']) && $receipt['business']['show_logo'])
                <div style="margin-bottom: 6px;">
                    <img src="{{ $receipt['business']['logo_path'] }}" alt="Logo" style="max-height: 48px; max-width: 160px; object-fit: contain;">
                </div>
            @endif
            <div class="business-title">{{ $receipt['business']['name'] }}</div>
            <div class="branch-info">
                <div class="bold">{{ $receipt['branch']['name'] }} ({{ $receipt['branch']['code'] }})</div>
                @if(!empty($receipt['branch']['address']))
                    <div>{{ $receipt['branch']['address'] }}</div>
                @endif
                @if(!empty($receipt['branch']['phone']))
                    <div>Tel: {{ $receipt['branch']['phone'] }}</div>
                @endif
                @if(!empty($receipt['business']['tax_id']))
                    <div>Tax ID: {{ $receipt['business']['tax_id'] }}</div>
                @endif
            </div>
        </div>

        @if(!empty($receipt['header']))
            <div class="receipt-header-text">{{ $receipt['header'] }}</div>
        @endif

        <div class="divider"></div>

        <table class="meta-table">
            <tr>
                <td class="bold">Receipt #:</td>
                <td class="text-right bold">{{ $receipt['sale']['sale_number'] }}</td>
            </tr>
            <tr>
                <td>Date / Time:</td>
                <td class="text-right">{{ $receipt['sale']['completed_at_formatted'] }}</td>
            </tr>
            <tr>
                <td>Cashier:</td>
                <td class="text-right">{{ $receipt['sale']['cashier_name'] }}</td>
            </tr>
            @if(!empty($receipt['sale']['customer_name']))
                <tr>
                    <td>Customer:</td>
                    <td class="text-right">{{ $receipt['sale']['customer_name'] }}</td>
                </tr>
            @endif
            @if(!empty($receipt['sale']['customer_phone']))
                <tr>
                    <td>Contact:</td>
                    <td class="text-right">{{ $receipt['sale']['customer_phone'] }}</td>
                </tr>
            @endif
        </table>

        <div class="divider"></div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Item</th>
                    <th style="width: 15%; text-align: center;">Qty</th>
                    <th style="width: 17%; text-align: right;">Price</th>
                    <th style="width: 18%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt['items'] as $item)
                    <tr>
                        <td colspan="4" class="bold" style="padding-top: 4px;">
                            {{ $item['name'] }}
                        </td>
                    </tr>
                    <tr>
                        <td class="item-row-sub">
                            SKU: {{ $item['sku'] }}
                            @if($item['discount'] > 0)
                                <span style="color: #b91c1c;">(Disc: -{{ $receipt['currency']['symbol'] }}{{ number_format($item['discount'], $receipt['currency']['precision']) }})</span>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item['quantity'] + 0 }}</td>
                        <td style="text-align: right;">{{ $receipt['currency']['symbol'] }}{{ number_format($item['unit_price'], $receipt['currency']['precision']) }}</td>
                        <td style="text-align: right;" class="bold">{{ $receipt['currency']['symbol'] }}{{ number_format($item['total'], $receipt['currency']['precision']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">{{ $receipt['currency']['symbol'] }}{{ number_format($receipt['totals']['subtotal'], $receipt['currency']['precision']) }}</td>
            </tr>
            @if($receipt['totals']['discount_total'] > 0)
                <tr>
                    <td>Total Discounts:</td>
                    <td class="text-right" style="color: #b91c1c;">-{{ $receipt['currency']['symbol'] }}{{ number_format($receipt['totals']['discount_total'], $receipt['currency']['precision']) }}</td>
                </tr>
            @endif
            @if($receipt['totals']['tax_total'] > 0)
                <tr>
                    <td>Tax:</td>
                    <td class="text-right">{{ $receipt['currency']['symbol'] }}{{ number_format($receipt['totals']['tax_total'], $receipt['currency']['precision']) }}</td>
                </tr>
            @endif
            <tr class="total-amount-row">
                <td style="padding-top: 4px; padding-bottom: 4px;" class="bold">TOTAL DUE:</td>
                <td style="padding-top: 4px; padding-bottom: 4px;" class="text-right bold">
                    {{ $receipt['currency']['symbol'] }}{{ number_format($receipt['totals']['total_amount'], $receipt['currency']['precision']) }}
                </td>
            </tr>
        </table>

        <div class="double-divider"></div>

        <div class="bold" style="font-size: 11px; margin-bottom: 4px; text-transform: uppercase;">Payment Details:</div>
        <table class="totals-table">
            @foreach($receipt['payments'] as $payment)
                <tr>
                    <td>{{ $payment['method'] }}:</td>
                    <td class="text-right">{{ $receipt['currency']['symbol'] }}{{ number_format($payment['amount'], $receipt['currency']['precision']) }}</td>
                </tr>
                @if($payment['tendered'] !== null && $payment['tendered'] > 0)
                    <tr>
                        <td style="padding-left: 8px; color: #555;">Tendered:</td>
                        <td class="text-right" style="color: #555;">{{ $receipt['currency']['symbol'] }}{{ number_format($payment['tendered'], $receipt['currency']['precision']) }}</td>
                    </tr>
                @endif
                @if($payment['change'] > 0)
                    <tr>
                        <td style="padding-left: 8px; color: #555;">Change Due:</td>
                        <td class="text-right bold">{{ $receipt['currency']['symbol'] }}{{ number_format($payment['change'], $receipt['currency']['precision']) }}</td>
                    </tr>
                @endif
                @if(!empty($payment['reference']))
                    <tr>
                        <td colspan="2" style="font-size: 10px; color: #666; padding-left: 8px;">Ref: {{ $payment['reference'] }}</td>
                    </tr>
                @endif
            @endforeach
        </table>

        @if(!empty($receipt['sale']['notes']))
            <div class="divider"></div>
            <div style="font-size: 11px; margin-top: 4px;">
                <span class="bold">Note:</span> {{ $receipt['sale']['notes'] }}
            </div>
        @endif

        <div class="divider"></div>

        @if(!empty($receipt['footer']))
            <div class="receipt-footer-text">{{ $receipt['footer'] }}</div>
        @endif

        <div class="text-center" style="font-size: 10px; color: #666; margin-top: 8px;">
            Powered by StoreFleet POS
        </div>
    </div>

    @if(request()->has('autoprint'))
        <script>
            window.addEventListener('load', function() {
                window.print();
            });
        </script>
    @endif
</body>
</html>
