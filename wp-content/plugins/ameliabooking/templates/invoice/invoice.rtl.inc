<?php

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

?>
<!DOCTYPE html>
<html dir="rtl" lang="<?php echo esc_attr($locale); ?>">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <?php if (!empty($googleFontCss)) : ?>
        <link rel="stylesheet" href="<?php echo esc_url($googleFontCss); ?>">
    <?php endif; ?>
  <style>
      body { margin: 0; font-family: <?php echo $fontFamily; ?>; line-height: 15px; }
      html { margin: 0; font-family: <?php echo $fontFamily; ?>; line-height: 15px;}
      @page { margin: 0; font-family: <?php echo $fontFamily; ?>; line-height: 15px;}

      .invoice {
          direction: rtl;
      }

      /* Header */
      .invoice-header {
          background-color: #F5F5F5;
          padding: 44px;
      }

      .invoice-header:after {
          content: "";
          display: table;
          clear: both;
      }

      /* Header first line */
      .invoice-header .invoice-header-first-line {
          margin-bottom: 10px;
          display: block;
      }

      .invoice-header .invoice-header-first-line:after {
          content: "";
          display: table;
          clear: both;
      }

      .invoice .invoice-header .invoice-header-first-line table {
          float: left;
      }

      .invoice-header .invoice-header-first-line table tr td:first-child {
          vertical-align: middle;
          padding-right: 16px;
      }

      .invoice-header .invoice-header-first-line img {
          width: 50px;
          height: 50px;
      }

      .invoice-header .invoice-header-first-line .invoice-header-first-line-company-name {
          vertical-align: middle;
          font-weight: 700;
          font-size: 16px;
          color: #1A2C37;
      }

      .invoice-header .invoice-header-first-line h2 {
          float: right;
          color: #0E1920;
          font-size: 22px;
      }

      /* Header second line */
      .invoice-header .invoice-header-second-line {
          padding-bottom: 8px;
      }

      .invoice-header-second-line-layout {
          width: 100%;
          border-spacing: 0;
      }

      .invoice-header-second-line-layout-left,
      .invoice-header-second-line-layout-right {
          width: 50%;
          vertical-align: top;
          padding: 0;
      }

      .invoice-header .invoice-header-second-line:after {
          content: "";
          display: table;
          clear: both;
      }

      .invoice-header .invoice-header-second-line .invoice-header-table {
          width: 100%;
          font-size: 14px;
          font-weight: 400;
          color: #1A2C37;
          border-spacing: 0 2px;
      }

      .invoice-header .invoice-header-second-line .invoice-header-table-company {
          text-align: left;
      }

      .invoice-header-table td {
          padding-bottom: 5px;
      }

      .invoice-header-table-company-name {
          font-size: 16px;
      }

      .invoice-header-table-invoice-number,
      .invoice-header-table-invoice-issued {
          text-align: right;
      }

      .invoice-header-table-invoice-issued td {
          direction: rtl;
      }

      .invoice-header-table-invoice-number-value,
      .invoice-header-table-invoice-issued-date {
          display: inline-block;
          vertical-align: middle;
          text-align: right;
          padding-left: 8px;
      }

      .invoice-header-table-invoice-issued-date {
          direction: rtl;
      }

      /* Invoice body */
      .invoice-body {
          padding: 0 44px;
          margin-top: 25px;
      }

      .invoice-body:after {
          content: "";
          display: table;
          clear: both;
      }

      .invoice-body-customer {
          width: 49%;
          color: #1A2C37;
          margin-bottom: 35px;
          float: right;
      }

      .invoice-body-customer td {
          font-size: 14px;
          font-weight: 400;
          padding-bottom: 5px;
      }

      .invoice-body-customer tr td:first-child {
          text-align: right;
      }

      .invoice-body-customer-to td {
          font-weight: bold;
          padding-bottom: 10px;
      }

      .invoice-body-cf {
          width: 49%;
          color: #1A2C37;
          margin-bottom: 35px;
          float: left;
          text-align: left;
      }

      .invoice-body-cf tr:first-child td {
          font-weight: bold;
          padding-bottom: 10px;
      }

      .invoice-body-cf td {
          font-size: 14px;
          font-weight: 400;
          padding-bottom: 5px;
      }

      .invoice-body-items {
          width: 100%;
          margin-bottom: 20px;
          clear: both;
      }

      .invoice-body-items td {
          padding: 10px;
          font-weight: 400;
          font-size: 12px;
      }

      .invoice-body-items tr td {
          text-align: left;
      }

      .invoice-body-items tr td:first-child {
          text-align: left;
      }

      .invoice-body-items tr td:last-child {
          text-align: right;
      }

      .invoice-body-items-name {
          background-color: #F5F5F5;
          color: #808A90;
      }

      .invoice-body-items-data {
          color: #1A2C37;
      }

      .invoice-body-items-data td {
          border-bottom: 1px dashed lightgray;
      }

      .invoice-body-sum {
          width: 50%;
          font-size: 13px;
          font-weight: 400;
          color: #1A2C37;
          float: left;
      }

      .invoice-body-sum-table {
          width: 100%;
          border-collapse: collapse;
          table-layout: fixed;
      }

      .invoice-body-sum-table td {
          padding: 0 0 10px 0;
          font-size: 13px;
          font-weight: 400;
      }

      .invoice-body-sum-label {
          vertical-align: top;
          text-align: right;
          word-wrap: break-word;
          overflow-wrap: break-word;
          padding-left: 10px;
      }

      .invoice-body-sum-value {
          vertical-align: bottom;
          width: 32%;
          text-align: left;
          white-space: nowrap;
      }

      .invoice-body-sum-hr-row td {
          vertical-align: middle;
          padding: 0 0 8px 0;
      }

      .invoice-body-sum-total-row td {
          font-size: 16px;
          padding-bottom: 20px;
      }

      .invoice-body-sum hr {
          color: lightgray;
          border: none;
          border-top: 1px solid lightgray;
          margin: 0;
      }

      .invoice-footer {
          width: calc(100% - 88px);
          position: absolute;
          bottom: 0;
          background-color: #F5F5F5;
          padding: 44px;
          box-sizing: border-box;
      }

      .invoice-footer-method > div:first-child {
          font-weight: bolder;
          font-size: 11px;
          color: #1A2C37;
          margin-bottom: 5px;
      }

      .invoice-footer-method > div:nth-child(2) {
          font-weight: bold;
          font-size: 12px;
          color: #808A90;
      }
  </style>
  <title><?php echo BackendStrings::get('invoice') ?></title>
</head>
<body>
  <div class="invoice">
    <div class="invoice-header">
      <div class="invoice-header-first-line">
        <table class="invoice-header-first-line-company">
          <tr>
            <?php if ($invoiceData['companyLogo'] && extension_loaded('gd')) : ?>
              <td>
                <img src='data:<?php echo esc_attr($invoiceData['companyLogo']['mime']); ?>;base64,<?php echo esc_attr($invoiceData['companyLogo']['img']); ?>' alt="logo">
              </td>
            <?php endif; ?>
            <?php if (!empty($data['company_name']) && $invoiceData['companyLogo']) : ?>
              <td class="invoice-header-first-line-company-name">
                <span><b><?php echo esc_html($data['company_name']) ?></b></span>
              </td>
            <?php endif; ?>
          </tr>
        </table>

        <h2>
          <?php echo BackendStrings::get('invoice') ?>
        </h2>
      </div>

      <br>

      <div class="invoice-header-second-line">
        <table class="invoice-header-table invoice-header-second-line-layout">
          <tr>
            <td class="invoice-header-second-line-layout-left">
              <table class="invoice-header-table invoice-header-table-company">
                <tr>
                  <td class="invoice-header-table-company invoice-header-table-company-name">
                    <span><?php echo esc_html($data['company_name']) ?></span>
                  </td>
                </tr>
                <?php if (!empty($data['company_address'])) : ?>
                  <tr>
                    <td class="invoice-header-table-company">
                      <span><?php echo esc_html($data['company_address']) ?></span>
                    </td>
                  </tr>
                <?php endif; ?>
                <?php if (!empty($data['company_email']) || !empty($data['company_phone'])) : ?>
                  <tr>
                    <td class="invoice-header-table-company">
                      <?php echo !empty($data['company_email']) ? esc_html($data['company_email']) : '' ?>
                      <?php if (!empty($data['company_email']) && !empty($data['company_phone'])) : ?>
                        |
                      <?php endif; ?>
                      <?php echo !empty($data['company_phone']) ? esc_html($data['company_phone']) : '' ?>
                    </td>
                  </tr>
                <?php endif; ?>
                <?php if (!empty($data['company_vat_number'])) : ?>
                  <tr>
                    <td class="invoice-header-table-company">
                      <?php echo esc_html($data['company_vat_number']) ?>
                    </td>
                  </tr>
                <?php endif; ?>
              </table>
            </td>
            <td class="invoice-header-second-line-layout-right">
              <table class="invoice-header-table">
                <tr>
                  <td class="invoice-header-table-invoice-number">
                    <span class="invoice-header-table-invoice-number-value">#<?php echo str_pad($data['invoice_number'], 4, '0', STR_PAD_LEFT) ?></span>
                    <span class="invoice-header-table-invoice-number-label"><?php echo BackendStrings::get('invoice') ?></span>
                  </td>
                </tr>
                <tr>
                  <td class="invoice-header-table-invoice-issued">
                    <span class="invoice-header-table-invoice-issued-date"><?php echo esc_html($data['invoice_issued']) ?></span>
                    <span class="invoice-header-table-invoice-issued-label"><?php echo BackendStrings::get('issued') ?></span>
                  </td>
                </tr>
                <?php if (!empty($data['invoice_dates'])) : ?>
                  <?php foreach ($data['invoice_dates'] as $date) : ?>
                    <tr class="invoice-header-table-invoice-issued">
                      <td>
                        <span class="invoice-header-table-invoice-issued-date"><?php echo esc_html($date) ?></span>
                        <span class="invoice-header-table-invoice-issued-label"><?php echo BackendStrings::get('booking_date') ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </table>
            </td>
          </tr>
        </table>
      </div>
    </div>

    <div class="invoice-body">
      <table class="invoice-body-customer">
        <tr class="invoice-body-customer-to">
          <td><?php echo BackendStrings::get('to_upper') ?></td>
        </tr>
        <tr>
          <td class="invoice-body-customer-name"><?php echo esc_html($data['customer_full_name']) ?></td>
        </tr>
        <tr>
          <td class="invoice-body-customer-email"><?php echo !empty($data['customer_email']) ? esc_html($data['customer_email']) : ' ' ?></td>
        </tr>
        <tr>
          <td class="invoice-body-customer-phone"><?php echo !empty($data['customer_phone']) ? esc_html($data['customer_phone']) : ' ' ?></td>
        </tr>
      </table>

      <?php if (!empty($data['customer_custom_fields'])) : ?>
        <table class="invoice-body-cf">
          <tr>
            <td><?php echo BackendStrings::get('invoice_additional_information') ?></td>
          </tr>
          <?php foreach ($data['customer_custom_fields'] as $customerCustomField) : ?>
            <tr>
              <td><?php echo esc_html($customerCustomField['value']) ?> :<?php echo esc_html($customerCustomField['label']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

      <table class="invoice-body-items">
        <thead class="invoice-body-items-name">
          <tr>
            <?php if ($invoiceData['taxEnabled']) : ?>
              <td><?php echo BackendStrings::get('invoice_tax_amount'); ?></td>
              <td><?php echo BackendStrings::get('invoice_tax_rate'); ?></td>
            <?php endif; ?>

            <td><?php echo BackendStrings::get('invoice_total_item_price') .
                ($invoiceData['includedTax'] ? BackendStrings::get('invoice_incl_tax') : ''); ?></td>
            <td><?php echo BackendStrings::get('invoice_qty'); ?></td>
            <td><?php echo BackendStrings::get('invoice_unit_price') .
                ($invoiceData['includedTax'] ? BackendStrings::get('invoice_incl_tax') : ''); ?></td>
            <td><?php echo BackendStrings::get('invoice_item'); ?></td>
          </tr>
        </thead>
        <?php foreach ($invoiceData['items'] as $appData) : ?>
          <tr class="invoice-body-items-data">
            <?php if ($invoiceData['taxEnabled']) : ?>
              <td><?php echo $helperService->getFormattedPrice(!empty($appData['invoice_tax']) ? $appData['invoice_tax'] : 0) ?></td>
              <td><?php echo !empty($appData['invoice_tax_rate']) ? esc_html($appData['invoice_tax_rate']) : 0 ?></td>
            <?php endif; ?>
            <td><?php echo $helperService->getFormattedPrice($appData['invoice_unit_price'] * $appData['invoice_qty']) ?></td>
            <td><?php echo esc_html($appData['invoice_qty']) ?></td>
            <td><?php echo $helperService->getFormattedPrice($appData['invoice_unit_price']) ?></td>
            <td><?php echo esc_html($appData['item_name']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>

      <div class="invoice-body-sum">
        <table class="invoice-body-sum-table">
          <tbody>
            <?php if ($invoiceData['subtotal'] !== $invoiceData['total']) : ?>
              <tr>
                <td class="invoice-body-sum-label"><?php echo BackendStrings::get('invoice_subtotal'); ?></td>
                <td class="invoice-body-sum-value"><?php echo $helperService->getFormattedPrice($invoiceData['subtotal']) ?></td>
              </tr>
            <?php endif; ?>
            <?php if ($invoiceData['discount'] > 0) : ?>
              <tr>
                <td class="invoice-body-sum-label"><?php echo BackendStrings::get('invoice_discount'); ?></td>
                <td class="invoice-body-sum-value"><?php echo $helperService->getFormattedPrice($invoiceData['discount']) ?></td>
              </tr>
            <?php endif; ?>
            <?php if ($invoiceData['tax'] > 0) : ?>
              <tr>
                <td class="invoice-body-sum-label"><?php echo BackendStrings::get('invoice_tax'); ?></td>
                <td class="invoice-body-sum-value"><?php echo $helperService->getFormattedPrice($invoiceData['tax']) ?></td>
              </tr>
            <?php endif; ?>
            <?php if ($invoiceData['subtotal'] !== $invoiceData['total'] || $invoiceData['tax'] > 0) : ?>
              <tr class="invoice-body-sum-hr-row">
                <td colspan="2"><hr></td>
              </tr>
            <?php endif; ?>
            <tr class="invoice-body-sum-total-row">
              <td class="invoice-body-sum-label"><?php echo BackendStrings::get('invoice_total') .
                  ($invoiceData['includedTax'] && $invoiceData['total'] > 0 ? BackendStrings::get('invoice_incl_tax') : ''); ?></td>
              <td class="invoice-body-sum-value"><?php echo $helperService->getFormattedPrice($invoiceData['total']) ?></td>
            </tr>
            <?php if ($invoiceData['paid']) : ?>
              <tr class="invoice-body-sum-hr-row">
                <td colspan="2"><hr></td>
              </tr>
              <tr>
                <td class="invoice-body-sum-label"><?php echo BackendStrings::get('invoice_paid'); ?></td>
                <td class="invoice-body-sum-value"><?php echo $helperService->getFormattedPrice($invoiceData['paid']) ?></td>
              </tr>
              <tr>
                <td class="invoice-body-sum-label"><?php echo BackendStrings::get('invoice_left_to_pay'); ?></td>
                <td class="invoice-body-sum-value"><?php echo $helperService->getFormattedPrice($invoiceData['leftToPay']) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="invoice-footer">
      <div class="invoice-footer-method">
        <div><?php echo BackendStrings::get('invoice_payment_method'); ?></div>
        <div><?php echo esc_html($data['invoice_method']) ?></div>
      </div>
    </div>
  </div>

</body>
</html>

