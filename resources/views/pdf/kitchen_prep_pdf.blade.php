<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Kitchen Preparation &amp; Food Item Estimates</title>
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

    /* Section Titles */
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

    /* Main Tables */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
      font-size: 7.5pt;
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

    /* Badges & Pills */
    .badge-category {
      font-size: 6.5pt;
      font-weight: 700;
      padding: 2px 5px;
      border-radius: 3px;
      background: #f1f5f9;
      color: #334155;
      display: inline-block;
      border: 1px solid #cbd5e1;
    }
    .badge-qty {
      font-weight: 800;
      font-size: 8.5pt;
      color: #0f172a;
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

    /* Fulfillment Badge: Key User Requirement */
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
    .text-right {
      text-align: right;
    }
    .text-center {
      text-align: center;
    }
    .font-bold {
      font-weight: 700;
    }

    .page-break {
      page-break-after: always;
    }

    /* Footer */
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
    @media print {
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body>

  @if(!empty($isPrintableView))
    <div class="no-print" style="position: sticky; top: 0; background: #0f172a; color: #ffffff; padding: 10px 16px; margin: -10px -8px 16px -8px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 14px rgba(0,0,0,0.2); z-index: 99999; border-bottom: 2px solid #e11d48; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 13px; font-weight: 700; color: #fff;">📄 KP's Kitchen Report Preview</span>
        <span style="font-size: 11px; color: #94a3b8;">(Select <strong>Destination: Save as PDF</strong> in print dialog to save)</span>
      </div>
      <div style="display: flex; gap: 8px;">
        <button type="button" onclick="window.print()" style="background: linear-gradient(135deg, #e11d48, #be123c); color: #fff; border: none; padding: 7px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
          🖨️ Save as PDF / Print
        </button>
        <button type="button" onclick="window.close()" style="background: #334155; color: #e2e8f0; border: none; padding: 7px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px;">
          Close
        </button>
      </div>
    </div>
    @if(!empty($autoPrint))
      <script>
        window.addEventListener('load', function() {
          setTimeout(function() { window.print(); }, 350);
        });
      </script>
    @endif
  @endif

  <!-- Header -->
  <table class="header-table">
    <tr>
      <td style="vertical-align: middle;">
        <h1 class="brand-title">KP'S KITCHEN</h1>
        <div class="brand-subtitle">Kitchen Preparation &amp; Food Item Estimates Sheet</div>
      </td>
      <td class="meta-box" style="vertical-align: middle;">
        <div>Filter Period: <span class="meta-highlight">{{ $kitchenPrep['filters']['date_range_label'] }}</span></div>
        <div>Generated: <strong>{{ $generatedAt }}</strong></div>
        <div>Category Filter: <strong>{{ ucfirst($kitchenPrep['filters']['category'] ?? 'All') }}</strong></div>
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
        <div class="kpi-label">Unique Food Items</div>
        <div class="kpi-value">{{ count($kitchenPrep['items'] ?? []) }}</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Fulfillment Summary</div>
        @php
          $pickupCount = 0;
          $deliveryCount = 0;
          foreach(($kitchenPrep['orders_list'] ?? []) as $ord) {
            if (($ord['order_type'] ?? '') === 'pickup') {
              $pickupCount++;
            } else {
              $deliveryCount++;
            }
          }
        @endphp
        <div class="kpi-value" style="font-size: 9.5pt;">
          <span style="color: #1e40af;"> Delivery: {{ $deliveryCount }}</span> |
          <span style="color: #92400e;"> Pickup: {{ $pickupCount }}</span>
        </div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Total Estimated Revenue</div>
        @php
          $totalRev = array_sum(array_column($kitchenPrep['orders_list'] ?? [], 'amount'));
        @endphp
        <div class="kpi-value">${{ number_format($totalRev, 2) }}</div>
      </td>
    </tr>
  </table>

  <!-- Section 1: Food Items Preparation Estimates -->
  <div class="section-title">1. Food Item Preparation Quantities</div>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 28%;">Food Item</th>
        <th style="width: 16%;">Category</th>
        <th style="width: 12%; text-align: right;">Total To Prepare</th>
        <th style="width: 10%;">Unit</th>
        <th style="width: 11%; text-align: right;">From Tiffins</th>
        <th style="width: 11%; text-align: right;">From Add-ons</th>
        <th style="width: 12%; text-align: center;">Orders Count</th>
      </tr>
    </thead>
    <tbody>
      @forelse(($kitchenPrep['items'] ?? []) as $item)
        <tr>
          <td class="font-bold" style="color: #0f172a;">{{ $item['name'] }}</td>
          <td>
            <span class="badge-category">{{ $item['category'] }}</span>
          </td>
          <td class="text-right">
            <span class="badge-qty">{{ $item['total_qty'] }}</span>
          </td>
          <td style="color: #64748b;">{{ $item['unit'] }}</td>
          <td class="text-right" style="color: #334155;">{{ $item['tiffin_qty'] }}</td>
          <td class="text-right" style="color: #10b981; font-weight: 600;">{{ $item['addon_qty'] }}</td>
          <td class="text-center" style="font-weight: 600; color: #64748b;">{{ $item['orders_count'] }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center" style="padding: 12px; color: #94a3b8;">
            No items required for the selected date filter range.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Page Break for the Detailed Orders table so it doesn't get awkwardly sliced -->
  <div class="page-break"></div>

  <!-- Section 2: Detailed Orders Breakdown with Delivery vs. Pickup -->
  <table class="header-table" style="margin-top: 5px;">
    <tr>
      <td style="vertical-align: middle;">
        <h2 style="font-size: 12pt; font-weight: 800; color: #0f172a; margin: 0;">
          KP'S KITCHEN - DETAILED ORDERS BREAKDOWN
        </h2>
        <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">
          Filtered Period: <strong>{{ $kitchenPrep['filters']['date_range_label'] }}</strong> &bull; Total Orders: <strong>{{ count($kitchenPrep['orders_list'] ?? []) }}</strong>
        </div>
      </td>
      <td class="meta-box" style="vertical-align: middle;">
        <div>Generated: {{ $generatedAt }}</div>
      </td>
    </tr>
  </table>

  <div class="section-title">2. Order-by-Order Prep &amp; Fulfillment Manifest</div>
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
              <span class="badge-fulfillment-pickup"> Customer Pickup</span>
            @else
              <span class="badge-fulfillment-delivery"> Home Delivery</span>
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
            No orders found for the selected time filter range.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Footer -->
  <div class="footer">
    KP's Kitchen Admin System &bull; Preparation Sheet &bull; Generated dynamically on {{ $generatedAt }}
  </div>

</body>
</html>
