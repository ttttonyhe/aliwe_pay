# aliwe_pay
An Elegant Alipay &amp; Wechat Pay Gateway
<br/>
[中文README](https://www.ouorz.com/288)
<br/>
3 files to build a payment gateway that works well

<br/>

Based on open sourced payment gateway project [Alipay](https://github.com/dedemao/alipay) and [WeixinPay](https://github.com/dedemao/weixinPay)
<br/>
This project includes sample codes for Mysql database operations and user experience optimization of WeChat payment process.

<br/>

![gateway](https://i.loli.net/2019/02/18/5c6a34f654d70.png)

Sample codes are from [Snapaper Platform](https://platform.snapaper.com)，enable：
1. Paid articles purchase
2. Paid videos purchase
3. User data modifications
4. Memberships purchase
5. More...

<br/>

### Files Explanations
+ **Alipay**
  + 3 files begin with 'alipay'
  + alipay.php
    + Generate the order number and payment amount information (according to the official documentation, there is a way to send the parameters directly to Alipay and return as they are)
    + Generate an order request and jump to the Alipay payment page
  + alipay_notify.php
    + Alipay asynchronous file
    + Process payment & user data
  + alipay_return.php
    + Jump to this file after completing a payment
    + Show content or tips based on returned payment status

<br/>    

+ **Wechat Pay**
  + 3 files begin with 'wechatpay'
  + wechatpay.php
    + Generate the order number and payment amount information
    + Generate a QR code
    + Keep checking the payment status
    + Show content or tips based on returned payment status
  + wechatpay_notify.php
    + Wechatpay asynchronous file
    + Process payment & user data
  + wechatpay_query.php
    + Receive the order number using POST request
    + Check the payment status and return
  
<br/>

![wechatpay](https://i.loli.net/2019/02/18/5c6a34f6519c4.png)

### Usage
```
git clone git@github.com:HelipengTony/aliwe_pay.git

Database and payment interface configuration are included in files
```
