# aliwe_pay
An Elegant Alipay &amp; Wechat Pay Gateway
实现支付宝与微信支付网关接口的3个文件

<br/>

基于开源支付网关项目[](https://github.com/dedemao/alipay)与[](https://github.com/dedemao/weixinPay)
<br/>
本项目包含了 Mysql 数据库的样例操作与微信支付流程的体验优化

<br/>

### 文件解析
+ 支付宝 Alipay
  + 涉及3个以 alipay 开头的文件
  + alipay.php
    + 接受订单号、金额信息(按照官方文档，有一种方法可以直接发送参数到支付宝再原样返回)
    + 发起订单请求跳转支付宝页面
  + alipay_notify.php
    + 支付宝订单异步文件
    + 处理数据录入
  + alipay_return.php
    + 支付宝支付成功跳转回商家网站对应文件
    + 根据返回的支付状态展示内容或提示

<br/>    

+ 微信支付 Wechat Pay
  + 涉及3个以 wechatpay 开头的文件
  + wechatpay.php
    + 接受订单号、金额信息(按照官方文档，有一种方法可以直接发送参数到支付宝再原样返回)
    + 发起订单请求生成二维码
    + 循环查询支付状态
    + 根据返回的支付状态展示内容或提示
  + wechatpay_notify.php
    + 微信支付订单异步文件
    + 处理数据录入
  + wechatpay_query.php
    + 接受订单号
    + 查询并返回支付状态
  
<br/>

### 食用方法
```
git clone git@github.com:HelipengTony/aliwe_pay.git
数据库及支付接口配置信息包含在了各个文件内
```
