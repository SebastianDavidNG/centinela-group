<?php
defined('ABSPATH') || exit;

/**
 * Log all things!
 */
class WompiPortalPagos_Logger {

	/**
	 * Vars
	 */
	public static $logger;
	const WC_LOG_FILENAME = 'wompi-portal-de-pagos';

	/**
	 * Utilize WC logger class
	 */
	public static function log($message, $single = true, $start_time = null, $end_time = null) {
		if (!class_exists('WC_Logger') ||
			( isset($_GET['page']) && isset($_GET['tab']) &&
				'wc-status' === sanitize_text_field(wp_unslash($_GET['page'])) &&
				'logs' === sanitize_text_field(wp_unslash($_GET['tab'])) )) {
			return;
		}

		if (apply_filters('wc_wompi_logging', true, $message)) {
			if (empty(self::$logger)) {
				if (version_compare(WC_VERSION, '3.0', '<')) {
					self::$logger = new WC_Logger();
				} else {
					self::$logger = wc_get_logger();
				}
			}

			$settings = Wompi_Portal_Pagos_Main::$settings;

			if (empty($settings) || ( isset($settings['logging']) && 'yes' !== $settings['logging'] )) {
				return;
			}

			if (!is_null($start_time)) {
				$formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', $start_time);
				$end_time             = is_null($end_time) ? current_time('timestamp') : $end_time;
				$formatted_end_time   = date_i18n(get_option('date_format') . ' g:ia', $end_time);
				$elapsed_time         = round(abs($end_time - $start_time) / 60, 2);

				$log_entry  = '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
				$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";
			} else {
				if ($single) {
					$log_entry = '=================================Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";
				} else {
					$log_entry = $message;
				}
			}

			if (version_compare(WC_VERSION, '3.0', '<')) {
				self::$logger->add(self::WC_LOG_FILENAME, $log_entry);
			} else {
				self::$logger->debug($log_entry, array('source' => self::WC_LOG_FILENAME));
			}
		}
	}
}
