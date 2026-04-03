<?php
/**
 * Abandoned cart recovery email template.
 *
 * Variables (set by MEYVC_Abandoned_Cart_Reminder::send_email):
 *
 * @var string $body_content    Main email body HTML (already escaped/sanitized).
 * @var string $store_name      Store display name.
 * @var string $store_url       Store home URL.
 * @var string $unsubscribe_url Unsubscribe URL.
 * @var string $footer_text     "You're receiving this because..." sentence.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$brand_color = apply_filters(
	'meyvc_email_brand_color',
	function_exists( 'meyvc_settings' ) ? meyvc_settings()->get( 'abandoned_cart', 'email_brand_color', '#2563eb' ) : '#2563eb'
);
$brand_color = is_string( $brand_color ) && preg_match( '/^#[a-f0-9]{6}$/i', $brand_color ) ? $brand_color : '#2563eb';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( $store_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f5;">
	<tr>
		<td align="center" style="padding:32px 16px;">
			<table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">

				<!-- Header -->
				<tr>
					<td align="center" style="padding:24px 32px;background-color:<?php echo esc_attr( $brand_color ); ?>;">
						<a href="<?php echo esc_url( $store_url ); ?>" style="text-decoration:none;">
							<span style="color:#ffffff;font-size:20px;font-weight:700;"><?php echo esc_html( $store_name ); ?></span>
						</a>
					</td>
				</tr>

				<!-- Body -->
				<tr>
					<td style="padding:32px;color:#374151;font-size:15px;line-height:1.6;">
						<?php echo wp_kses_post( $body_content ); ?>
					</td>
				</tr>

				<!-- Footer -->
				<tr>
					<td style="padding:24px 32px;border-top:1px solid #e5e7eb;background-color:#f9fafb;">
						<p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;line-height:1.5;">
							<?php echo esc_html( $footer_text ); ?>
							<br>
							<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color:#9ca3af;text-decoration:underline;">
								<?php esc_html_e( 'Unsubscribe from cart recovery emails', 'meyvora-convert' ); ?>
							</a>
						</p>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>
</body>
</html>
