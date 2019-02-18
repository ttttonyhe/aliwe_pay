<?php
header('Content-type:text/html; Charset=utf-8');
/*

    请搭配wechatpay_query.php食用
    生成二维码后不间断查询支付状态来判断支付是否成功

*/

$current_user_id = $_POST['user'];
$current_post_id = (string)$_POST['post'];

$mchid = 'xxx';          //微信支付商户号 PartnerID 通过微信支付商户资料审核后邮件发送
$appid = 'xxxxx';  //公众号APPID 通过微信支付商户资料审核后邮件发送
$apiKey = 'xxxxx';   //https://pay.weixin.qq.com 帐户设置-安全设置-API安全-API密钥-设置API密钥
$wxPay = new WxpayService($mchid,$appid,$apiKey);

/* 自定义部分 */
if(!empty($_POST['duration']) && $_POST['mem_type'] != 0){
    $current_mem_duration = $_POST['duration'];
    $outTradeNo = time().'a'.$current_post_id.'a'.$current_user_id.'a'.$current_mem_duration.'a'.$_POST['mem_type'].'a'.rand(0,9);
    $url_return = 'https://platform.snapaper.com/membership?pay_return=1';
}else{
    $outTradeNo = time().'a'.$current_post_id.'a'.$current_user_id.'a'.rand(0,9);     //你自己的商品订单号，不能重复
    $url_return = 'https://platform.snapaper.com/'.$current_post_id.'?pay_return=1';
}
/* 自定义部分 */

$payAmount = $_POST['amount'];          //付款金额，单位:元
$orderName = $_POST['order_name'];    //订单标题
$notifyUrl = 'https://platform.snapaper.com/wechatpay_notify.php';     //付款成功后的回调地址(不要有问号)
$payTime = time();      //付款时间
$arr = $wxPay->createJsBizPackage($payAmount,$outTradeNo,$orderName,$notifyUrl,$payTime);
//生成二维码
$url = 'https://www.kuaizhan.com/common/encode-png?large=true&data='.$arr['code_url'];


/* 自定义扫码展示部分 */
echo "
<title>WeChat Pay Checking Out | Snapaper</title>
<link href='https://static.zeo.im/uikit.min.css' rel='stylesheet'>
<script src='https://static.ouorz.com/jquery.min.js'></script>


<div class='uk-container'>
    <div class='uk-child-width-1-3@m'>
        <div style='margin: 10vh auto;'>
            <div class='uk-card uk-card-default'>
                <div class='uk-card-media-top'>
                    <img src='{$url}' style='width:100%'>
                </div>
                <div class='uk-card-body'>
                <h3 class='uk-card-title' style='margin-bottom: 0px;font-size: 2.3rem;font-weight: 600;'>WeChat Pay</h3>
                <p style='margin-top: 10px;'>Please Use WeChat App to Scan the QR Code and Pay</p><p style='background: #23d393;padding: 8px 20px;border-radius: 4px;text-align: center;color: #fff;letter-spacing: .5px;' id='status_notice'></p>
            </div>
            </div>
        </div>
    </div>
</div>

<script>
    function url(){
        window.location.href = '".$url_return."';
    }
    
    var get_payment_status = function(){
        var order_string = '".$outTradeNo."';
        jQuery.ajax({
        type:     'GET'
        ,url:     '/wechatpay_query.php?order='+order_string
        ,cache:    false
        ,dataType:  'json'
        ,contentType: 'application/json; charset=utf-8'
        ,success:   function(back){
                if(back.data[0] == 'SUCCESS'){
                    var change = document.getElementById('status_notice');
                    change.innerHTML = 'Payment has been completed';
                    setTimeout('url()',2500);
                }
        }
        ,error:    function(back){
             if(back.data[0] == 'SUCCESS'){
                    var change = document.getElementById('status_notice');
                    change.innerHTML = 'Payment has been completed';
                    setTimeout('url()',2500);
             }
         }
    });
    }
    var status_interval = setInterval(get_payment_status,700);
</script>";

/* 自定义扫码展示部分 */


class WxpayService
{
    protected $mchid;
    protected $appid;
    protected $apiKey;
    public function __construct($mchid, $appid, $key)
    {
        $this->mchid = $mchid;
        $this->appid = $appid;
        $this->apiKey = $key;
    }
    /**
     * 发起订单
     * @param float $totalFee 收款总费用 单位元
     * @param string $outTradeNo 唯一的订单号
     * @param string $orderName 订单名称
     * @param string $notifyUrl 支付结果通知url 不要有问号
     * @param string $timestamp 订单发起时间
     * @return array
     */
    public function createJsBizPackage($totalFee, $outTradeNo, $orderName, $notifyUrl, $timestamp)
    {
        $config = array(
            'mch_id' => $this->mchid,
            'appid' => $this->appid,
            'key' => $this->apiKey,
        );
        //$orderName = iconv('GBK','UTF-8',$orderName);
        $unified = array(
            'appid' => $config['appid'],
            'attach' => 'pay',             //商家数据包，原样返回，如果填写中文，请注意转换为utf-8
            'body' => $orderName,
            'mch_id' => $config['mch_id'],
            'nonce_str' => self::createNonceStr(),
            'notify_url' => $notifyUrl,
            'out_trade_no' => $outTradeNo,
            'spbill_create_ip' => '127.0.0.1',
            'total_fee' => intval($totalFee * 100),       //单位 转为分
            'trade_type' => 'NATIVE',
        );
        $unified['sign'] = self::getSign($unified, $config['key']);
        $responseXml = self::curlPost('https://api.mch.weixin.qq.com/pay/unifiedorder', self::arrayToXml($unified));
		//禁止引用外部xml实体
		libxml_disable_entity_loader(true);        
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($unifiedOrder === false) {
            die('parse xml error');
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            die($unifiedOrder->return_msg);
        }
        if ($unifiedOrder->result_code != 'SUCCESS') {
            die($unifiedOrder->err_code);
        }
        $codeUrl = (array)($unifiedOrder->code_url);
        if(!$codeUrl[0]) exit('get code_url error');
        $arr = array(
            "appId" => $config['appid'],
            "timeStamp" => $timestamp,
            "nonceStr" => self::createNonceStr(),
            "package" => "prepay_id=" . $unifiedOrder->prepay_id,
            "signType" => 'MD5',
            "code_url" => $codeUrl[0],
        );
        $arr['paySign'] = self::getSign($arr, $config['key']);
        return $arr;
    }
    public function notify()
    {
        $config = array(
            'mch_id' => $this->mchid,
            'appid' => $this->appid,
            'key' => $this->apiKey,
        );
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($postObj === false) {
            die('parse xml error');
        }
        if ($postObj->return_code != 'SUCCESS') {
            die($postObj->return_msg);
        }
        if ($postObj->result_code != 'SUCCESS') {
            die($postObj->err_code);
        }
        $arr = (array)$postObj;
        unset($arr['sign']);
        if (self::getSign($arr, $config['key']) == $postObj->sign) {
            echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            return $postObj;
        }
    }
    /**
     * curl get
     *
     * @param string $url
     * @param array $options
     * @return mixed
     */
    public static function curlGet($url = '', $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    public static function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    public static function createNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    public static function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }
    /**
     * 获取签名
     */
    public static function getSign($params, $key)
    {
        ksort($params, SORT_STRING);
        $unSignParaString = self::formatQueryParaMap($params, false);
        $signStr = strtoupper(md5($unSignParaString . "&key=" . $key));
        return $signStr;
    }
    protected static function formatQueryParaMap($paraMap, $urlEncode = false)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if (null != $v && "null" != $v) {
                if ($urlEncode) {
                    $v = urlencode($v);
                }
                $buff .= $k . "=" . $v . "&";
            }
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }
}
