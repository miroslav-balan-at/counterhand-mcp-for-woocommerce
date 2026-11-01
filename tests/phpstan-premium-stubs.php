<?php
// phpcs:ignoreFile -- signatures of another plugin's functions, for PHPStan only.

const ICL_TM_COMPLETE = 10;

/**
 * @param int          $string_id
 * @param string       $language
 * @param string|null  $value
 * @param int|false    $status
 * @return int|false
 */
function icl_add_string_translation( $string_id, $language, $value = null, $status = false ) {}

/** @return bool */
function wcml_is_multi_currency_on() {}

class WC_COG_Product {
	/** @return string|float */
	public static function get_cost( WC_Product $product ) {}
}
