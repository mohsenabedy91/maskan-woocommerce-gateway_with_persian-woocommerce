<?php
/*
Plugin Name: درگاه پرداخت مسکن افزونه ووکامرس
Version: 1.0.0
Description:  درگاه پرداخت مسکن برای افزونه ووکامرس - این افزونه به صورت تجاری توسط گروه مهندسی راسا به فروش می رسد و هرگونه کپی برداری و اشتراک گذاری آن غیر مجاز می باشد.
Plugin URI: https://www.bank-maskan.ir/
Author: گروه مهندسی راسا
Author URI: https://rasagroups.com
*/

add_action( 'plugins_loaded', function () {

    if ( ! class_exists( 'Persian_Woocommerce_Gateways' ) ) {
        return add_action( 'admin_notices', function () { ?>
            <div class="notice notice-error">
                <p>برای استفاده از درگاه پرداخت مسکن ووکامرس باید ووکامرس پارسی 3.3.6 به بالا را نصب نمایید.</p>
            </div>
            <?php
        } );
    }

    include_once('class-gateway.php');
}, 999 );