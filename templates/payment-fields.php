<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add SVG support
add_filter('wp_kses_allowed_html', 'lapinopay_allow_svg_tags', 10, 2);
function lapinopay_allow_svg_tags($allowed_tags, $context)
{
    if ($context === 'post') {
        $allowed_tags['svg'] = array(
            'xmlns' => true,
            'width' => true,
            'height' => true,
            'viewbox' => true,
            'fill' => true,
            'class' => true,
            'role' => true,
            'aria-label' => true,
        );
        $allowed_tags['path'] = array(
            'd' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
        );
        $allowed_tags['g'] = array(
            'clip-path' => true,
        );
        $allowed_tags['defs'] = array();
        $allowed_tags['clipPath'] = array(
            'id' => true,
        );
        $allowed_tags['rect'] = array(
            'width' => true,
            'height' => true,
            'fill' => true,
        );
    }
    return $allowed_tags;
}

// Register and enqueue styles and scripts
function lapinopay_enqueue_payment_assets()
{
    // Only enqueue on checkout page
    if (!is_checkout()) {
        return;
    }

    // Get the correct path to the CSS file
    $css_file = plugin_dir_path(dirname(__FILE__)) . 'assets/css/lapinopay-payment-gateway-styles.css';
    $css_url = plugins_url('assets/css/lapinopay-payment-gateway-styles.css', dirname(__FILE__));

    // Only proceed if the CSS file exists
    if (file_exists($css_file)) {
        $version = filemtime($css_file);

        // Register and enqueue styles with proper dependencies
        wp_register_style(
            'lapinopay-payment-styles',
            $css_url,
            array('woocommerce-general', 'woocommerce-layout'),
            $version,
            'all'
        );
        wp_enqueue_style('lapinopay-payment-styles');
    } else {
        // Use WordPress debug log instead of error_log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Use WordPress debug log
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->warning('LapinoPay: CSS file not found at ' . $css_file, array('source' => 'lapinopay'));
            }
        }
        return;
    }
}

// Remove any existing hooks to prevent duplicate loading
remove_action('wp_enqueue_scripts', 'lapinopay_enqueue_payment_assets');
remove_action('admin_enqueue_scripts', 'lapinopay_enqueue_payment_assets');

// Add hooks with proper priority
add_action('wp_enqueue_scripts', 'lapinopay_enqueue_payment_assets', 20);
add_action('admin_enqueue_scripts', 'lapinopay_enqueue_payment_assets', 20);

// Add this helper function
function lapinopay_get_payment_icon($icon_name, $alt_text)
{
    // Register and enqueue the image with version
    $icon_path = plugin_dir_path(dirname(__FILE__)) . 'assets/icons/secure-payment.png';
    $icon_url = plugins_url('assets/icons/secure-payment.png', dirname(__FILE__));
    $version = filemtime($icon_path);

    if ($icon_name === 'shield-check') {
        // Try to get the attachment ID from the URL
        $attachment_id = attachment_url_to_postid($icon_url);

        if (!$attachment_id && file_exists($icon_path)) {
            // If no attachment ID, register the image with WordPress
            $upload_dir = wp_upload_dir();
            $image_data = file_get_contents($icon_path);
            $filename = basename($icon_path);

            if (wp_mkdir_p($upload_dir['path'])) {
                $file = $upload_dir['path'] . '/' . $filename;
            } else {
                $file = $upload_dir['basedir'] . '/' . $filename;
            }

            file_put_contents($file, $image_data);

            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attachment_id = wp_insert_attachment($attachment, $file);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file);
            wp_update_attachment_metadata($attachment_id, $attach_data);
        }

        if ($attachment_id) {
            // Register and enqueue the image
            wp_register_style(
                'lapinopay-shield-icon',
                false,
                array(),
                $version
            );
            wp_enqueue_style('lapinopay-shield-icon');

            return wp_get_attachment_image($attachment_id, 'full', false, array(
                'class' => 'lapinopay-payment-icon',
                'alt' => esc_attr($alt_text),
                'role' => 'img',
                'aria-label' => esc_attr($alt_text),
                'width' => '50',
                'style' => 'display: block;'
            ));
        }

        // If we couldn't create an attachment, register the image URL and use wp_get_attachment_image_url
        wp_register_style(
            'lapinopay-shield-icon',
            false,
            array(),
            $version
        );
        wp_enqueue_style('lapinopay-shield-icon');

        // Create a temporary attachment ID for the image
        $temp_attachment = array(
            'ID' => 0,
            'guid' => $icon_url,
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name(basename($icon_url)),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Use wp_get_attachment_image with the temporary attachment
        return wp_get_attachment_image(0, 'full', false, array(
            'class' => 'lapinopay-payment-icon',
            'alt' => esc_attr($alt_text),
            'role' => 'img',
            'aria-label' => esc_attr($alt_text),
            'width' => '50',
            'style' => 'display: block;',
            'src' => esc_url($icon_url)
        ));
    }

    // For SVG icons, we'll use inline SVG with proper sanitization
    $svg_icons = array(
        'credit-card' => '<svg width="133" height="33" viewBox="0 0 133 33" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . esc_attr($alt_text) . '">
            <path d="M38.2874 11.9634C38.2452 15.2933 41.255 17.1515 43.5223 18.2563C45.8517 19.3898 46.6342 20.1168 46.6249 21.1305C46.6076 22.6819 44.7668 23.3666 43.0443 23.3932C40.0391 23.4398 38.2917 22.5818 36.9026 21.933L35.82 26.9987C37.2137 27.6409 39.7945 28.201 42.4706 28.2256C48.7525 28.2256 52.8623 25.1247 52.8846 20.3169C52.9092 14.2151 44.4447 13.8773 44.5025 11.15C44.5225 10.323 45.3116 9.44053 47.0407 9.21614C47.8966 9.10279 50.2593 9.01604 52.9381 10.2496L53.9894 5.34838C52.5489 4.82385 50.6974 4.32159 48.3922 4.32159C42.4796 4.32159 38.3209 7.46472 38.2874 11.9634ZM64.092 4.74376C62.9449 4.74376 61.9783 5.41286 61.5468 6.43966L52.5734 27.8656H58.8507L60.0999 24.4133H67.7707L68.4953 27.8656H74.028L69.2 4.74376H64.092ZM64.9702 10.9898L66.7817 19.6723H61.8204L64.9702 10.9898ZM30.6765 4.74404L25.7284 27.8653H31.7102L36.656 4.74347L30.6765 4.74404ZM21.8274 4.74404L15.6013 20.4814L13.0827 7.10009C12.7872 5.60631 11.6202 4.74376 10.3242 4.74376H0.14646L0.00390625 5.41518C2.09335 5.86857 4.46733 6.59985 5.90559 7.38231C6.78579 7.86029 7.03677 8.27812 7.32593 9.41392L12.0961 27.8656H18.4174L28.1088 4.74376L21.8274 4.74404Z" fill="#222357"/>
            <path d="M97.6697 32.4803V30.3198C97.6697 29.4915 97.1654 28.9515 96.3012 28.9515C95.8691 28.9515 95.4009 29.0955 95.0768 29.5636C94.8249 29.1675 94.4648 28.9515 93.9247 28.9515C93.5644 28.9515 93.2046 29.0594 92.9163 29.4555V29.0235H92.1602V32.4803H92.9163V30.5718C92.9163 29.9598 93.2405 29.6716 93.7447 29.6716C94.2486 29.6716 94.5009 29.9957 94.5009 30.5718V32.4803H95.2571V30.5718C95.2571 29.9598 95.617 29.6716 96.0851 29.6716C96.5894 29.6716 96.8413 29.9957 96.8413 30.5718V32.4803H97.6697ZM108.868 29.0235H107.644V27.9792H106.888V29.0235H106.204V29.7075H106.888V31.292C106.888 32.0843 107.212 32.5523 108.076 32.5523C108.4 32.5523 108.76 32.4444 109.013 32.3003L108.796 31.6521C108.58 31.7961 108.328 31.8322 108.148 31.8322C107.788 31.8322 107.644 31.6162 107.644 31.256V29.7075H108.868V29.0235ZM115.278 28.9513C114.846 28.9513 114.558 29.1675 114.378 29.4555V29.0235H113.622V32.4803H114.378V30.5359C114.378 29.9598 114.63 29.6356 115.098 29.6356C115.242 29.6356 115.422 29.6717 115.566 29.7077L115.782 28.9875C115.638 28.9515 115.422 28.9515 115.278 28.9515M105.592 29.3117C105.231 29.0596 104.727 28.9516 104.187 28.9516C103.323 28.9516 102.747 29.3837 102.747 30.0679C102.747 30.6441 103.179 30.9681 103.935 31.0762L104.295 31.1123C104.691 31.1841 104.907 31.2922 104.907 31.4723C104.907 31.7243 104.619 31.9044 104.115 31.9044C103.611 31.9044 103.215 31.7243 102.963 31.5443L102.603 32.1204C102.999 32.4084 103.539 32.5525 104.079 32.5525C105.087 32.5525 105.664 32.0845 105.664 31.4362C105.664 30.8241 105.195 30.4999 104.475 30.392L104.115 30.3559C103.791 30.3198 103.539 30.248 103.539 30.0319C103.539 29.7798 103.791 29.6358 104.187 29.6358C104.619 29.6358 105.051 29.8157 105.268 29.9238L105.592 29.3117ZM125.685 28.9516C125.253 28.9516 124.965 29.1677 124.784 29.4557V29.0236H124.028V32.4805H124.784V30.536C124.784 29.9599 125.037 29.6358 125.505 29.6358C125.649 29.6358 125.829 29.6719 125.973 29.7078L126.189 28.9877C126.045 28.9516 125.829 28.9516 125.685 28.9516ZM116.034 30.752C116.034 31.7963 116.754 32.5525 117.871 32.5525C118.375 32.5525 118.735 32.4445 119.095 32.1565L118.735 31.5443C118.447 31.7604 118.159 31.8683 117.835 31.8683C117.223 31.8683 116.79 31.4362 116.79 30.752C116.79 30.104 117.223 29.6717 117.835 29.6358C118.159 29.6358 118.447 29.7437 118.735 29.9599L119.095 29.3478C118.735 29.0596 118.375 28.9516 117.871 28.9516C116.754 28.9516 116.034 29.7078 116.034 30.752ZM123.02 30.752V29.0236H122.264V29.4557C122.012 29.1317 121.652 28.9516 121.184 28.9516C120.211 28.9516 119.455 29.7078 119.455 30.752C119.455 31.7963 120.211 32.5525 121.184 32.5525C121.688 32.5525 122.048 32.3725 122.264 32.0484V32.4805H123.02V30.752ZM120.247 30.752C120.247 30.1399 120.643 29.6358 121.292 29.6358C121.904 29.6358 122.336 30.104 122.336 30.752C122.336 31.3642 121.904 31.8683 121.292 31.8683C120.643 31.8322 120.247 31.3642 120.247 30.752ZM111.209 28.9516C110.201 28.9516 109.481 29.6717 109.481 30.752C109.481 31.8324 110.201 32.5525 111.245 32.5525C111.749 32.5525 112.253 32.4084 112.649 32.0845L112.289 31.5443C112.001 31.7604 111.641 31.9044 111.281 31.9044C110.813 31.9044 110.345 31.6883 110.237 31.076H112.793V30.7881C112.83 29.6717 112.181 28.9516 111.209 28.9516ZM111.209 29.5997C111.677 29.5997 112.001 29.8879 112.073 30.4281H110.273C110.345 29.9599 110.669 29.5997 111.209 29.5997ZM129.97 30.752V27.6553H129.213V29.4557C128.961 29.1317 128.601 28.9516 128.133 28.9516C127.161 28.9516 126.405 29.7078 126.405 30.752C126.405 31.7963 127.161 32.5525 128.133 32.5525C128.637 32.5525 128.997 32.3725 129.213 32.0484V32.4805H129.97V30.752ZM127.197 30.752C127.197 30.1399 127.593 29.6358 128.241 29.6358C128.853 29.6358 129.285 30.104 129.285 30.752C129.285 31.3642 128.853 31.8683 128.241 31.8683C127.593 31.8322 127.197 31.3642 127.197 30.752ZM101.918 30.752V29.0236H101.162V29.4557C100.91 29.1317 100.55 28.9516 100.082 28.9516C99.1097 28.9516 98.3535 29.7078 98.3535 30.752C98.3535 31.7963 99.1097 32.5525 100.082 32.5525C100.586 32.5525 100.946 32.3725 101.162 32.0484V32.4805H101.918V30.752ZM99.1097 30.752C99.1097 30.1399 99.5059 29.6358 100.154 29.6358C100.766 29.6358 101.198 30.104 101.198 30.752C101.198 31.3642 100.766 31.8683 100.154 31.8683C99.5059 31.8322 99.1097 31.3642 99.1097 30.752Z" fill="black"/>
            <path d="M105.336 2.77246H116.679V23.1537H105.336V2.77246Z" fill="#FF5F00"/>
            <path d="M106.059 12.9633C106.059 8.82233 108.004 5.14931 110.992 2.77264C108.796 1.04423 106.023 0 102.999 0C95.8326 0 90.0352 5.7974 90.0352 12.9633C90.0352 20.1293 95.8326 25.9267 102.998 25.9267C106.023 25.9267 108.796 24.8824 110.992 23.1539C108.004 20.8133 106.059 17.1043 106.059 12.9633Z" fill="#EB001B"/>
            <path d="M131.985 12.9633C131.985 20.1291 126.187 25.9267 119.021 25.9267C115.997 25.9267 113.224 24.8824 111.027 23.1539C114.052 20.7774 115.961 17.1043 115.961 12.9633C115.961 8.82233 114.016 5.14931 111.027 2.77264C113.224 1.04423 115.997 0 119.021 0C126.187 0 131.985 5.83349 131.985 12.9633Z" fill="#F79E1B"/>
            </svg>',
        'revolut' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . esc_attr($alt_text) . '">
            <path d="M20.9148 6.957C20.9148 3.12 17.7918 0 13.9518 0H2.42578V3.86H13.4038C15.1418 3.86 16.5808 5.226 16.6128 6.904C16.6218 7.31655 16.5479 7.72672 16.3953 8.11014C16.2428 8.49356 16.0148 8.84242 15.7248 9.136C15.4368 9.43138 15.0925 9.66591 14.7122 9.82566C14.3318 9.98542 13.9233 10.0671 13.5108 10.066H9.23378C9.16093 10.0663 9.09113 10.0953 9.03962 10.1468C8.9881 10.1984 8.95904 10.2681 8.95878 10.341V13.772C8.95878 13.832 8.97611 13.886 9.01078 13.934L16.2668 24H21.5778L14.3048 13.906C17.9678 13.722 20.9148 10.646 20.9148 6.957ZM6.89578 5.923H2.42578V24H6.89578V5.923Z" fill="black"/>
            </svg>',
        'google-pay' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . esc_attr($alt_text) . '">
            <g clip-path="url(#clip0_3079_2221)">
            <path d="M8.35909 0.788944C5.96112 1.62082 3.89311 3.19976 2.45882 5.29382C1.02454 7.38789 0.299573 9.88671 0.390418 12.4233C0.481264 14.9598 1.38313 17.4004 2.96355 19.3864C4.54396 21.3725 6.71962 22.7995 9.17096 23.4577C11.1583 23.9705 13.2405 23.993 15.2385 23.5233C17.0484 23.1168 18.7218 22.2471 20.0947 20.9996C21.5236 19.6615 22.5608 17.9592 23.0947 16.0758C23.6751 14.0277 23.7783 11.8738 23.3966 9.77957H12.2366V14.4089H18.6997C18.5705 15.1473 18.2937 15.852 17.8859 16.4809C17.478 17.1098 16.9474 17.6499 16.326 18.0689C15.5367 18.591 14.6471 18.9423 13.7141 19.1002C12.7784 19.2742 11.8186 19.2742 10.8828 19.1002C9.93444 18.9041 9.03727 18.5127 8.24846 17.9508C6.98124 17.0538 6.02973 15.7794 5.52971 14.3096C5.02124 12.8122 5.02124 11.1888 5.52971 9.69144C5.88564 8.64185 6.47403 7.6862 7.25096 6.89582C8.14007 5.97472 9.26571 5.31631 10.5044 4.99284C11.7431 4.66936 13.0469 4.69331 14.2728 5.06207C15.2305 5.35605 16.1063 5.8697 16.8303 6.56207C17.5591 5.83707 18.2866 5.11019 19.0128 4.38144C19.3878 3.98957 19.7966 3.61644 20.166 3.21519C19.0608 2.18671 17.7635 1.38643 16.3485 0.860194C13.7717 -0.0754498 10.9522 -0.100594 8.35909 0.788944Z" fill="white"/>
            <path d="M8.35875 0.789855C10.9516 -0.100288 13.7711 -0.0758051 16.3481 0.85923C17.7634 1.38904 19.0601 2.19318 20.1638 3.22548C19.7888 3.62673 19.3931 4.00173 19.0106 4.39173C18.2831 5.11798 17.5562 5.84173 16.83 6.56298C16.106 5.87061 15.2302 5.35696 14.2725 5.06298C13.047 4.69293 11.7432 4.66759 10.5042 4.98975C9.26516 5.3119 8.13883 5.9691 7.24875 6.88923C6.47181 7.67961 5.88342 8.63526 5.5275 9.68486L1.64062 6.67548C3.03189 3.91653 5.44078 1.80615 8.35875 0.789855Z" fill="#E33629"/>
            <path d="M0.611401 9.65605C0.820316 8.62067 1.16716 7.61798 1.64265 6.6748L5.52953 9.69168C5.02105 11.1891 5.02105 12.8124 5.52953 14.3098C4.23453 15.3098 2.9389 16.3148 1.64265 17.3248C0.452308 14.9554 0.0892746 12.2557 0.611401 9.65605Z" fill="#F8BD00"/>
            <path d="M12.2391 9.77832H23.3991C23.7809 11.8726 23.6776 14.0264 23.0972 16.0746C22.5633 17.958 21.5261 19.6602 20.0972 20.9983C18.8429 20.0196 17.5829 19.0483 16.3285 18.0696C16.9504 17.6501 17.4812 17.1094 17.8891 16.4798C18.297 15.8503 18.5735 15.1448 18.7022 14.4058H12.2391C12.2372 12.8646 12.2391 11.3214 12.2391 9.77832Z" fill="#587DBD"/>
            <path d="M1.64062 17.3246C2.93688 16.3246 4.2325 15.3196 5.5275 14.3096C6.02851 15.7799 6.98138 17.0544 8.25 17.9508C9.04126 18.5101 9.94037 18.8983 10.89 19.0908C11.8257 19.2648 12.7855 19.2648 13.7213 19.0908C14.6542 18.9329 15.5439 18.5816 16.3331 18.0596C17.5875 19.0383 18.8475 20.0096 20.1019 20.9883C18.7292 22.2366 17.0558 23.1068 15.2456 23.5139C13.2476 23.9836 11.1655 23.9611 9.17813 23.4483C7.60632 23.0286 6.13814 22.2888 4.86563 21.2752C3.51874 20.2059 2.41867 18.8583 1.64062 17.3246Z" fill="#319F43"/>
            </g>
            <defs>
            <clipPath id="clip0_3079_2221">
            <rect width="24" height="24" fill="white"/>
            </clipPath>
            </defs>
            </svg>',
        'apple-pay' => '<svg width="24" height="28" viewBox="0 0 24 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . esc_attr($alt_text) . '">
            <path d="M19.9966 26.8766C18.4459 28.3542 16.7527 28.1209 15.1229 27.421C13.3981 26.7055 11.8157 26.6744 9.99598 27.421C7.71736 28.3853 6.51476 28.1053 5.15392 26.8766C-2.56807 19.0532 -1.42876 7.1391 7.3376 6.7036C9.4738 6.81247 10.9612 7.85456 12.2113 7.94789C14.0785 7.5746 15.8666 6.5014 17.8604 6.64138C20.2498 6.82803 22.0537 7.76124 23.2405 9.44103C18.3035 12.3496 19.4744 18.7421 24 20.5307C23.098 22.8638 21.9271 25.1813 19.9808 26.8922L19.9966 26.8766ZM12.0531 6.61028C11.8157 3.14183 14.6798 0.279965 17.9712 0C18.43 4.01283 14.2684 6.99912 12.0531 6.61028Z" fill="black"/>
            </svg>'
    );

    if (isset($svg_icons[$icon_name])) {
        // Define allowed HTML tags and attributes for SVG
        $kses_defaults = wp_kses_allowed_html('post');

        $svg_args = array(
            'svg' => array(
                'width' => true,
                'height' => true,
                'xmlns' => true,
                'viewbox' => true,
                'fill' => true,
                'class' => true,
                'role' => true,
                'aria-label' => true,
            ),
            'path' => array(
                'd' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
            ),
            'g' => array(
                'clip-path' => true,
            ),
            'defs' => array(),
            'clipPath' => array(
                'id' => true,
            ),
            'rect' => array(
                'width' => true,
                'height' => true,
                'fill' => true,
            ),
        );

        $allowed_tags = array_merge($kses_defaults, $svg_args);

        return sprintf(
            '<div class="lapinopay-payment-icon" role="img" aria-label="%s">%s</div>',
            esc_attr($alt_text),
            wp_kses($svg_icons[$icon_name], $allowed_tags)
        );
    }

    // Fallback if icon not found
    return sprintf(
        '<span class="lapinopay-payment-icon" aria-label="%s"></span>',
        esc_attr($alt_text)
    );
}
?>

<!-- Hidden select for form submission -->
<select name="lapinopay_payment_category" id="lapinopay_payment_category" class="select" style="display: none;"
    required>
    <option value="">Select a payment method</option>
    <option value="VISA_MC">Credit Card</option>
    <option value="REVOLUT_PAY">Revolut Pay</option>
    <option value="GOOGLE_PAY">Google Pay</option>
    <option value="APPLE_PAY">Apple Pay</option>
</select>

<div class="lapinopay-payment-container">
    <div class="lapinopay-security-badge">
        <?php echo wp_kses_post(lapinopay_get_payment_icon('shield-check', 'Security')); ?>
        <!-- <span>Secure Payment</span> -->
    </div>

    <div class="lapinopay-payment-methods">
        <div class="lapinopay-payment-method selected" data-method="credit-card">
            <input type="radio" name="lapinopay_payment_method" id="credit-card" value="credit-card" checked>
            <label for="credit-card">
                <div class="lapinopay-payment-method-icon">
                    <?php echo wp_kses_post(lapinopay_get_payment_icon('credit-card', 'Credit Card')); ?>
                </div>
                <div class="lapinopay-payment-method-info">
                    <div class="lapinopay-payment-method-name">Credit Card</div>
                    <div class="lapinopay-payment-method-description">Pay securely with your credit card</div>
                </div>
                <span class="lapinopay-radio-check"></span>
            </label>
        </div>

        <div class="lapinopay-payment-method" data-method="revolut">
            <input type="radio" name="lapinopay_payment_method" id="revolut" value="revolut">
            <label for="revolut">
                <div class="lapinopay-payment-method-icon">
                    <?php echo wp_kses_post(lapinopay_get_payment_icon('revolut', 'Revolut')); ?>
                </div>
                <div class="lapinopay-payment-method-info">
                    <div class="lapinopay-payment-method-name">Revolut Pay</div>
                    <div class="lapinopay-payment-method-description">Fast and secure payment with Revolut</div>
                </div>
                <span class="lapinopay-radio-check"></span>
            </label>
        </div>

        <div class="lapinopay-payment-method" data-method="apple-pay">
            <input type="radio" name="lapinopay_payment_method" id="apple-pay" value="apple-pay">
            <label for="apple-pay">
                <div class="lapinopay-payment-method-icon">
                    <?php echo wp_kses_post(lapinopay_get_payment_icon('apple-pay', 'Apple Pay')); ?>
                </div>
                <div class="lapinopay-payment-method-info">
                    <div class="lapinopay-payment-method-name">Apple Pay</div>
                    <div class="lapinopay-payment-method-description">Quick checkout with Apple Pay</div>
                </div>
                <span class="lapinopay-radio-check"></span>
            </label>
        </div>

        <div class="lapinopay-payment-method" data-method="google-pay">
            <input type="radio" name="lapinopay_payment_method" id="google-pay" value="google-pay">
            <label for="google-pay">
                <div class="lapinopay-payment-method-icon">
                    <?php echo wp_kses_post(lapinopay_get_payment_icon('google-pay', 'Google Pay')); ?>
                </div>
                <div class="lapinopay-payment-method-info">
                    <div class="lapinopay-payment-method-name">Google Pay</div>
                    <div class="lapinopay-payment-method-description">Easy payment with Google Pay</div>
                </div>
                <span class="lapinopay-radio-check"></span>
            </label>
        </div>
    </div>

    <div class="lapinopay-footer">
        Your personal data will be used to process your order, support your experience throughout this website, and for
        other purposes described in our <a target="_blank" href="https://www.lapinopay.com/privacy">Privacy
            policy</a>.
    </div>

    <button type="submit" class="lapinopay-place-order" id="lapinopay-place-order">
        Place order
    </button>
</div>
<script>
    jQuery(function ($) {
        // Get the hidden select element - Fix the selector to make sure we get the right element
        const payment_field_category = $('#lapinopay_payment_category');

        const paymentCategories = {
            'credit-card': 'VISA_MC',
            'revolut': 'REVOLUT_PAY',
            'google-pay': 'GOOGLE_PAY',
            'apple-pay': 'APPLE_PAY'
        };

        function updatePaymentCategory(selectedValue) {
            // Log for debugging
            console.log('Updating payment category to:', paymentCategories[selectedValue]);

            // Make sure we have the select element
            if (payment_field_category.length) {
                // Update the value and trigger change event
                payment_field_category.val(paymentCategories[selectedValue]).trigger('change');
            } else {
                console.error('Payment category select element not found');
            }
        }

        // Handle click on payment method container
        $(document).on('click', '.lapinopay-payment-method', function (e) {
            e.preventDefault();

            const radio = $(this).find('input[type="radio"]');
            if (radio.length) {
                // Update UI
                $('.lapinopay-payment-method').removeClass('selected');
                $(this).addClass('selected');

                // Update radio buttons
                $('.lapinopay-payment-method input[type="radio"]').prop('checked', false);
                radio.prop('checked', true);

                // Update hidden select
                updatePaymentCategory(radio.val());
            }
        });

        // Set initial value based on default selected radio
        const defaultSelected = $('.lapinopay-payment-method input[type="radio"]:checked');
        if (defaultSelected.length) {
            updatePaymentCategory(defaultSelected.val());
        }

        // Handle WooCommerce checkout updates
        $(document.body).on('updated_checkout', function () {
            console.log('Checkout updated, re-checking payment methods');
            const selectedRadio = $('.lapinopay-payment-method input[type="radio"]:checked');
            if (selectedRadio.length) {
                updatePaymentCategory(selectedRadio.val());
            }
        });

        // Add click handler for the place order button
        $('#lapinopay-place-order').on('click', function (e) {
            e.preventDefault();
            // Trigger the original place order button
            $('#place_order').trigger('click');
        });
    });
</script>