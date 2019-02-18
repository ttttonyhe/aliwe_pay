<?php
/**
 * 原生支付（扫码支付）及公众号支付的异步回调通知
 * 说明：需要在native.php或者jsapi.php中的填写回调地址。例如：http://www.xxx.com/wx/notify.php
 * 付款成功后，微信服务器会将付款结果通知到该页面
 */
header('Content-type:text/html; Charset=utf-8');
$mchid = 'xxx';          //微信支付商户号 PartnerID 通过微信支付商户资料审核后邮件发送
$appid = 'xxxx';  //公众号APPID 通过微信支付商户资料审核后邮件发送
$apiKey = 'xxxx';   //https://pay.weixin.qq.com 帐户设置-安全设置-API安全-API密钥-设置API密钥
$wxPay = new WxpayService($mchid,$appid,$apiKey);
$result = $wxPay->notify();
if($result){
    
    
    if(strpos($result['out_trade_no'],"'")==false){
    
    /* 自定义部分 */
        
    //完成你的逻辑
    //例如连接数据库，获取付款金额$result['cash_fee']，获取订单号$result['out_trade_no']，修改数据库中的订单状态等;
        
    	$url = 'localhost';
        $user = 'root';
        $passwd = 'xxxxxx';
        $con = mysqli_connect($url,$user,$passwd,'xxxxxx',3306);
        
        $array = explode('a',$result['out_trade_no']); //分隔字符串
        
        
        /* 获取现有会员中止日期 */
        $sql1='SELECT mem_duration FROM snap_payment_user WHERE user_id ="'.$array[2].'" AND post_id = "VIP"';
        $current1=mysqli_query($con,$sql1);
        $current_duration=mysqli_fetch_array($current1,MYSQLI_ASSOC);
        mysqli_free_result($current1);
        
        /* 获取现有会员支付金额 */
        $sql2='SELECT amount FROM snap_payment_user WHERE user_id ="'.$array[2].'" AND post_id = "VIP"';
        $current2=mysqli_query($con,$sql2);
        $current_amount=mysqli_fetch_array($current2,MYSQLI_ASSOC);
        mysqli_free_result($current2);
        
        //获取续费要增加的日期长度
        switch ($array[4]){
            case 1:
                $add_time = '+31day';
                break;
            case 2:
                $add_time = '+180day';
                break;
            case 3:
                $add_time = '+365day';
                break;
            default:
                $add_time = '+0day';
                break;
        }
        
        //判断会员是否已过期(存在曾经的记录并且记录显示已过期)
        if(!empty($current_duration['mem_duration']) && (int)$current_duration['mem_duration'] < (int)time()){
            //已过期
            $current_time = strtotime($add_time,time()); //从现在时间开始续费
        }elseif(!empty($current_duration['mem_duration'])){
            //未过期
            $current_time = strtotime($add_time,$current_duration['mem_duration']);
            //直接增加中止时长
        }
        
        /* 获取现有会员中止日期结束 */
        
        //微信无法将浮点数加入订单号，获取微信返回的支付金额(单位：分)
        $amount_paid = (float)$result['cash_fee'] / 100;
        
        /* 会员订单 */
        if(!empty($array[3]) && !empty($array[4])){
            
            if(!empty($current_duration['mem_duration'])){ //若存在终止日期，则更新
                $sql = 'UPDATE snap_payment_user SET mem_duration="'.$current_time.'",mem_type="'.$array[4].'",amount="'.((float)$amount_paid + (float)$current_amount['amount'] ).'" WHERE user_id="'.$array[2].'" AND post_id = "VIP"';
            }else{
                //首次开通
                $sql = 'insert into snap_payment_user (post_id,user_id,amount,timenum,mem_duration,mem_type) values("VIP","'.$array[2].'","'.(float)$amount_paid.'","'.$array[0].'","'.$array[3].'","'.$array[4].'")'; //保存数据库，插入新数据
            }
            
        }else{
        /* 会员订单结束 */
        
        
        /* 文章订单 */
            $sql = 'insert into snap_payment_user (post_id,user_id,amount,timenum) values("'.(string)$array[1].'","'.$array[2].'","'.(float)$amount_paid.'","'.$array[0].'")'; //保存数据库，插入新数据
        /* 文章订单结束 */
        
        
        }
        
        if(!mysqli_query($con,$sql)){
            setcookie('snapaperpaymenterror','mysql', time() + 3600 * 1);
        }
        
        //在另一表中记录支付记录
        $sql3 = 'insert into snap_payment_record (user_id,pay_amount,pay_time,pay_way) values("'.$array[2].'","'.(float)$amount_paid.'","'.$array[0].'","wechat")'; //保存数据库，插入新数据
        mysqli_query($con,$sql3);
        
        mysqli_close($con);
        
        /* 自定义部分 */
        
        
        
        
        
        
        
        
}else{
    echo 'pay error';
}
}else{
    echo 'pay error';
}
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

    public function notify()
    {
        $config = array(
            'mch_id' => $this->mchid,
            'appid' => $this->appid,
            'key' => $this->apiKey,
        );
        $postStr = file_get_contents('php://input');
		//禁止引用外部xml实体
		libxml_disable_entity_loader(true);        
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
            return $arr;
        }
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
