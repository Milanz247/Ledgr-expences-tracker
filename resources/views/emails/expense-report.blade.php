<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportData['period'] }} Spending Report</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .summary-boxes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-box {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: #ffffff;
        }
        .summary-box.expenses {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .summary-box.savings {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .summary-box label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .summary-box .amount {
            font-size: 24px;
            font-weight: 700;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .category-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 13px;
        }
        .category-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333333;
            border-bottom: 2px solid #e9ecef;
        }
        .category-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            color: #555555;
        }
        .category-table tr:last-child td {
            border-bottom: none;
        }
        .category-name {
            font-weight: 500;
            color: #333333;
        }
        .amount {
            text-align: right;
            font-weight: 600;
            color: #667eea;
        }
        .percentage {
            text-align: right;
            color: #999999;
            font-size: 12px;
        }
        .top-expenses {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .expense-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 13px;
        }
        .expense-item:last-child {
            border-bottom: none;
        }
        .expense-name {
            flex: 1;
            color: #333333;
        }
        .expense-category {
            color: #999999;
            font-size: 12px;
            margin-right: 10px;
        }
        .expense-amount {
            font-weight: 600;
            color: #f5576c;
            min-width: 80px;
            text-align: right;
        }
        .balances {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        .balance-card {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .balance-card h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #666666;
        }
        .balance-card .balance-amount {
            font-size: 20px;
            font-weight: 700;
            color: #333333;
        }
        .cta-button {
            display: inline-block;
            padding: 14px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            margin: 30px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            margin: 5px 0;
        }
        .footer-message {
            font-style: italic;
            color: #888888;
            margin-top: 10px;
        }

        @media (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            .summary-boxes {
                grid-template-columns: 1fr;
            }
            .balances {
                grid-template-columns: 1fr;
            }
            .category-table,
            .category-table thead,
            .category-table tbody,
            .category-table th,
            .category-table td,
            .category-table tr {
                display: block;
                width: 100%;
            }
            .category-table thead {
                display: none;
            }
            .category-table td {
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            .category-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 12px;
                font-weight: 600;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ str_replace('Last 30 Days (Test)', 'Monthly', str_replace('Last 7 Days', 'Weekly', str_replace('Yesterday', 'Daily', $reportData['period']))) }} Spending Summary</h1>
            <p>{{ now()->format('F j, Y') }}</p>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Summary Boxes -->
            <div class="summary-boxes">
                <div class="summary-box expenses">
                    <label>Total Expenses</label>
                    <div class="amount">Rs {{ number_format($reportData['total_expenses'], 0) }}</div>
                </div>
                <div class="summary-box savings">
                    <label>Net Savings</label>
                    <div class="amount">Rs {{ number_format($reportData['net_savings'], 0) }}</div>
                </div>
            </div>

            <!-- Category Breakdown -->
            <h2 class="section-title">Spending by Category</h2>
            @if ($reportData['category_breakdown']->count() > 0)
                <table class="category-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="amount">Amount</th>
                            <th class="percentage">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reportData['category_breakdown'] as $category)
                            @php
                                $percentage = $reportData['total_expenses'] > 0
                                    ? round(($category['total'] / $reportData['total_expenses']) * 100, 1)
                                    : 0;
                            @endphp
                            <tr>
                                <td class="category-name" data-label="Category">{{ $category['category_name'] }}</td>
                                <td class="amount" data-label="Amount">Rs {{ number_format($category['total'], 0) }}</td>
                                <td class="percentage" data-label="Percentage">{{ $percentage }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="color: #666666; text-align: center;">No expenses recorded for this period.</p>
            @endif

            <!-- Top 3 Expenses -->
            @if ($reportData['top_expenses']->count() > 0)
                <h2 class="section-title">Top Expenses</h2>
                <div class="top-expenses">
                    @foreach ($reportData['top_expenses'] as $index => $expense)
                        <div class="expense-item">
                            <div style="flex: 1;">
                                <div class="expense-name">{{ $index + 1 }}. {{ $expense->description ?? $expense->category->name }}</div>
                                <span class="expense-category">{{ $expense->category->name }} • {{ $expense->date->format('M d, Y') }}</span>
                            </div>
                            <div class="expense-amount">Rs {{ number_format($expense->amount, 0) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Account Balances -->
            <h2 class="section-title">Account Status</h2>
            <div class="balances">
                <div class="balance-card">
                    <h4>💳 Bank Balance</h4>
                    <div class="balance-amount">Rs {{ number_format($reportData['bank_balance'], 0) }}</div>
                </div>
                <div class="balance-card">
                    <h4>💰 Fund Balance</h4>
                    <div class="balance-amount">Rs {{ number_format($reportData['fund_balance'], 0) }}</div>
                </div>
            </div>

            <!-- Call to Action -->
            <div style="text-align: center;">
                <a href="{{ config('app.url') }}/dashboard" class="cta-button">View Dashboard</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Stay on track with your finances!</strong></p>
            <p class="footer-message">This is an automated report from your Expense Tracker. You can manage your report settings anytime.</p>
            <p style="margin-top: 15px; color: #999999; font-size: 11px;">© {{ now()->year }} Expense Tracker. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
