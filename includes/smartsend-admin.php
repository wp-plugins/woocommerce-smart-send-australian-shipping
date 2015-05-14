<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
add_action( 'admin_print_styles', 'smartsend_enqueue_admin_scripts', 15);

function smartsend_enqueue_admin_scripts()
{
    $cssPath = realpath(dirname(__FILE__).'/../assets/css/');
    $CSSurl = plugins_url('css/admin.css', $cssPath.'admin.css');
    // Admin Styles
    wp_register_style( 'smart_send_admin_styles', $CSSurl);
    wp_enqueue_style('smart_send_admin_styles');
}

/**
 * ORDER SUMMARY
 * Ref. woocommerce/includes/admin/class-wc-admin-post-types.php Line 596
 *
 * $the_order is an instance of woocommerce/includes/class-wc-order.php which is abstracted from woocommerce/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-order.php
 */

add_filter('woocommerce_admin_order_actions', 'smart_send_summary_fulfillment_button');

/**
 * Check if this order has SSPriceID and SSstatus, if so add manual 'ship' or 'sent' buttons
 *
 * @param $actions
 *
 * @return $actions
 */
function smart_send_summary_fulfillment_button( $actions )
{
    global $post;
    if ( !smart_send_sendable_order($post->post_status))
        return $actions;

    if ( ($SSstatus = get_post_meta($post->ID, 'SSstatus')) && $SSpriceID = get_post_meta($post->ID, 'SSpriceID') )
    {
        $status = $SSstatus[0];

/*        if ($status == 'quoted') // We show 'ship'
        {
            $actions['ship'] = array(
                'url' 		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=smart_send_ship_order&order_id=' . $post->ID .'&set_status=ordered&priceID='.$SSpriceID[0] ), 'smart-send-ship-order' ),
                'name' 		=> __( 'Ship order', 'smart_send' ),
                'action' 	=> "ss_ship ss_button ssaction ssbuttonstyle"
            );
        }
        else*/if ( $status == 'ordered')
        {
            $actions['ship'] = array(
//                'url' 		=> wp_nonce_url( admin_url( 'admin-ajax.php?iframe=true&width=90%&height=90%&action=smart_send_ship_order&order_id=' . $post->ID .'&set_status=shipped&priceID='.$SSpriceID[0] ), 'smart-send-ship-order' ),
                'url' 		=> 'http://support.smartsend.com.au/anonymous_requests/new',
                'name' 		=> __( 'Track', 'smart_send' ),
                'action' 	=> "ss_shipped ss_button ssbuttonstyle"
            );
        }
        elseif ( $status == 'pending')
        {
            $actions['ship'] = array(
                'url' 		=> '',
                'name' 		=> __( 'Shipment Pending', 'smart_send' ),
                'action' 	=> "ss_pending"
            );
        }
        elseif ( $status == 'shipped')
        {
            $actions['ship'] = array(
                'url' 		=> '',
                'name' 		=> __( 'Shipped', 'smart_send' ),
                'action' 	=> ""
            );
        }
    }

    return $actions;
}

add_action( 'woocommerce_admin_order_actions_end','ss_orderview_js' );

function ss_orderview_js()
{
    ?>
    <script type="text/javascript">
        jQuery(".ss_shipped").attr("rel", "prettyPhoto[iframes]");
    </script>
    <?php
}
/**
 * ORDER VIEW
 * Smart Send order view details, underneath billing address
 *
 * Either buttons or information are shown
 */
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'smart_send_order_view_button', 10, 1 );

function smart_send_order_view_button($order)
{
    $order_id = $order->id;
    $ssOrder = new smartSendOrder($order->id);
    $wcStatus = $order->post_status;

    $orderMeta = $ssOrder->wcMeta;
    $priceID   = $ssOrder->priceId;
    $status    = $ssOrder->shippingStatus;
    $ssSettings = get_option( 'woocommerce_smart_send_settings', null );

    $url = wp_nonce_url( admin_url( 'admin-ajax.php?action=smart_send_ship_order&order_id=' . $order_id .'&priceID='.$priceID ), 'smart-send-ship-order' );

    if (!empty($ssOrder->bookingReference) )
    {    ?>
        <div class="ss_notice">
            Smart Send reference ID: <?php echo $ssOrder->bookingReference; ?>
            <?php
            if (!empty($orderMeta['SSpickupDate']))
                echo '<br>Pickup date: '.$orderMeta['SSpickupDate'][0];
            ?>
        </div>
        <br clear=left"><br>
        <?php
    }

    switch($status)
    {
        // If the order is not in a shippable state
        // TODO: IF order is same day / local 1/2/3/4 hour etc do not show datepicker
        case 'quoted':

            if (in_array($wcStatus, array('wc-processing', 'wc-completed')))
            {
                $url .= '&set_status=ordered';
                $shipDelay = !empty($ssSettings['ship_delay']) ? $ssSettings['ship_delay'] : 0;
                // 10 hours gmt + shipping delay
                $timePlus = time() + 36000 + 86400 * $shipDelay;
                if (!$shipDelay)
                {
                    $timePlus += (12 * 60 * 60 + 16 * 60); // Add 12 hours and 16 minutes to push over if past 11:45am
                }
                $shipDate = gmdate("Y-m-d", $timePlus);
                $dayList = smartSendAPI::weekendless($shipDate);
                $shipDate = $dayList[0];
                ?>
                <span class="button ssbuttonstyle ss-show-ship">Ship Order</span>
                <br>
                <div class="shipping-date">
                    Pickup Date:
                    <input type="text" name="shipdate" class="shipdate" value="<?php echo $shipDate?>"/>
                    <a class="button ssaction ssbuttonstyle ss-order-shipping" href="<?php echo $url; ?>" alt="<?php echo $shipDate?>">Book</a><br/>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(".ss-show-ship").on("click", function() {
                            $(".shipping-date").css({display: "inline-block"});
                        });

                        var dt = new Date(), h = dt.getUTCHours() + 10, m = dt.getUTCMinutes(), weekday = dt.getDay(), minDateStart = 0, dayOffset = 7 - weekday;
                        if (h >= 24) {
                            h = h - 24;
                        }
                        console.log('Hour = ' + h + ' and minute = ' + m);
                        if (h >= 12 || (h == 11 && m >= 44 )) {
                            minDateStart = 1;
                        }

                        // Saturday or Sunday, make sure datestart is Monday
                        // If Sunday, offset = 1
                        // If Saturday, offset = 2
                        if (dayOffset <= 2  && (minDateStart < dayOffset)) {
                            minDateStart = dayOffset;
                        }


                        $(".shipdate").datepicker({
                            minDate: minDateStart,
                            maxDate: 14,
                            dateFormat: "yy-mm-dd",
                            beforeShowDay: available,
                            onSelect: function(textDate, _inst) {
                                $(".ss-order-shipping").attr("alt", textDate);
                            }
                        });

                        var availableDates = ["<?php echo implode( '", "', $dayList);?>"];
                        function available(date) {
                            var dmy = date.getFullYear() + "-" + nPad(date.getMonth()+1) + "-" + nPad(date.getDate());
                            if ($.inArray(dmy, availableDates) != -1) {
                                return [true, "","Available"];
                            } else {
                                return [false,"","unAvailable"];
                            }
                        }
                        function nPad(d) {
                            return (d < 10) ? '0' + d.toString() : d.toString();
                        }
                    });
                </script>
                <?php
            }
            else
            {
                ?>
                <div class="ss_notice">Order status must be one of 'Processing' or 'Completed' to enable Smart Send shipping fulfilment</div>
                <?php
            }

            break;

        case 'ordered':

            $url .= '&set_status=shipped&iframe=true&width=75%&height=75%';
            $trackingId = $ssOrder->getTrackingId();
            if ($trackingId)
            {
                ?>
                <a class="button ssbuttonstyle ss-track-shipping" href="http://support.smartsend.com.au/anonymous_requests/new" rel="prettyPhoto[iframes]" target="_blank">Track Shipping</a><br/>
            <?php
            }
                else
                    echo 'No tracking reference ID';
            if (!empty($orderMeta['SStrackingID']))
            {
                $labelFile = $ssOrder->getLabel();
                $labelPath = realpath(dirname(__FILE__).'/..').'/labels/';
                ?><br/>
                <a class="button ssbuttonstyle" href="<?php echo plugins_url('labels',$labelPath).'/'.$labelFile; ?>" target="_blank">Print / View Shipping Label</a><br/>
            <?php
            }
            else
                echo '<p>Please refresh page to see <b>Shipping Label</b></p>';
            break;

        case 'pending':

            $url .= '&set_status=shipped';
            ?>
            <div class="ss_notice">Order cannot be completed here, please login to the <br><a href="https://www.smartsend.com.au/VIP/apiBookings.cfm">API Bookings</a> section of the VIP admin dashboard</div>
            <?php
            break;

        case 'shipped':
            ?>
            <div class="ss_notice">Order has been shipped.</div>
            <?php
            break;
    }
}

add_action( 'woocommerce_product_options_pricing', 'smart_send_show_product_cost' );

// Wholesale Price
function smart_send_show_product_cost() {
    woocommerce_wp_text_input( array( 'id'          => 'SScostPrice',
                                      'data_type'   => 'price',
                                      'label'       => __( 'Cost Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
                                      'desc_tip'    => 'true',
                                      'description' => __( 'The price the product cost you, the merchant. Used to calculate Smart Send transport assurance.' )
    ) );
}

add_action('woocommerce_process_product_meta_simple', 'smart_send_save_product_cost');
function smart_send_save_product_cost($post_id) {
    update_post_meta( $post_id, 'SScostPrice', stripslashes( $_POST['SScostPrice'] ) );
}

/*
 *   Functionality for order redemption buttons
 */

add_action( 'admin_footer', 'smart_send_order_js' ); // Write our JS below here

/**
 *  Submit booking request via AJAX; if successful, refresh page, otherwise show warning
 */
function smart_send_order_js()
{ ?>
    <script type="text/javascript" >
        jQuery(document).ready(function($) {

//            $("a[rel^='prettyPhoto']").prettyPhoto({
//                opacity: 0.8,
//                theme: 'pp_woocommerce'
//            });

            // Trigger on clicking '.ss_button'
            $(".ssaction").on("click", function(ev) {
                ev.preventDefault();

                var buttonText = $(this).text(), _self = $(this), ajaxUrl = $(this).attr("href");

                if ($(this).attr("alt"))
                {
                    ajaxUrl += "&pickupdate="+$(this).attr("alt");
                }

                $(this).text('PROCESSING..');

                $.getJSON( ajaxUrl, function(resp) {
                    var msg = resp.response;
                    if (msg == 'error') {
                        _self.text(buttonText);
                        alert(resp[0]);
                        return false;
                    }

                    if (msg == 'pending')
                    {
                        _self.text(buttonText);
                        alert(resp[0]);
                        location.reload(true);
                        return false;
                    }

                    if(_self.hasClass('ss-ship-order'))
                    {
                        alert('Shipping booked, reference number '+msg);
                        location.reload(true);
                    }
                    else if(_self.hasClass('ss-track-shipping'))
                    {
                        alert('Shipping booked, reference number '+msg);
                        location.reload(true);
                    }
                    else {
                        _self.text(buttonText);
                        alert(msg);
                    }
                    location.reload(true);
                });
            });
        });
    </script>
<?php
}

/**
 *  Check if the status is one that allows shipping buttons
 *
 * @param $status
 *
 * @return bool
 */
function smart_send_sendable_order($status)
{
    if (in_array( $status, array( 'wc-completed', 'wc-processing' ) ))
        return true;

    return false;
}