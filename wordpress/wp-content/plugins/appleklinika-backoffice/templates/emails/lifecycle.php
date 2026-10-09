<?php
/** @var array $view @var string $heading */
if (! defined('ABSPATH')) { exit; }
?>
<!doctype html>
<html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><title><?php echo esc_html($heading); ?></title>
<style>@media only screen and (max-width:600px){.ak-email-pad{padding:24px 18px!important}.ak-email-title{font-size:28px!important;line-height:1.2!important}.ak-email-outer{padding:12px 0!important}.ak-email-price{width:100px!important}}</style>
</head><body style="margin:0;padding:0;background-color:#f3f4f6;color:#202124;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;"><?php echo esc_html($view['preheader']); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#f3f4f6;"><tr><td align="center" class="ak-email-outer" style="padding:32px 12px;">
<!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-top:4px solid #bf1630;">
<tr><td class="ak-email-pad" style="padding:32px 36px 28px;">
<img src="<?php echo esc_url($view['logo_url']); ?>" width="164" height="84" alt="Apple Klinika" border="0" style="display:block;width:164px;height:auto;max-width:100%;border:0;margin:0 0 28px;">
<p style="margin:0 0 12px;font-size:12px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase;color:#737780;">Rendelés #<?php echo esc_html($view['number']); ?></p>
<h1 class="ak-email-title" style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:34px;line-height:1.16;font-weight:bold;letter-spacing:-0.7px;text-align:left;color:#202124;"><?php echo esc_html($heading); ?></h1>
<p style="margin:0 0 12px;font-size:16px;line-height:1.6;"><?php echo esc_html($view['greeting']); ?></p>
<p style="margin:0 0 22px;font-size:16px;line-height:1.6;"><?php echo esc_html($view['intro']); ?></p>
<?php if ($view['accepted'] || $view['received']): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#f7f8fa;"><tr><td style="padding:16px 18px;font-size:14px;line-height:1.7;">
<strong style="color:#202124;"><?php echo $view['paid'] ? 'Fizetve' : ($view['cash'] ? 'Fizetés készpénzben, átvételkor' : 'Választott fizetési mód'); ?></strong><?php if ($view['payment'] !== ''): ?> · <?php echo esc_html($view['payment']); ?><?php endif; ?><br>
<?php if ($view['date'] !== ''): ?>Rendelés dátuma: <?php echo esc_html($view['date']); ?><?php endif; ?>
</td></tr></table>
<?php if ($view['expected_fulfilment'] !== ''): ?><p style="margin:16px 0;font-size:14px;line-height:1.7;"><?php echo esc_html($view['expected_fulfilment']); ?></p><?php endif; ?>
<?php if ($view['payment_instructions'] !== ''): ?><div style="margin:16px 0;font-size:14px;line-height:1.7;"><?php echo wp_kses_post($view['payment_instructions']); ?></div><?php endif; ?>
<h2 style="margin:28px 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:19px;line-height:1.4;color:#202124;">A rendelésed</h2>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;table-layout:fixed;border-collapse:collapse;font-size:14px;line-height:1.6;">
<thead><tr><th scope="col" align="left" style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#737780;font-weight:normal;">Termék / mennyiség</th><th scope="col" align="right" class="ak-email-price" style="width:128px;padding:10px 0;border-bottom:1px solid #e5e7eb;color:#737780;font-weight:normal;">Összeg</th></tr></thead>
<tbody><?php foreach ($view['items'] as $item): ?><tr><td valign="top" style="padding:16px 12px 16px 0;border-bottom:1px solid #e5e7eb;word-wrap:break-word;overflow-wrap:anywhere;">
<strong><?php echo esc_html($item['name']); ?></strong><br><span style="color:#626770;"><?php echo esc_html((string) $item['quantity']); ?> db</span>
<?php foreach ($item['details'] as $detail): ?><div style="font-size:12px;line-height:1.6;color:#626770;margin-top:4px;"><strong><?php echo esc_html($detail['label']); ?>:</strong> <?php echo esc_html($detail['value']); ?></div><?php endforeach; ?>
</td><td align="right" valign="top" style="padding:16px 0;border-bottom:1px solid #e5e7eb;"><?php echo wp_kses_post($item['total']); ?></td></tr><?php endforeach; ?></tbody></table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;line-height:1.6;margin-top:12px;">
<?php foreach ($view['totals'] as $key => $total): if ($key === 'payment_method') { continue; } ?><tr>
<td valign="top" style="padding:6px 12px 6px 0;<?php echo $key === 'order_total' ? 'font-weight:bold;font-size:18px;border-top:1px solid #e5e7eb;' : ''; ?>"><?php echo esc_html(wp_strip_all_tags($total['label'])); ?></td>
<td align="right" valign="top" style="padding:6px 0;<?php echo $key === 'order_total' ? 'font-weight:bold;font-size:18px;border-top:1px solid #e5e7eb;' : ''; ?>"><?php echo wp_kses_post($total['value']); ?></td></tr><?php endforeach; ?>
</table>
<?php else: ?>
<h2 style="margin:26px 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:19px;line-height:1.4;color:#202124;">Csomagkövetés</h2>
<?php foreach ($view['tracking'] as $index => $link): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:12px;background:#f7f8fa;"><tr><td style="padding:18px;">
<p style="margin:0 0 12px;font-size:14px;line-height:1.6;word-break:break-all;">GLS csomagszám: <strong><?php echo esc_html($link['code']); ?></strong></p>
<table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#bf1630" style="background:#bf1630;border-radius:4px;mso-padding-alt:13px 18px;">
<a href="<?php echo esc_url($link['url']); ?>" style="display:inline-block;padding:13px 18px;font-size:15px;line-height:20px;font-weight:bold;text-decoration:none;color:#ffffff;">Csomag követése<?php echo count($view['tracking']) > 1 ? ' · ' . esc_html((string) ($index + 1)) : ''; ?></a>
</td></tr></table></td></tr></table>
<?php endforeach; ?>
<?php endif; ?>
<h2 style="margin:28px 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:19px;line-height:1.4;color:#202124;"><?php echo esc_html($view['delivery_title']); ?></h2>
<?php if ($view['shipping'] !== ''): ?><p style="margin:0 0 8px;font-size:14px;line-height:1.7;font-weight:bold;"><?php echo esc_html($view['shipping']); ?></p><?php endif; ?>
<?php if ($view['delivery_address'] !== ''): ?><p style="margin:0 0 10px;font-size:14px;line-height:1.7;"><?php echo wp_kses_post($view['delivery_address']); ?></p><?php endif; ?>
<p style="margin:0 0 22px;font-size:14px;line-height:1.7;color:#626770;"><?php echo esc_html($view['delivery_note']); ?></p>
<?php if ($view['paid']): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#fff6f7;border-left:3px solid #bf1630;"><tr><td style="padding:18px;font-size:14px;line-height:1.7;">
<strong>Számlád a mellékletben</strong><br>A rendelés számláját PDF-ként csatoltuk ehhez a levélhez.
<?php if ($view['invoice_number'] !== ''): ?><br>Számlaszám: <?php echo esc_html($view['invoice_number']); ?><?php endif; ?>
</td></tr></table>
<?php if ($view['billing_address'] !== ''): ?><h2 style="margin:24px 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:16px;color:#202124;">Számlázási adatok</h2><p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#626770;"><?php echo wp_kses_post($view['billing_address']); ?></p><?php endif; ?>
<?php endif; ?>
<?php if ($view['account_url'] !== ''): ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px;"><tr><td bgcolor="<?php echo $view['paid'] ? '#bf1630' : '#202124'; ?>" style="border-radius:4px;mso-padding-alt:14px 20px;">
<a href="<?php echo esc_url($view['account_url']); ?>" style="display:inline-block;padding:14px 20px;font-size:15px;line-height:20px;font-weight:bold;text-decoration:none;color:#ffffff;">Rendelés megtekintése</a>
</td></tr></table>
<p style="margin:12px 0 0;font-size:12px;line-height:1.6;color:#737780;">A részleteket és az aktuális állapotot a fiókodban találod.</p>
<?php endif; ?>
</td></tr><tr><td class="ak-email-pad" style="padding:22px 36px;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.7;color:#737780;">
<strong style="color:#202124;">Apple Klinika</strong><br>Ez a levél a rendelésedhez kapcsolódó értesítés.
</td></tr></table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table></body></html>
