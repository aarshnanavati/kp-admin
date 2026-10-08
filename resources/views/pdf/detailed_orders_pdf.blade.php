<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Detailed Kitchen Orders Breakdown</title>
  <style>
    @page {
      margin: 10mm 8mm;
      size: A4 landscape;
    }
    body {
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      font-size: 8pt;
      color: #1e293b;
      line-height: 1.35;
      margin: 0;
      padding: 0;
      background: #ffffff;
    }
    
    /* Header Bar */
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

    /* Summary KPI Bar */
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

    /* Section Title */
    .section-title {
      font-size: 10pt;
      font-weight: 700;
      color: #0f172a;
      margin: 10px 0 6px 0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-left: 3px solid #e11d48;
      padding-left: 6px;
    }

    /* Orders Table */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
      font-size: 7.3pt;
    }
    .data-table th {
      background-color: #0f172a;
      color: #ffffff;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 6.8pt;
      letter-spacing: 0.3px;
      padding: 6px 6px;
      border: 1px solid #0f172a;
      text-align: left;
    }
    .data-table td {
      padding: 5px 6px;
      border: 1px solid #e2e8f0;
      vertical-align: middle;
    }
    .data-table tr:nth-child(even) td {
      background-color: #f8fafc;
    }

    /* Badges */
    .badge-order-id {
      font-family: monospace;
      font-weight: 700;
      color: #e11d48;
      background: #ffe4e6;
      padding: 2px 4px;
      border-radius: 3px;
      display: inline-block;
    }
    
    /* FULFILLMENT BADGE: Key Requirement */
    .badge-fulfillment-delivery {
      background-color: #dbeafe;
      color: #1e40af;
      border: 1px solid #bfdbfe;
      font-weight: 700;
      font-size: 7pt;
      padding: 2px 6px;
      border-radius: 3px;
      display: inline-block;
      white-space: nowrap;
    }
    .badge-fulfillment-pickup {
      background-color: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
      font-weight: 700;
      font-size: 7pt;
      padding: 2px 6px;
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

    .note-box {
      font-size: 6.8pt;
      color: #b45309;
      background: #fffbeb;
      border: 1px dashed #fde68a;
      padding: 2px 4px;
      border-radius: 3px;
      display: block;
      margin-top: 2px;
    }
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
        <div class="brand-subtitle">Detailed Kitchen Orders Breakdown &amp; Fulfillment Manifest</div>
      </td>
      <td class="meta-box" style="vertical-align: middle;">
        <div>Filter Range: <span class="meta-highlight">{{ $kitchenPrep['filters']['date_range_label'] }}</span></div>
        <div>Generated: <strong>{{ $generatedAt }}</strong></div>
        <div>Total Orders: <strong>{{ count($kitchenPrep['orders_list'] ?? []) }}</strong></div>
      </td>
    </tr>
  </table>

  <!-- KPI Cards -->
  <table class="kpi-table">
    <tr>
      <td class="kpi-cell">
        <div class="kpi-label">Total Active Orders</div>
        <div class="kpi-value">{{ count($kitchenPrep['orders_list'] ?? []) }}</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Home Delivery Orders</div>
        @php
          $deliveryCount = 0;
          $pickupCount = 0;
          foreach(($kitchenPrep['orders_list'] ?? []) as $ord) {
            if (($ord['order_type'] ?? '') === 'pickup') {
              $pickupCount++;
            } else {
              $deliveryCount++;
            }
          }
        @endphp
        <div class="kpi-value" style="color: #1e40af;">🚚 {{ $deliveryCount }} Orders</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Store Pickup Orders</div>
        <div class="kpi-value" style="color: #92400e;">🛍️ {{ $pickupCount }} Orders</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Total Order Value</div>
        @php
          $totalRev = array_sum(array_column($kitchenPrep['orders_list'] ?? [], 'amount'));
        @endphp
        <div class="kpi-value">${{ number_format($totalRev, 2) }}</div>
      </td>
    </tr>
  </table>

  <!-- Section Header -->
  <div class="section-title">Order-by-Order Kitchen Preparation &amp; Fulfillment List</div>

  <!-- Detailed Orders Table -->
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 7%;">Order #</th>
        <th style="width: 14%;">Customer Name</th>
        <th style="width: 13%;">Tiffin Plan</th>
        <!-- FULFILLMENT COLUMN (DELIVERY / PICKUP) -->
        <th style="width: 12%; text-align: center;">Fulfillment Type</th>
        <th style="width: 14%;">Optional Choices</th>
        <th style="width: 12%;">Add-ons</th>
        <th style="width: 14%;">Delivery Address / Notes</th>
        <th style="width: 7%; text-align: right;">Amount</th>
        <th style="width: 7%; text-align: center;">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse(($kitchenPrep['orders_list'] ?? []) as $ord)
        <tr>
          <!-- Order ID -->
          <td>
            <span class="badge-order-id">#{{ $ord['id'] }}</span>
          </td>

          <!-- Customer Name -->
          <td class="font-bold" style="color: #0f172a;">
            {{ $ord['name'] }}
          </td>

          <!-- Tiffin Plan -->
          <td style="font-weight: 600; color: #334155;">
            {{ $ord['tiffin_plan'] }}
          </td>

          <!-- Fulfillment: Home Delivery vs. Customer Pickup -->
          <td class="text-center">
            @if(($ord['order_type'] ?? '') === 'pickup')
              <span class="badge-fulfillment-pickup">🛍️ Customer Pickup</span>
            @else
              <span class="badge-fulfillment-delivery">🚚 Home Delivery</span>
            @endif
          </td>

          <!-- Optional Item Chosen -->
          <td style="color: #475569;">
            {{ $ord['optional_item_choosen'] }}
          </td>

          <!-- Add-ons -->
          <td style="color: #059669; font-weight: 600;">
            {{ $ord['add_ons'] }}
          </td>

          <!-- Address & Notes -->
          <td style="font-size: 7pt; color: #334155;">
            @if(($ord['order_type'] ?? '') === 'pickup')
              <div style="font-weight: 600; color: #92400e;">[Pickup at Kitchen Counter]</div>
            @else
              <div>{{ $ord['address'] }}</div>
            @endif

            @if(!empty($ord['note']) && $ord['note'] !== '-')
              <span class="note-box">📝 {{ $ord['note'] }}</span>
            @endif
          </td>

          <!-- Amount -->
          <td class="text-right font-bold" style="color: #0f172a;">
            {{ $ord['amount_formatted'] }}
          </td>

          <!-- Status -->
          <td class="text-center">
            @php
              $stClass = 'status-pending';
              $stLower = strtolower($ord['status'] ?? '');
              if (strpos($stLower, 'deliv') !== false) $stClass = 'status-delivered';
              elseif (strpos($stLower, 'dispatch') !== false || strpos($stLower, 'out') !== false) $stClass = 'status-dispatched';
              elseif (strpos($stLower, 'confirm') !== false || strpos($stLower, 'prep') !== false) $stClass = 'status-confirmed';
            @endphp
            <span class="badge-status {{ $stClass }}">{{ $ord['status'] ?? 'Active' }}</span>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="text-center" style="padding: 14px; color: #94a3b8;">
            No orders found matching the filter criteria.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Footer -->
  <div class="footer">
    KP's Kitchen Admin System &bull; Detailed Orders Breakdown &bull; Generated dynamically on {{ $generatedAt }}
  </div>

</body>
</html>
