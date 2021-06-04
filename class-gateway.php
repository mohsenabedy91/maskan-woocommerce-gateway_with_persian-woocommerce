<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woocommerce_Ir_Gateway_Maskan' ) ) :

Persian_Woocommerce_Gateways::register( 'Maskan' );

class Woocommerce_Ir_Gateway_Maskan extends Persian_Woocommerce_Gateways {

	public function __construct() {

		$this->method_title = 'مسکن';

		parent::init( $this );
	}

	public function fields() {
		return array(
			'terminal' => array(
				'title'       => 'ترمینال آیدی',
				'type'        => 'text',
				'description' => 'شماره ترمینال درگاه بانک مسکن',
				'default'     => '',
				'desc_tip'    => true
			),
			'username' => array(
				'title'       => 'نام کاربری',
				'type'        => 'text',
                'description' => 'نام کاربری درگاه بانک مسکن',
				'default'     => '',
				'desc_tip'    => true
			),
			'password' => array(
				'title'       => 'کلمه عبور',
				'type'        => 'text',
				'description' => 'کلمه عبور درگاه بانک مسکن',
				'default'     => '',
                'desc_tip'    => true
			),
			'cancelled_massage' => array(),
            'shortcodes'        => array(
                'transactionReferenceID' => 'کد شماره ارجاع داخلی (جهت پیگیری در سایت پرداخت اینترنتی یا همان کد رهگیری)',
                'traceNumber'            => 'شماره پیگیری',
                'referenceNumber'        => 'شماره ارجاع (جهت پیگیری در شعب بانک)',
            )
		);
	}

	public function request( $order ) {

		if ( ! extension_loaded( 'curl' ) ) {
			return 'تابع curl روی هاست فروشنده فعال نیست و امکان پرداخت وجود ندارد.';
		}

        $parameters = array(
            "CARDACCEPTORCODE"  => $this->option( 'terminal' ),
            "USERNAME"          => $this->option( 'username' ),
            "USERPASSWORD"      => $this->option( 'password' ),
            "AMOUNT"            => $this->get_total( 'IRR' ),
            "CALLBACKURL"       => $this->get_verify_url(),
            "PAYMENTID"         => date( 'Hmyids' ),
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($parameters));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, "http://79.174.161.132:8181/NvcService/Api/v2/PayRequest");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Accept: application/json"));
        $result = curl_exec($curl);
        curl_close($curl);


        $confirm = json_decode($result, false);
        $ActionCode = strval($confirm->ActionCode);
        $RedirectUrl = strval($confirm->RedirectUrl);

        if ($ActionCode == 0)
        {
            header("Location: ".$RedirectUrl);
            exit;
        }
        return $this->Fault_BankMaskan($ActionCode);
	}

	public function verify( $order ) {

        global $woocommerce;
        if ( isset($_GET['wc_order']) )
            $order_id = $_GET['wc_order'];
        else
            $order_id = $woocommerce->session->order_id_bankMaskan;
        $error = '';
        if ( $order_id ) {
            $order = new WC_Order($order_id);
            if($order->status !='completed'){

                $json = stripslashes($_POST['Data']);
                $Res = json_decode($json);

                $transaction_id = strval($Res->RRN);
                $orderId = strval($Res->PaymentID);
                $fault = strval($Res->ActionCode);
                $TraceNumber = strval($Res->MessageNumber);
	            $TransactionReferenceID = $transaction_id;
	            $ReferenceNumber = $orderId;
                update_post_meta( $order_id, 'WC_BankMaskan_settleSaleOrderId', $transaction_id );

                if ($fault == "511" || $fault == "519")
                {
                    $status = 'cancelled';
                    $error = $this->Fault_BankMaskan($fault);
                }
                else if ($fault != "0")
                {
                    $status = 'failed';
	                $error = $this->Fault_BankMaskan($fault);
                }
                else
                {
                    $parameters = array(
                        "CARDACCEPTORCODE"  => $this->option( 'terminal' ),
                        "USERNAME"          => $this->option( 'username' ),
                        "USERPASSWORD"      => $this->option( 'password' ),
                        "RRN"               => $transaction_id,
                        "PAYMENTID"         => $orderId,
                    );

                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($parameters));
	                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_URL, "http://79.174.161.132:8181/NvcService/Api/v2/Confirm");
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Accept: application/json"));
                    $result = curl_exec($curl);
                    curl_close($curl);
                    $result = iconv('UTF-8', 'UTF-8//IGNORE', utf8_encode($result));

                    $confirm = json_decode($result, false);
                    $ActionCode = strval($confirm->ActionCode);

                    if (json_last_error() != 0 || $confirm == null || $ActionCode == null)
                    {
                        $status = 'failed';
	                    $error = $this->Fault_BankMaskan($ActionCode);
                    }
                    else
                    {
                        if ($ActionCode == "511" || $ActionCode == "519")
                        {
                            $status = 'cancelled';
                            $error = $this->Fault_BankMaskan($ActionCode);
                        }
                        else if ($ActionCode != "0")
                        {
                            $status = 'failed';
                            $error = $this->Fault_BankMaskan($ActionCode);
                        }
                        else
                        {
                            $status = 'completed';
                        }
                    }
                }

	            $transaction_id = $TransactionReferenceID;
	            $transaction_id = ! empty( $transaction_id ) ? $transaction_id : $ReferenceNumber;
	            $transaction_id = ! empty( $transaction_id ) ? $transaction_id : $TraceNumber;

	            $this->set_shortcodes( array(
		            'transactionReferenceID' => $TransactionReferenceID,
		            'traceNumber'            => $TraceNumber,
		            'referenceNumber'        => $ReferenceNumber
	            ) );

	            return compact( 'status', 'transaction_id', 'error' );
            }
        }
	}

    private static function Fault_BankMaskan($err_code){

        if ($err_code == "settle"){
            return __("عملیات Settel دستی با موفقیت انجام شد .", "woocommerce" );
        }

        switch(intval($err_code)){
            case -2:
                return  __("شکست در ارتباط با بانک .", "woocommerce" );
            case -1:
                return  __("شکست در ارتباط با بانک .", "woocommerce" );
            case 0:
                return  __("تراکنش با موفقیت انجام شد .", "woocommerce" );
            case 1:
                return  __("عملیات ناموفق.", "woocommerce" );
            case 2:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 3:
                return  __("تراکنش نامعتبر است.", "woocommerce" );
            case 5:
                return  __("تراکنش نامعتبر است.", "woocommerce" );
            case 6:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 7:
                return  __("کارت نامعتبر است", "woocommerce" );
            case 8:
                return  __("باتشخیص هویت دارنده ی کارت، موفق می باشد.", "woocommerce" );
            case 9:
                return  __("متاسفانه خطایی در سرور رخ داده است", "woocommerce" );
            case 12:
                return  __("تراکنش نامعتبر است", "woocommerce" );
            case 13:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 14:
                return  __("شماره کارت ارسالی نامعتبر است. (وجود ندارد).", "woocommerce" );
            case 15:
                return  __("صادرکننده ی کارت نامعتبراست. (وجود ندارد)", "woocommerce" );
            case 16:
                return  __("تراکنش مورد تایید است و اطلاعات شیار سوم کارت به روز رسانی شود.", "woocommerce" );
            case 19:
                return  __("عملیات ناموفق", "woocommerce" );
            case 20:
                return  __("خطا در برقراری ارتباط", "woocommerce" );
            case 23:
                return  __("تراکنش نامعتبر است.", "woocommerce" );
            case 25:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 30:
                return  __("تراکنش نامعتبر است.", "woocommerce" );
            case 31:
                return  __("پذیرنده توسط سوئیچ پشتیبانی نمی شود.", "woocommerce" );
            case 33:
                return  __("تاریخ انقضای کارت سپری شده است.", "woocommerce" );
            case 34:
                return  __("عملیات ناموفق.", "woocommerce" );
            case 36:
                return  __("کارت نامعتبر است", "woocommerce" );
            case 38:
                return  __("تعداد دفعات ورود رمز غلط بیش از حد مجاز است.", "woocommerce" );
            case 39:
                return  __("کارت حساب اعتباری ندارد.", "woocommerce" );
            case 40:
                return  __("عملیات ناموفق.", "woocommerce" );
            case 41:
                return  __("کارت مفقودی میباشد.", "woocommerce" );
            case 42:
                return  __("کارت حساب عمومی ندارد.", "woocommerce" );
            case 43:
                return  __("عملیات ناموفق.", "woocommerce" );
            case 44:
                return  __("کارت حساب سرمایه گذاری ندارد.", "woocommerce" );
            case 48:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 51:
                return  __("موجودی کافی نیست.", "woocommerce" );
            case 52:
                return  __("کارت حساب جاری ندارد.", "woocommerce" );
            case 53:
                return  __("کارت حساب قرض الحسنه ندارد.", "woocommerce" );
            case 54:
                return  __("تاریخ انقضای کارت سپری شده است.", "woocommerce" );
            case 55:
                return  __("رمز کارت نامعتبر است.", "woocommerce" );
            case 56:
                return  __("کارت نا معتبر است.", "woocommerce" );
            case 57:
                return  __("تراکنش نامعتبر است.", "woocommerce" );
            case 58:
                return  __("تراکنش نامعتبر است.", "woocommerce" );
            case 59:
                return  __("کارت نامعتبر است", "woocommerce" );
            case 61:
                return  __("مبلغ تراکنش بیش از حد مجاز است.", "woocommerce" );
            case 62:
                return  __("کارت محدود شده است.", "woocommerce" );
            case 63:
                return  __("عملیات ناموفق.", "woocommerce" );
            case 64:
                return  __("تراکنش نا معتبر است", "woocommerce" );
            case 65:
                return  __("تعداد درخواست تراکنش بیش از حد مجاز است.", "woocommerce" );
            case 67:
                return  __("کارت توسط دستگاه ضبط شود.", "woocommerce" );
            case 75:
                return  __("تعداد دفعات ورود رمزغلط بیش از حد مجاز است.", "woocommerce" );
            case 77:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 78:
                return  __("کارت فعال نیست.", "woocommerce" );
            case 79:
                return  __("حساب متصل به کارت نامعتبر است یا دارای اشکال است.", "woocommerce" );
            case 80:
                return  __("تراکنش موفق عمل نکرده است.", "woocommerce" );
            case 81:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 83:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 84:
                return  __("وضعیت سامانه یا بانک مقصد تراکنش غیرفعال می باشد. (Host Down)", "woocommerce" );
            case 86:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 90:
                return  __("عملیات ناموفق.", "woocommerce" );
            case 91:
                return  __("صادر کننده یا سوییچ مقصد فعال نمی باشد.", "woocommerce" );
            case 92:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 93:
                return  __("خطا در برقراری ارتباط.", "woocommerce" );
            case 94:
                return  __("ارسال تراکنش تکراری", "woocommerce" );
            case 96:
                return  __("بروز خطای سیستمی در انجام تراکنش", "woocommerce" );
            case 97:
                return  __("فرآیند تغییر کلید برای صادر کننده یا پذیرنده در حال انجام است.", "woocommerce" );
            case 98:
                return  __(".شارژ مورد نظر موجود نیست", "woocommerce" );
            case 99:
                return  __("بروزرسانی کلیدهای پایانه", "woocommerce" );
            case 100:
                return  __("خطا در برقراری ارتباط", "woocommerce" );
            case 500:
                return  __("کدپذیرندگی معتبر نمی باشد", "woocommerce" );
            case 501:
                return  __("مبلغ بیشتر از حد مجاز است", "woocommerce" );
            case 502:
                return  __("نام کاربری و یا رمز ورود اشتباه است", "woocommerce" );
            case 503:
                return  __("آی پی دامنه کار بر نا معتبر است", "woocommerce" );
            case 504:
                return  __("آدرس صفحه برگشت نا معتبر است", "woocommerce" );
            case 505:
                return  __("خطای نا معلوم", "woocommerce" );
            case 506:
                return  __("شماره سفارش تکراری است -  و یا مشکلی دیگر در درج اطلاعات", "woocommerce" );
            case 507:
                return  __("اعتبار ستجی مقادیر با خطا مواجه شده", "woocommerce" );
            case 508:
                return  __("فرمت درخواست ارسالی نا معتبر است", "woocommerce" );
            case 509:
                return  __("از سرویس سوئیچ پاسخی بازنگشت", "woocommerce" );
            case 510:
                return  __("مشتری منصرف شده است", "woocommerce" );
            case 511:
                return  __("زمان انجام تراکنش به پایان رسیده", "woocommerce" );
            case 512:
                return  __("نامعتبر است Cvv2", "woocommerce" );
            case 513:
                return  __("تاریخ انقضاء کارت نامعتبر است", "woocommerce" );
            case 514:
                return  __("پست الکترونیک نا معتبر است", "woocommerce" );
            case 515:
                return  __("حروف امنیتی اشتباه وارد شده است", "woocommerce" );
            case 516:
                return  __("اطلاعات درخواست نامعتبر میباشد", "woocommerce" );
            case 517:
                return  __("شماره کارت وارد شده صحیح نمیباشد", "woocommerce" );
            case 518:
                return  __("تراکنش یافت نشد", "woocommerce" );
            case 519:
                return  __("مشتری از پرداخت منصرف شده است", "woocommerce" );
            case 520:
                return  __("مشتری در زمان مقرر پرداخت را انجام نداده است", "woocommerce" );
            case 521:
                return  __(".قبلا درخواست تائید با موفقیت ثبت شده است", "woocommerce" );
            case 522:
                return  __(".قبلا درخواست اصلاح تراکنش با موفقیت ثبت شده است", "woocommerce" );
            case 600:
                return  __("لغو تراکنش", "woocommerce" );
        }
        return __("در حین پرداخت خطای سیستمی رخ داده است .", "woocommerce" );
    }
}
endif;