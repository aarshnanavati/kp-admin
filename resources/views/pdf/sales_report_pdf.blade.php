<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sales &amp; Orders Log Report</title>
  <style>
    @page {
      margin: 10mm 8mm;
      size: A4 landscape;
    }
    body {
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      font-size: 8pt;
      color: #1e293b;
      line-height: 1.3;
      margin: 0;
      padding: 0;
      background: #ffffff;
    }
    
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
      border-bottom: 2px solid #e11d48;
      padding-bottom: 8px;
    }
    .brand-title {
      font-size: 16pt;
      font-weight: 800;
      color: #e11d48;
      letter-spacing: -0.5px;
      margin: 0;
    }
    .brand-subtitle {
      font-size: 9pt;
      color: #475569;
      margin: 2px 0 0 0;
      font-weight: 600;
    }
    .meta-box {
      text-align: right;
      font-size: 7.5pt;
      color: #64748b;
    }
    .meta-highlight {
      font-size: 9pt;
      font-weight: 700;
      color: #0f172a;
    }

    .kpi-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
    }
    .kpi-cell {
      padding: 6px 10px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      width: 25%;
    }
    .kpi-label {
      font-size: 7pt;
      text-transform: uppercase;
      font-weight: 700;
      color: #64748b;
      margin-bottom: 2px;
    }
    .kpi-value {
      font-size: 11pt;
      font-weight: 800;
      color: #0f172a;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
      font-size: 7.2pt;
    }
    .data-table th {
      background-color: #0f172a;
      color: #ffffff;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 6.8pt;
      letter-spacing: 0.3px;
      padding: 5px 6px;
      border: 1px solid #0f172a;
      text-align: left;
    }
    .data-table td {
      padding: 4px 6px;
      border: 1px solid #e2e8f0;
      vertical-align: middle;
    }
    .data-table tr:nth-child(even) td {
      background-color: #f8fafc;
    }

    .badge-order-id {
      font-family: monospace;
      font-weight: 700;
      color: #e11d48;
      background: #ffe4e6;
      padding: 2px 4px;
      border-radius: 3px;
      display: inline-block;
    }

    /* Fulfillment Badges: Home Delivery vs. Customer Pickup */
    .badge-fulfillment-delivery {
      background-color: #dbeafe;
      color: #1e40af;
      border: 1px solid #bfdbfe;
      font-weight: 700;
      font-size: 6.8pt;
      padding: 2px 5px;
      border-radius: 3px;
      display: inline-block;
      white-space: nowrap;
    }
    .badge-fulfillment-pickup {
      background-color: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
      font-weight: 700;
      font-size: 6.8pt;
      padding: 2px 5px;
      border-radius: 3px;
      display: inline-block;
      white-space: nowrap;
    }

    .badge-status {
      font-size: 6.5pt;
      font-weight: 700;
      padding: 2px 4px;
      border-radius: 3px;
      display: inline-block;
      text-transform: uppercase;
    }
    .status-confirmed { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-dispatched { background: #e0e7ff; color: #3730a3; }
    .status-delivered { background: #ccfbf1; color: #115e59; }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: 700; }

    .footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      text-align: center;
      font-size: 6.5pt;
      color: #94a3b8;
      border-top: 1px solid #e2e8f0;
      padding-top: 4px;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <table class="header-table">
    <tr>
      <td style="vertical-align: middle;">
        <h1 class="brand-title">KP'S KITCHEN</h1>
        <div class="brand-subtitle">Business Operations &bull; Sales &amp; Orders Log Report</div>
      </td>
      <td class="meta-box" style="vertical-align: middle;">
        <div>Filter Range: <span class="meta-highlight">{{ $filterRange }}</span></div>
        <div>Generated: <strong>{{ $generatedAt }}</strong></div>
      </td>
    </tr>
  </table>

  <!-- KPI Cards -->
  <table class="kpi-table">
    <tr>
      <td class="kpi-cell">
        <div class="kpi-label">Total Orders</div>
        <div class="kpi-value">{{ count($orders) }}</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Total Revenue</div>
        <div class="kpi-value">${{ number_format($orders->sum('amount'), 2) }}</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Fulfillment Summary</div>
        @php
          $pCount = $orders->where('order_type', 'pickup')->count();
          $dCount = count($orders) - $pCount;
        @endphp
        <div class="kpi-value" style="font-size: 9.5pt;">
          <span style="color: #1e40af;">🚚 Delivery: {{ $dCount }}</span> | 
          <span style="color: #92400e;">🛍️ Pickup: {{ $pCount }}</span>
        </div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Average Order Value</div>
        <div class="kpi-value">${{ count($orders) > 0 ? number_format($orders->sum('amount') / count($orders), 2) : '0.00' }}</div>
      </td>
    </tr>
  </table>

  <!-- Orders Table -->
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 7%;">Order #</th>
        <th style="width: 8%;">Date</th>
        <th style="width: 13%;">Customer</th>
        <!-- FULFILLMENT COLUMN (DELIVERY / PICKUP) -->
        <th style="width: 12%; text-align: center;">Fulfillment</th>
        <th style="width: 12%;">Tiffin Plan</th>
        <th style="width: 13%;">Choices</th>
        <th style="width: 9%;">Area / Postcode</th>
        <th style="width: 9%;">Driver</th>
        <th style="width: 8%; text-align: right;">Amount</th>
        <th style="width: 9%; text-align: center;">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($orders as $ord)
        @php
          $custName = is_string($ord->customer) && !empty(trim($ord->customer))
            ? trim($ord->customer)
            : (is_object($ord->customer) && isset($ord->customer->name) ? $ord->customer->name : (optional($ord->customerRelation)->name ?: 'Customer #' . $ord->customer_id));
          
          $choicesStr = (is_array($ord->selections) && !empty($ord->selections['summary']))
            ? $ord->selections['summary']
            : '-';

          $stClass = 'status-pending';
          $stLower = strtolower($ord->status ?? '');
          if (strpos($stLower, 'deliv') !== false) $stClass = 'status-delivered';
          elseif (strpos($stLower, 'dispatch') !== false || strpos($stLower, 'out') !== false) $stClass = 'status-dispatched';
          elseif (strpos($stLower, 'confirm') !== false || strpos($stLower, 'prep') !== false) $stClass = 'status-confirmed';
        @endphp
        <tr>
          <td><span class="badge-order-id">#{{ $ord->id }}</span></td>
          <td style="color: #64748b;">{{ $ord->date }}</td>
          <td class="font-bold" style="color: #0f172a;">{{ $custName }}</td>
          
          <!-- Fulfillment: Home Delivery vs. Customer Pickup -->
          <td class="text-center">
            @if($ord->order_type === 'pickup')
              <span class="badge-fulfillment-pickup">🛍️ Customer Pickup</span>
            @else
              <span class="badge-fulfillment-delivery">🚚 Home Delivery</span>
            @endif
          </td>

          <td style="font-weight: 600; color: #334155;">{{ $ord->tiffin ?: 'Standard Tiffin' }}</td>
          <td style="color: #475569; font-size: 6.8pt;">{{ $choicesStr }}</td>
          <td style="color: #64748b;">{{ $ord->area ?: '-' }}</td>
          <td style="color: #64748b;">{{ $ord->driver ?: ($ord->order_type === 'pickup' ? 'N/A (Pickup)' : 'Unassigned') }}</td>
          <td class="text-right font-bold" style="color: #0f172a;">${{ number_format($ord->amount, 2) }}</td>
          <td class="text-center"><span class="badge-status {{ $stClass }}">{{ $ord->status }}</span></td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="text-center" style="padding: 14px; color: #94a3b8;">
            No orders found matching the filter criteria.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Footer -->
  <div class="footer">
    KP's Kitchen Admin System &bull; Sales &amp; Orders Log &bull; Generated dynamically on {{ $generatedAt }}
  </div>

</body>
</html>
