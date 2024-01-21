<?php
/*
Plugin Name: Talistech - Add Tracking Code
Description: Add custom fields to the WooCommerce Edit Order page and send a custom email notification.
Version: 1.0
Author: Talistech.com
*/

// Add custom fields to the WooCommerce Edit Order page
function custom_add_order_fields() {
    if (
        isset($_GET['page']) && $_GET['page'] === 'wc-orders' &&
        isset($_GET['action']) && $_GET['action'] === 'edit' &&
        isset($_GET['id']) && is_numeric($_GET['id'])
    ) {
        $order_id = intval($_GET['id']);
        $transporteur = get_post_meta($order_id, '_transporteur', true);
        $tracking_link = get_post_meta($order_id, '_tracking_link', true);

        echo '<div class="order_data_column" style="width:100%;">';
        woocommerce_wp_select(
            array(
                'id' => '_transporteur',
                'label' => __('Transporteur', 'woocommerce'),
                'options' => array(
                    'Bpost' => __('Bpost', 'woocommerce'),
                    'PostNL' => __('PostNL', 'woocommerce'),
                    'DPD' => __('DPD', 'woocommerce'),
                    'DHL' => __('DHL', 'woocommerce'),
                    'GLS' => __('GLS', 'woocommerce'),
                    'FedEX' => __('FedEX', 'woocommerce'),
                    'UPS' => __('UPS', 'woocommerce'),
                    'Modial Relay' => __('Modial Relay', 'woocommerce'),
                    'TNT' => __('TNT', 'woocommerce'),
                ),
                'value' => $transporteur,
            )
        );
        woocommerce_wp_text_input(
            array(
                'id' => '_tracking_link',
                'label' => __('Tracking Link', 'woocommerce'),
                'placeholder' => __('Enter tracking link', 'woocommerce'),
                'value' => $tracking_link,
            )
        );
        echo '</div>';
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'custom_add_order_fields');

// Save custom fields when the order is saved
function custom_save_order_fields($order_id) {
    $existing_transporteur = get_post_meta($order_id, '_transporteur', true);
    $existing_tracking_link = get_post_meta($order_id, '_tracking_link', true);

    $transporteur = isset($_POST['_transporteur']) ? wc_clean($_POST['_transporteur']) : '';
    update_post_meta($order_id, '_transporteur', $transporteur);

    $tracking_link = isset($_POST['_tracking_link']) ? wc_clean($_POST['_tracking_link']) : '';
    update_post_meta($order_id, '_tracking_link', $tracking_link);

    $should_send_email = (
        (empty($existing_transporteur) && !empty($transporteur)) ||
        (empty($existing_tracking_link) && !empty($tracking_link)) ||
        ($existing_transporteur !== $transporteur) ||
        ($existing_tracking_link !== $tracking_link)
    );

    error_log('Transporteur: ' . $transporteur);
    error_log('Tracking Link: ' . $tracking_link);

    if ($should_send_email) {
        $order = wc_get_order($order_id);
        $shopname = get_bloginfo('name');
        $subject = __('We hebben je ' . $shopname . ' pakketje afgegeven aan het transportbedrijf!', 'woocommerce');

        // Format the message using HTML
        $message = sprintf(
            __(
                'Goed nieuws! We hebben je pakketje afgegeven aan het transportbedrijf. Binnen 2 a 3 dagen mag jij je pakketje thuis verwachten.<br><br><strong>Transportbedrijf:</strong> %s<br><strong>Tracking link:</strong> %s',
                'woocommerce'
            ),
            $transporteur,
            (preg_match('/^https?:\/\//i', $tracking_link) ? '<a href="' . esc_url($tracking_link) . '">' . $tracking_link . '</a>' : $tracking_link)
        );

        // Indicate that the email content is HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send HTML email
        wc_mail(
            $order->get_billing_email(),
            $subject,
            $message,
            '',
            $headers
        );
    }
}
add_action('woocommerce_process_shop_order_meta', 'custom_save_order_fields', 10, 1);
