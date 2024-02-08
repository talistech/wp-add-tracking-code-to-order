<?php
/*
Plugin Name: Talistech - Add Tracking Code
Description: Add custom fields to the WooCommerce Edit Order page and send a custom email notification.
Version: 1.5
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

        woocommerce_wp_select(
            array(
                'id'    => '_transporteur',
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
                    'Klaar om opgehaald te worden' => __('Klaar om opgehaald te worden', 'woocommerce'), // New transporteur
                ),
                'value' => $transporteur,
                'class' => 'form-field-wide', // Add the 'form-field-wide' class
            )
        );

        // Add JavaScript to disable tracking_code when 'Klaar om opgehaald te worden' is selected
        echo '<script>
            jQuery(function($){
                $(\'#_transporteur\').change(function(){
                    var selectedTransporteur = $(this).val();
                    var trackingCodeField = $(\'#_tracking_link\');

                    if(selectedTransporteur === \'Klaar om opgehaald te worden\') {
                        trackingCodeField.prop(\'disabled\', true);
                    } else {
                        trackingCodeField.prop(\'disabled\', false);
                    }
                });

                // Trigger change event on page load
                $(\'#_transporteur\').change();
            });
        </script>';

        woocommerce_wp_text_input(
            array(
                'id'          => '_tracking_link',
                'label'       => __('Tracking Link', 'woocommerce'),
                'placeholder' => __('Enter tracking link', 'woocommerce'),
                'value'       => $tracking_link,
                'class'       => 'form-field-wide', // Add the 'form-field-wide' class
            )
        );

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
        (empty($existing_transporteur) && !empty($transporteur) && !empty($tracking_link)) ||
        (empty($existing_tracking_link) && !empty($tracking_link)) ||
        ($existing_tracking_link !== $tracking_link)
    );

    // Additional condition to check if the transporteur was switched to "Klaar om opgehaald te worden"
    $switched_to_klaar = ($existing_transporteur !== 'Klaar om opgehaald te worden') && ($transporteur === 'Klaar om opgehaald te worden');

    error_log('Transporteur: ' . $transporteur);
    error_log('Tracking Link: ' . $tracking_link);

    if ($should_send_email) {
        $order = wc_get_order($order_id);
        $shopname = get_bloginfo('name');
        $subject = __('We hebben je ' . $shopname . ' pakketje afgegeven aan het transportbedrijf!', 'woocommerce');

        // Format the message using HTML
        $message = sprintf(
            __(
                'Goed nieuws! We hebben je pakketje afgegeven aan het transportbedrijf. Binnen 2 a 3 dagen mag jij je pakketje thuis verwachten.<br><br><strong>Transportbedrijf:</strong> %s<br><strong>Tracking code/link:</strong> %s',
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

    // Send additional email for "Klaar om opgehaald te worden"
    if ($switched_to_klaar) {
        $order = wc_get_order($order_id);
        $shopname = get_bloginfo('name');
        $subject_klaar = __('Je ' . $shopname . ' pakketje ligt klaar om opgehaald te worden!', 'woocommerce');

        // Format the message using HTML
        $message_klaar = sprintf(
            __(
                'Goed nieuws! We hebben je pakketje zorgvuldig klaargemaakt en het ligt klaar om opgehaald te worden. Houd onze openingsuren goed in de gaten alvorens je bij ons op bezoek komt. Tot dan!',
                'woocommerce'
            )
        );

        // Indicate that the email content is HTML
        $headers_klaar = array('Content-Type: text/html; charset=UTF-8');

        // Send HTML email for "Klaar om opgehaald te worden"
        wc_mail(
            $order->get_billing_email(),
            $subject_klaar,
            $message_klaar,
            '',
            $headers_klaar
        );
    }
}
add_action('woocommerce_process_shop_order_meta', 'custom_save_order_fields', 10, 1);
?>
