<?php
header('Content-type:text/html; Charset=utf-8');
//支付宝公钥，账户中心->密钥管理->开放平台密钥，找到添加了支付功能的应用，根据你的加密类型，查看支付宝公钥
$alipayPublicKey='xxx';

$aliPay = new AlipayService($alipayPublicKey);
//验证签名
$result = $aliPay->rsaCheck($_POST,$_POST['sign_type']);
if($result===true){
    //处理你的逻辑，例如获取订单号$_POST['out_trade_no']，订单金额$_POST['total_amount']等
    
    
        /* 自定义部分 */
        $array = explode('a',$_POST['out_trade_no']); //分隔字符串
        
        if(strpos($_POST['out_trade_no'],"'")==false){
        
        //数据库连接信息配置
    	$url = 'localhost';
        $user = 'root';
        $passwd = 'xxxxxx';
        $con = mysqli_connect($url,$user,$passwd,'xxxxx',3306);
        
        
        /* 
            获取现有会员中止日期 
            首次开通则使用订单号$array[5]作为结束时间
            已开通则使用计算过后的$current_time作为结束时间
        */
        $sql1='SELECT mem_duration FROM snap_payment_user WHERE user_id = "'.$array[2].'" AND post_id = "VIP"';
        $current=mysqli_query($con,$sql1);
        $current_duration=mysqli_fetch_array($current,MYSQLI_ASSOC);
        mysqli_free_result($current);
        
        
        /* 获取现有会员支付金额 */
        $sql2='SELECT amount FROM snap_payment_user WHERE user_id ="'.$array[2].'" AND post_id = "VIP"';
        $current2=mysqli_query($con,$sql2);
        $current_amount=mysqli_fetch_array($current2,MYSQLI_ASSOC);
        mysqli_free_result($current2);
        
        
        
        //获取续费要增加的日期长度
        switch ($array[5]){
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
                $add_time = '+0month';
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
        
        
        
        /* 会员订单 */
        if(!empty($array[4]) && !empty($array[5])){
            
            if(!empty($current_duration['mem_duration'])){ //若存在终止日期，则更新
                $sql = 'UPDATE snap_payment_user SET mem_duration="'.$current_time.'",mem_type="'.$array[5].'",amount="'.((float)$_POST['total_amount'] + (float)$current_amount['amount'] ).'" WHERE user_id="'.$array[2].'" AND post_id = "VIP"';
            }else{
                //首次开通
                $sql = 'insert into snap_payment_user (post_id,user_id,amount,timenum,mem_duration,mem_type) values("VIP","'.$array[2].'","'.(float)$_POST['total_amount'].'","'.$array[0].'","'.$array[4].'","'.$array[5].'")'; //保存数据库，插入新数据
            }
            
        }else{
        /* 会员订单结束 */
        
        
        /* 文章订单 */
            $sql = 'insert into snap_payment_user (post_id,user_id,amount,timenum) values("'.(string)$array[1].'","'.$array[2].'","'.(float)$_POST['total_amount'].'","'.$array[0].'")'; //保存数据库，插入新数据
        /* 文章订单结束 */
        
        
        }
        
        //出错则设置没卵用的cookie
        if(!mysqli_query($con,$sql)){
            setcookie('snapaperpaymenterror','mysql', time() + 3600 * 1);
        }
        
        //在另一表中记录支付记录
        $sql3 = 'insert into snap_payment_record (user_id,pay_amount,pay_time,pay_way) values("'.$array[2].'","'.(float)$_POST['total_amount'].'","'.$array[0].'","alipay")'; //保存数据库，插入新数据
        mysqli_query($con,$sql3);
        mysqli_close($con);
        /* 自定义部分 */
            
            
            
            
        echo 'success';exit(); //必须输出一个'success'，否则支付宝会不断请求此文件直到响应
}else{
    echo 'error';exit();
}}
class AlipayService
{
    //支付宝公钥
    protected $alipayPublicKey;
    protected $charset;

    public function __construct($alipayPublicKey)
    {
        $this->charset = 'utf8';
        $this->alipayPublicKey=$alipayPublicKey;
    }

    /**
     *  验证签名
     **/
    public function rsaCheck($params) {
        $sign = $params['sign'];
        $signType = $params['sign_type'];
        unset($params['sign_type']);
        unset($params['sign']);
        return $this->verify($this->getSignContent($params), $sign, $signType);
    }

    function verify($data, $sign, $signType = 'RSA') {
        $pubKey= $this->alipayPublicKey;
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($pubKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        ($res) or die('支付宝RSA公钥错误。请检查公钥文件格式是否正确');

        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }
//        if(!$this->checkEmpty($this->alipayPublicKey)) {
//            //释放资源
//            openssl_free_key($res);
//        }
        return $result;
    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }

    public function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = $this->characet($v, $this->charset);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }
}