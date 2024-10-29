<?php
/**
 * Class-airpaychecksum
 * Class-airpaychecksum.php for checksum calculation.
 *
 * @file
 * @package checksum
 * Class-airpaychecksum.php for checksum calculation.
 */

/**
 * Class-airpaychecksum.php for checksum calculation.
 */
class AirpayChecksum {

	/**
	 * Calculate checksum
	 *
	 * @param data       $data checksum data.
	 * @param secret_key $secret_key secret key for checksum calculation.
	 */
	public static function calculate_checksum( $data, $secret_key ) {
		$checksum = md5( $data . $secret_key );
		return $checksum;
	}
	/**
	 * Calculate checksum
	 *
	 * @param data $data checksum data.
	 * @param salt $salt data for checksum calculation.
	 */
	public static function encrypt( $data, $salt ) {
		// Build a 256-bit $key which is a SHA256 hash of $salt and $password.
		$key = hash( 'SHA256', $salt . '@' . $data );
		return $key;
	}
	/**
	 * Calculate checksum
	 *
	 * @param data $data checksum data.
	 */
	public static function encrypt_sha_256( $data ) {
		$key = hash( 'SHA256', $data );
		return $key;
	}
	/**
	 * Calculate checksum
	 *
	 * @param data $data checksum data.
	 * @param salt $salt data for checksum calculation.
	 */
	public static function calculate_checksum_sha_256( $data, $salt ) {

		$checksum = hash( 'SHA256', $salt . '@' . $data );
		return $checksum;
	}
	/**
	 * To get all params.
	 */
	public static function get_all_params() {
		$all = '';
		check_admin_referer( 'post', '_POST' );
		foreach ( $_POST as $key => $value ) {
			if ( 'checksum' !== $key ) {
				$all .= "'";
				if ( 'returnUrl' === $key ) {
					// pretty url check.//.
					$a = strstr( $value, '?' );
					if ( $a ) {
						$value .= '&wc-api=WC_Airpay';
					} else {
						$value .= '?wc-api=WC_Airpay';
					}

					$all .= self::sanitized_url( $value );
				} else {
					$all .= self::sanitized_param( $value );
				}
				$all .= "'";
			}
		}
		return $all;
	}

	/**
	 * Calculate checksum
	 *
	 * @param checksum $checksum checksum value.
	 * @param all      $all data.
	 * @param secret   $secret secret key.
	 */
	public static function verify_checksum( $checksum, $all, $secret ) {
		$cal_checksum = self::calculate_checksum( $secret, $all );
		$bool         = 0;
		if ( $checksum === $cal_checksum ) {
			$bool = 1;
		}

		return $bool;
	}

	/**
	 * Calculate checksum
	 *
	 * @param param $param value for sanitization.
	 */
	public static function sanitized_param( $param ) {

		$pattern[0]      = '%,%';
		$pattern[1]      = '%#%';
		$pattern[2]      = '%\(%';
		$pattern[3]      = '%\)%';
		$pattern[4]      = '%\{%';
		$pattern[5]      = '%\}%';
		$pattern[6]      = '%<%';
		$pattern[7]      = '%>%';
		$pattern[8]      = '%`%';
		$pattern[9]      = '%!%';
		$pattern[10]     = '%\$%';
		$pattern[11]     = '%\%%';
		$pattern[12]     = '%\^%';
		$pattern[13]     = '%=%';
		$pattern[14]     = '%\+%';
		$pattern[15]     = '%\|%';
		$pattern[16]     = '%\\\%';
		$pattern[17]     = '%:%';
		$pattern[18]     = "%'%";
		$pattern[19]     = '%"%';
		$pattern[20]     = '%;%';
		$pattern[21]     = '%~%';
		$pattern[22]     = '%\[%';
		$pattern[23]     = '%\]%';
		$pattern[24]     = '%\*%';
		$pattern[25]     = '%&%';
		$sanitized_param = preg_replace( $pattern, '', $param );
		return $sanitized_param;
	}

	/**
	 * Calculate checksum
	 *
	 * @param param $param value for sanitization.
	 */
	public static function sanitized_url( $param ) {
		$pattern[0]      = '%,%';
		$pattern[1]      = '%\(%';
		$pattern[2]      = '%\)%';
		$pattern[3]      = '%\{%';
		$pattern[4]      = '%\}%';
		$pattern[5]      = '%<%';
		$pattern[6]      = '%>%';
		$pattern[7]      = '%`%';
		$pattern[8]      = '%!%';
		$pattern[9]      = '%\$%';
		$pattern[10]     = '%\%%';
		$pattern[11]     = '%\^%';
		$pattern[12]     = '%\+%';
		$pattern[13]     = '%\|%';
		$pattern[14]     = '%\\\%';
		$pattern[15]     = "%'%";
		$pattern[16]     = '%"%';
		$pattern[17]     = '%;%';
		$pattern[18]     = '%~%';
		$pattern[19]     = '%\[%';
		$pattern[20]     = '%\]%';
		$pattern[21]     = '%\*%';
		$sanitized_param = preg_replace( $pattern, '', $param );
		return $sanitized_param;
	}
}
