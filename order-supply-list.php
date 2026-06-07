<?php
/**
 * Plugin Name:       Order Supply List
 * Plugin Name (FA):  لیست تأمین سفارشات
 * Description:       Displays required production quantities grouped by parent category for active WooCommerce orders.
 * Description (FA):  نمایش تعداد تولید مورد نیاز به تفکیک دسته‌بندی مادر برای سفارشات فعال ووکامرس.
 * Version:           1.6.8
 * Author:            Arya Byte
 * Author URI:        https://aryabyte.ir
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       order-supply-list
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OSL_VERSION', '1.6.8' );
define( 'OSL_URL',     plugin_dir_url( __FILE__ ) );
define( 'OSL_DIR',     plugin_dir_path( __FILE__ ) );

/* ── Load translations ── */
add_action( 'init', function () {
    load_plugin_textdomain( 'order-supply-list', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/* ── Admin menu ── */
add_action( 'admin_menu', function () {
    add_menu_page(
        __( 'Order Supply List', 'order-supply-list' ),
        __( 'Order Supply', 'order-supply-list' ),
        'manage_woocommerce',
        'order-supply-list',
        'osl_render_page',
        'dashicons-clipboard',
        56
    );
} );

/* ── Enqueue assets ── */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( strpos( $hook, 'order-supply-list' ) === false ) return;

    if ( osl_use_jalali_calendar() ) {
        wp_enqueue_style(
            'osl-jalali-datepicker',
            OSL_URL . 'assets/jalalidatepicker.min.css',
            [],
            OSL_VERSION
        );
        wp_enqueue_script(
            'osl-jalali-datepicker',
            OSL_URL . 'assets/jalalidatepicker.min.js',
            [],
            OSL_VERSION,
            true
        );
    }

    wp_enqueue_style(  'osl-style',  OSL_URL . 'assets/style.css', [], OSL_VERSION );
    wp_enqueue_script(
        'osl-script',
        OSL_URL . 'assets/script.js',
        osl_use_jalali_calendar() ? [ 'jquery', 'osl-jalali-datepicker' ] : [ 'jquery' ],
        OSL_VERSION,
        true
    );
} );

/* ══════════════════════════════════════════
   Jalali ↔ Gregorian conversion
   Algorithm: jdf.scr.ir v2.70
══════════════════════════════════════════ */
function osl_gregorian_to_jalali( $gy, $gm, $gd ) {
    $g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
    if ( $gy > 1600 ) { $jy = 979;  $gy -= 1600; }
    else              { $jy = 0;    $gy -= 621; }
    $gy2  = ( $gm > 2 ) ? $gy + 1 : $gy;
    $days = ( 365 * $gy )
          + (int) ( ( $gy2 + 3 ) / 4 )
          - (int) ( ( $gy2 + 99 ) / 100 )
          + (int) ( ( $gy2 + 399 ) / 400 )
          - 80 + $gd + $g_d_m[ $gm - 1 ];
    $jy  += 33 * (int) ( $days / 12053 );
    $days %= 12053;
    $jy  += 4 * (int) ( $days / 1461 );
    $days %= 1461;
    $jy  += (int) ( ( $days - 1 ) / 365 );
    if ( $days > 365 ) $days = ( $days - 1 ) % 365;
    if ( $days < 186 ) {
        $jm = 1 + (int) ( $days / 31 );
        $jd = 1 + $days % 31;
    } else {
        $jm = 7 + (int) ( ( $days - 186 ) / 30 );
        $jd = 1 + ( $days - 186 ) % 30;
    }
    return [ $jy, $jm, $jd ];
}

function osl_jalali_to_gregorian( $jy, $jm, $jd ) {
    if ( $jy > 979 ) { $gy = 1600; $jy -= 979; }
    else             { $gy = 621; }
    $days = ( 365 * $jy )
          + ( (int) ( $jy / 33 ) * 8 )
          + (int) ( ( $jy % 33 + 3 ) / 4 )
          + 78 + $jd
          + ( $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
    $gy  += 400 * (int) ( $days / 146097 );
    $days %= 146097;
    if ( $days > 36524 ) {
        $gy  += 100 * (int) ( --$days / 36524 );
        $days %= 36524;
        if ( $days >= 365 ) $days++;
    }
    $gy  += 4 * (int) ( $days / 1461 );
    $days %= 1461;
    $gy  += (int) ( ( $days - 1 ) / 365 );
    if ( $days > 365 ) $days = ( $days - 1 ) % 365;
    $gd    = $days + 1;
    $sal_a = [ 0, 31,
        ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || $gy % 400 === 0 ) ? 29 : 28,
        31, 30, 31, 30, 31, 31, 30, 31, 30, 31,
    ];
    for ( $gm = 0; $gm <= 11; $gm++ ) {
        if ( $gd <= $sal_a[ $gm ] ) break;
        $gd -= $sal_a[ $gm ];
    }
    return [ $gy, $gm, $gd ];
}

function osl_to_jalali_str( $datetime_str ) {
    $ts = strtotime( $datetime_str );
    if ( ! $ts ) return $datetime_str;
    list( $jy, $jm, $jd ) = osl_gregorian_to_jalali(
        (int) gmdate( 'Y', $ts ),
        (int) gmdate( 'n', $ts ),
        (int) gmdate( 'j', $ts )
    );
    return $jy . '/' . str_pad( $jm, 2, '0', STR_PAD_LEFT ) . '/' . str_pad( $jd, 2, '0', STR_PAD_LEFT );
}

/**
 * Returns true if the given Jalali year is a leap year (has 30 days in Esfand).
 * Relies on the existing osl_jalali_to_gregorian() conversion: if the year spans
 * 366 Gregorian days it is a leap year.
 */
function osl_jalali_is_leap( $jy ) {
    // Convert 1 Farvardin of this year and next year to Gregorian Julian Day count
    list( $gy1, $gm1, $gd1 ) = osl_jalali_to_gregorian( $jy,     1, 1 );
    list( $gy2, $gm2, $gd2 ) = osl_jalali_to_gregorian( $jy + 1, 1, 1 );
    $days1 = (int) date( 'z', mktime( 0, 0, 0, $gm1, $gd1, $gy1 ) ) + ( $gy1 * 365 ) + (int) ( $gy1 / 4 ) - (int) ( $gy1 / 100 ) + (int) ( $gy1 / 400 );
    $days2 = (int) date( 'z', mktime( 0, 0, 0, $gm2, $gd2, $gy2 ) ) + ( $gy2 * 365 ) + (int) ( $gy2 / 4 ) - (int) ( $gy2 / 100 ) + (int) ( $gy2 / 400 );
    return ( $days2 - $days1 ) === 366;
}

/**
 * Validate and convert a Jalali date string (YYYY/MM/DD) to Gregorian (YYYY-MM-DD).
 * Returns empty string if the input is invalid.
 */
function osl_jalali_str_to_gregorian_str( $str ) {
    $str = trim( $str );

    // Must match YYYY/MM/DD with valid Jalali ranges
    if ( ! preg_match( '/^(1[34]\d{2})\/(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])$/', $str, $m ) ) {
        return '';
    }

    $jy = (int) $m[1];
    $jm = (int) $m[2];
    $jd = (int) $m[3];

    // Day-in-month validation including leap year for Esfand (month 12)
    if ( $jm <= 6 ) {
        $max_day = 31;
    } elseif ( $jm <= 11 ) {
        $max_day = 30;
    } else {
        $max_day = osl_jalali_is_leap( $jy ) ? 30 : 29;
    }

    if ( $jd > $max_day ) {
        return '';
    }

    list( $gy, $gm, $gd ) = osl_jalali_to_gregorian( $jy, $jm, $jd );
    return $gy . '-' . str_pad( $gm, 2, '0', STR_PAD_LEFT ) . '-' . str_pad( $gd, 2, '0', STR_PAD_LEFT );
}


/**
 * Determine which calendar should be shown in the admin UI.
 * Internally, WooCommerce queries always use Gregorian dates.
 */
function osl_use_jalali_calendar() {
    $locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

    return (bool) apply_filters(
        'order_supply_list_use_jalali_calendar',
        strpos( (string) $locale, 'fa' ) === 0
    );
}

/**
 * Convert the user-entered date to a Gregorian YYYY-MM-DD date for WooCommerce.
 * Persian UI accepts Jalali YYYY/MM/DD. English/LTR UI accepts Gregorian YYYY-MM-DD.
 */
function osl_parse_input_date_to_gregorian( $date ) {
    $date = trim( (string) $date );

    if ( $date === '' ) {
        return '';
    }

    if ( osl_use_jalali_calendar() ) {
        return osl_jalali_str_to_gregorian_str( $date );
    }

    if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
        return '';
    }

    $year  = (int) $m[1];
    $month = (int) $m[2];
    $day   = (int) $m[3];

    if ( ! checkdate( $month, $day, $year ) ) {
        return '';
    }

    return sprintf( '%04d-%02d-%02d', $year, $month, $day );
}

/**
 * Format a Gregorian date for display according to the active admin calendar.
 */
function osl_format_display_date( $gregorian_date ) {
    $gregorian_date = trim( (string) $gregorian_date );

    if ( $gregorian_date === '' ) {
        return '';
    }

    if ( osl_use_jalali_calendar() ) {
        return osl_to_jalali_str( $gregorian_date );
    }

    return $gregorian_date;
}

function osl_date_format_hint() {
    return osl_use_jalali_calendar() ? '1404/01/01' : '2025-03-21';
}

function osl_date_to_format_hint() {
    return osl_use_jalali_calendar() ? '1404/12/29' : '2026-03-20';
}

/* ── Variation attribute label ── */
function osl_get_variation_label( $variation_id ) {
    if ( ! $variation_id ) return '';
    $variation = wc_get_product( $variation_id );
    if ( ! $variation || ! $variation->is_type( 'variation' ) ) return '';
    $parts = [];
    foreach ( $variation->get_variation_attributes() as $attr_key => $attr_val ) {
        $taxonomy = str_replace( 'attribute_', '', $attr_key );
        $slug     = urldecode( $attr_val );
        if ( taxonomy_exists( $taxonomy ) && $slug !== '' ) {
            $term    = get_term_by( 'slug', $slug, $taxonomy )
                    ?: get_term_by( 'name', $slug, $taxonomy );
            $parts[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug;
        } elseif ( $slug !== '' ) {
            $parts[] = $slug;
        }
    }
    return implode( ' / ', array_filter( $parts ) );
}

/* ══════════════════════════════════════════
   Core data retrieval
══════════════════════════════════════════ */
function osl_get_data( $date_from = '', $date_to = '' ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return [ 'error' => __( 'WooCommerce is not active.', 'order-supply-list' ) ];
    }

    /**
     * Filter the order statuses included in the supply report.
     * Default: processing, on-hold, pending.
     *
     * @param string[] $statuses Array of WooCommerce status slugs (with wc- prefix).
     */
    $statuses = apply_filters(
        'order_supply_list_statuses',
        [ 'wc-processing', 'wc-on-hold', 'wc-pending' ]
    );
    // Sanitize: allow only known wc- prefixed statuses
    $valid_statuses = array_keys( wc_get_order_statuses() );
    $statuses       = array_values( array_intersect( $statuses, $valid_statuses ) );
    if ( empty( $statuses ) ) {
        $statuses = [ 'wc-processing', 'wc-on-hold', 'wc-pending' ];
    }
    $args     = [
        'status' => $statuses,
        'return' => 'ids',
    ];

    $from_g              = $date_from ? osl_parse_input_date_to_gregorian( $date_from ) : '';
    $to_g                = $date_to   ? osl_parse_input_date_to_gregorian( $date_to )   : '';
    $using_default_range = false;

    if ( $from_g && $to_g ) {
        // Explicit range — no row limit needed, date bounds protect performance
        $args['limit']        = -1;
        $args['date_created'] = $from_g . ' 00:00:00...' . $to_g . ' 23:59:59';
    } elseif ( $from_g ) {
        $args['limit']        = -1;
        $args['date_created'] = '>=' . $from_g . ' 00:00:00';
    } elseif ( $to_g ) {
        $args['limit']        = -1;
        $args['date_created'] = '<=' . $to_g . ' 23:59:59';
    } else {
        // No date filter → cap at 90 days + 2000 rows to protect performance
        $default_from         = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
        $args['limit']        = 2000;
        $args['date_created'] = '>=' . $default_from . ' 00:00:00';
        $using_default_range  = true;
    }

    $order_ids   = wc_get_orders( $args );
    $product_qty = [];
    $var_labels  = [];
    $item_names  = []; // fallback names for deleted products (#9)

    foreach ( $order_ids as $oid ) {
        $order = wc_get_order( $oid );
        if ( ! $order ) continue;
        foreach ( $order->get_items() as $item ) {
            $pid = (int) $item->get_product_id();
            $vid = (int) $item->get_variation_id();
            $qty = (int) $item->get_quantity();
            $key = $pid . '_' . $vid;
            $product_qty[ $key ] = ( $product_qty[ $key ] ?? 0 ) + $qty;
            // Store the order-item name as fallback for deleted products (#9)
            if ( ! isset( $item_names[ $key ] ) ) {
                $item_names[ $key ] = $item->get_name();
            }
            if ( $vid && ! isset( $var_labels[ $key ] ) ) {
                $var_labels[ $key ] = osl_get_variation_label( $vid );
            }
        }
    }

    $now_date = osl_format_display_date( current_time( 'Y-m-d' ) );
    $now_time = current_time( 'H:i' );

    if ( empty( $product_qty ) ) {
        return [
            'categories'          => [],
            'total_orders'        => count( $order_ids ),
            'generated'           => $now_date . ' — ' . $now_time,
            'site'                => get_bloginfo( 'name' ),
            'using_default_range' => $using_default_range,
        ];
    }

    $parent_cats  = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0 ] );
    $categories   = [];
    $assigned_keys = []; // #11: track keys already placed under a parent cat

    // Build a deleted-products bucket (#9)
    $deleted_items = [];

    foreach ( $parent_cats as $pcat ) {
        if ( $pcat->slug === 'uncategorized' ) continue;
        $cat_ids = osl_child_ids( $pcat->term_id );
        $items   = [];

        foreach ( $product_qty as $key => $qty ) {
            // #11: each product key appears under exactly one parent category
            if ( isset( $assigned_keys[ $key ] ) ) continue;

            list( $pid ) = explode( '_', $key, 2 );
            $pid       = (int) $pid;
            $prod_cats = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'ids' ] );

            if ( empty( array_intersect( $prod_cats, $cat_ids ) ) ) continue;

            $product = wc_get_product( $pid );

            // #9: product deleted — use order-item name, place under special sub
            if ( ! $product ) {
                $deleted_items[] = [
                    'name'      => $item_names[ $key ] ?? __( '(deleted product)', 'order-supply-list' ),
                    'sku'       => '—',
                    'variation' => $var_labels[ $key ] ?? '',
                    'sub'       => __( 'Deleted / Uncategorised', 'order-supply-list' ),
                    'qty'       => $qty,
                    'thumb'     => '',
                    'deleted'   => true,
                ];
                $assigned_keys[ $key ] = true;
                continue;
            }

            // #10: find the deepest child category under this parent
            $sub      = $pcat->name;
            $best_depth = 0;
            foreach ( $prod_cats as $cid ) {
                if ( ! in_array( $cid, $cat_ids, true ) || $cid === $pcat->term_id ) continue;
                $depth = osl_term_depth( $cid, $pcat->term_id );
                if ( $depth > $best_depth ) {
                    $t = get_term( $cid, 'product_cat' );
                    if ( $t && ! is_wp_error( $t ) ) {
                        $sub        = $t->name;
                        $best_depth = $depth;
                    }
                }
            }

            $thumb_id  = $product->get_image_id();
            $thumb_url = $thumb_id
                ? wp_get_attachment_image_url( $thumb_id, [ 48, 48 ] )
                : wc_placeholder_img_src( 'thumbnail' );

            $items[]                   = [
                'name'      => $product->get_name(),
                'sku'       => $product->get_sku() ?: '—',
                'variation' => $var_labels[ $key ] ?? '',
                'sub'       => $sub,
                'qty'       => $qty,
                'thumb'     => $thumb_url,
                'deleted'   => false,
            ];
            $assigned_keys[ $key ] = true; // #11: mark as claimed
        }

        if ( empty( $items ) ) continue;

        $grouped = [];
        foreach ( $items as $item ) $grouped[ $item['sub'] ][] = $item;
        ksort( $grouped );
        foreach ( $grouped as &$sg ) usort( $sg, fn( $a, $b ) => $b['qty'] - $a['qty'] );
        unset( $sg );

        $categories[] = [
            'name'      => $pcat->name,
            'total_qty' => array_sum( array_column( $items, 'qty' ) ),
            'grouped'   => $grouped,
        ];
    }

    // #9: append deleted/uncategorised products as a separate section
    if ( ! empty( $deleted_items ) ) {
        $grouped_del = [];
        foreach ( $deleted_items as $item ) $grouped_del[ $item['sub'] ][] = $item;
        foreach ( $grouped_del as &$sg ) usort( $sg, fn( $a, $b ) => $b['qty'] - $a['qty'] );
        unset( $sg );
        $categories[] = [
            'name'      => __( '⚠ Deleted / Uncategorised Products', 'order-supply-list' ),
            'total_qty' => array_sum( array_column( $deleted_items, 'qty' ) ),
            'grouped'   => $grouped_del,
        ];
    }

    usort( $categories, fn( $a, $b ) => $b['total_qty'] - $a['total_qty'] );

    return [
        'categories'          => $categories,
        'total_orders'        => count( $order_ids ),
        'generated'           => $now_date . ' — ' . $now_time,
        'site'                => get_bloginfo( 'name' ),
        'using_default_range' => $using_default_range,
    ];
}

function osl_child_ids( $parent_id ) {
    $ids  = [ $parent_id ];
    $kids = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent_id ] );
    foreach ( $kids as $k ) $ids = array_merge( $ids, osl_child_ids( $k->term_id ) );
    return $ids;
}

/**
 * Returns the depth of $term_id within the subtree rooted at $root_id.
 * Direct children of root = depth 1, grandchildren = 2, etc.
 * Returns 0 if $term_id equals $root_id or is not a descendant.
 */
function osl_term_depth( $term_id, $root_id ) {
    $depth = 0;
    $term  = get_term( $term_id, 'product_cat' );
    while ( $term && ! is_wp_error( $term ) && $term->term_id !== $root_id ) {
        $depth++;
        if ( $term->parent === 0 ) return 0; // not under root
        $term = get_term( $term->parent, 'product_cat' );
    }
    return $depth;
}

/* ══════════════════════════════════════════
   Page render
══════════════════════════════════════════ */
function osl_render_page() {
    // Defense-in-depth capability check
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'order-supply-list' ) );
    }

    $use_jalali          = osl_use_jalali_calendar();
    $date_input_type     = $use_jalali ? 'text' : 'date';
    $date_from_hint      = osl_date_format_hint();
    $date_to_hint        = osl_date_to_format_hint();
    $root_dir            = is_rtl() ? 'rtl' : 'ltr';
    $from_date           = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
    $to_date             = isset( $_GET['date_to'] )   ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) )   : '';

    // Reject invalid date input visibly. Persian UI expects Jalali, English UI expects Gregorian.
    $date_error = false;
    if ( $from_date && ! osl_parse_input_date_to_gregorian( $from_date ) ) {
        $date_error = sprintf(
            /* translators: %s: expected date format example */
            __( 'Invalid "From" date. Use format: %s', 'order-supply-list' ),
            $date_from_hint
        );
        $from_date = '';
    }
    if ( $to_date && ! osl_parse_input_date_to_gregorian( $to_date ) ) {
        $date_error = sprintf(
            /* translators: %s: expected date format example */
            __( 'Invalid "To" date. Use format: %s', 'order-supply-list' ),
            $date_to_hint
        );
        $to_date = '';
    }

    $d = osl_get_data( $from_date, $to_date );
    ?>
    <div id="osl-root" dir="<?php echo esc_attr( $root_dir ); ?>" data-calendar="<?php echo esc_attr( $use_jalali ? 'jalali' : 'gregorian' ); ?>">

        <div class="osl-toolbar no-print">
            <div class="osl-toolbar__left">
                <h1>📋 <?php esc_html_e( 'Order Supply List', 'order-supply-list' ); ?></h1>
                <span class="osl-meta">
                    <?php
                    printf(
                        /* translators: %d: number of active orders */
                        esc_html( _n( '%d active order', '%d active orders', $d['total_orders'] ?? 0, 'order-supply-list' ) ),
                        (int) ( $d['total_orders'] ?? 0 )
                    );
                    ?>
                    &nbsp;|&nbsp;
                    <?php esc_html_e( 'Updated:', 'order-supply-list' ); ?> <?php echo esc_html( $d['generated'] ?? '' ); ?>
                </span>
            </div>
            <div class="osl-toolbar__right">
                <button id="osl-refresh" class="osl-btn osl-btn--refresh">↻ <?php esc_html_e( 'Refresh', 'order-supply-list' ); ?></button>
                <button onclick="window.print()" class="osl-btn osl-btn--print">🖨 <?php esc_html_e( 'Print', 'order-supply-list' ); ?></button>
            </div>
        </div>

        <form method="get" action="" class="osl-filter-bar no-print">
            <input type="hidden" name="page" value="order-supply-list">
            <div class="osl-dp-wrap">
                <label><?php esc_html_e( 'From:', 'order-supply-list' ); ?></label>
                <input type="<?php echo esc_attr( $date_input_type ); ?>" name="date_from" class="osl-dp-input" placeholder="<?php echo esc_attr( $date_from_hint ); ?>" value="<?php echo esc_attr( $from_date ); ?>" <?php echo $use_jalali ? 'data-jdp data-jdp-only-date autocomplete="off"' : ''; ?>>
                <span class="osl-dp-sep"><?php esc_html_e( 'To', 'order-supply-list' ); ?></span>
                <input type="<?php echo esc_attr( $date_input_type ); ?>" name="date_to" class="osl-dp-input" placeholder="<?php echo esc_attr( $date_to_hint ); ?>" value="<?php echo esc_attr( $to_date ); ?>" <?php echo $use_jalali ? 'data-jdp data-jdp-only-date autocomplete="off"' : ''; ?>>
                <button type="submit" class="osl-dp-apply"><?php esc_html_e( 'Apply Filter', 'order-supply-list' ); ?></button>
                <a href="?page=order-supply-list" class="osl-dp-clear"><?php esc_html_e( 'Clear', 'order-supply-list' ); ?></a>
            </div>
        </form>

        <div class="osl-print-head print-only">
            <table class="osl-print-head-table"><tr>
                <td class="osl-print-head-title"><?php esc_html_e( 'Order Supply List', 'order-supply-list' ); ?></td>
                <td class="osl-print-head-meta">
                    <div><?php echo esc_html( $d['site'] ?? '' ); ?></div>
                    <div><?php esc_html_e( 'Date:', 'order-supply-list' ); ?> <?php echo esc_html( $d['generated'] ?? '' ); ?></div>
                    <div><?php echo (int) ( $d['total_orders'] ?? 0 ); ?> <?php esc_html_e( 'orders', 'order-supply-list' ); ?></div>
                </td>
            </tr></table>
        </div>

        <?php if ( isset( $d['error'] ) ) : ?>
            <p class="osl-error"><?php echo esc_html( $d['error'] ); ?></p>
        <?php else : ?>

        <?php if ( $date_error ) : ?>
            <div class="osl-notice osl-notice--error"><?php echo esc_html( $date_error ); ?></div>
        <?php endif; ?>

        <?php if ( ! empty( $d['using_default_range'] ) ) : ?>
            <div class="osl-notice osl-notice--warning">
                <?php esc_html_e( 'No date filter applied. Showing orders from the last 90 days only. Use the date filter above to view a different range.', 'order-supply-list' ); ?>
            </div>
        <?php endif; ?>

        <?php if ( empty( $d['categories'] ) ) : ?>
            <p class="osl-empty"><?php esc_html_e( 'No active orders found.', 'order-supply-list' ); ?></p>
        <?php else : ?>

        <div id="osl-content">
            <?php foreach ( $d['categories'] as $cat ) : ?>
            <div class="osl-cat">
                <div class="osl-cat-head">
                    <span class="osl-cat-name"><?php echo esc_html( $cat['name'] ); ?></span>
                    <span class="osl-cat-total">
                        <?php esc_html_e( 'Total:', 'order-supply-list' ); ?>
                        <?php echo number_format( $cat['total_qty'] ); ?>
                        <?php esc_html_e( 'units', 'order-supply-list' ); ?>
                    </span>
                </div>
                <table class="osl-table">
                    <caption class="osl-print-caption"><?php echo esc_html( $cat['name'] ); ?> — <?php esc_html_e( 'Total:', 'order-supply-list' ); ?> <?php echo number_format( $cat['total_qty'] ); ?> <?php esc_html_e( 'units', 'order-supply-list' ); ?></caption>
                    <thead><tr>
                        <th class="th-num"><?php esc_html_e( '#', 'order-supply-list' ); ?></th>
                        <th class="th-img"><?php esc_html_e( 'Image', 'order-supply-list' ); ?></th>
                        <th class="th-name"><?php esc_html_e( 'Product', 'order-supply-list' ); ?></th>
                        <th class="th-var"><?php esc_html_e( 'Variation', 'order-supply-list' ); ?></th>
                        <th class="th-sku"><?php esc_html_e( 'SKU', 'order-supply-list' ); ?></th>
                        <th class="th-qty"><?php esc_html_e( 'Qty', 'order-supply-list' ); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php $row = 1; foreach ( $cat['grouped'] as $sub_name => $sub_items ) : ?>
                        <tr class="osl-subcat-row">
                            <td colspan="5" class="td-subcat-name">📂 <?php echo esc_html( $sub_name ); ?></td>
                            <td class="td-subcat-qty"><?php echo number_format( array_sum( array_column( $sub_items, 'qty' ) ) ); ?></td>
                        </tr>
                        <?php foreach ( $sub_items as $item ) : ?>
                        <tr>
                            <td class="td-num"><?php echo (int) $row++; ?></td>
                            <td class="td-img">
                                <?php if ( $item['thumb'] ) : ?>
                                <img src="<?php echo esc_url( $item['thumb'] ); ?>" class="osl-thumb" alt="<?php echo esc_attr( $item['name'] ); ?>">
                                <?php endif; ?>
                            </td>
                            <td class="td-name"><?php echo esc_html( $item['name'] ); ?></td>
                            <td class="td-var"><?php echo esc_html( $item['variation'] ?: '—' ); ?></td>
                            <td class="td-sku"><?php echo esc_html( $item['sku'] ); ?></td>
                            <td class="td-qty"><?php echo number_format( $item['qty'] ); ?></td>
                        </tr>
                        <?php endforeach; endforeach; ?>
                    </tbody>
                    <tfoot><tr class="osl-foot">
                        <td colspan="5" class="td-foot-label">
                            <?php esc_html_e( 'Total —', 'order-supply-list' ); ?> <?php echo esc_html( $cat['name'] ); ?>
                        </td>
                        <td class="td-qty"><?php echo number_format( $cat['total_qty'] ); ?></td>
                    </tr></tfoot>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
