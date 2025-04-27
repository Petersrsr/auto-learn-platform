# 全平台网课代刷平台

这是一个在线网课代刷平台，用户可以查询并提交订单以进行网课的代刷服务。该平台支持不同平台的课程查询和订单提交。

## 项目结构

. ├── 易支付 │ ├── cx.petersr.xyz │ │ ├── index.html # 网课代挂下单页面 │ │ ├── logo.png # 网站Logo │ │ └── cx.html # 网课代挂业务页面 │ ├── gpt │ │ ├── index.html # GPT相关功能页面 │ │ ├── alipay-qrcode.png # 支付宝二维码 │ │ └── wechat-qrcode.png # 微信二维码 │ ├── petersr.xyz │ │ ├── cx.html # 网课代挂业务页面 │ │ ├── index.html # 首页 │ │ ├── logo.png # 网站Logo │ │ ├── wechat-group-qrcode.png # 微信群二维码 │ │ └── alipay-qrcode.png # 支付宝二维码 ├── 易支付.zip # 压缩文件 ├── index1.html # 其他页面 ├── index2.html # 其他页面 └── pay.html # 支付页面

markdown
复制

## 功能

1. **网课查询**：通过提供学校、账号、密码信息，用户可以查询相关课程。
2. **网课代挂**：用户可以选择查询到的课程并提交订单进行代刷。
3. **支付功能**：支持支付宝和微信支付二维码生成，用户可以通过扫码支付。
4. **平台支持**：支持多个平台的课程查询和订单提交。

## 使用方法

### 1. 克隆项目

```bash
git clone https://github.com/Petersrsr/auto-learn-platform.git
2. 安装依赖（如有）
如果你使用了外部依赖，可以使用以下命令安装它们：

bash
复制
npm install
3. 启动项目
你可以直接打开 index.html 文件在浏览器中访问，或者通过本地服务器来运行：

bash
复制
npm start
4. 使用说明
查询课程：在 在线下单 页面中填写学校、账号、密码信息，然后点击“查课”按钮进行课程查询。

选择课程：在查询结果中，勾选你想要刷的课程。

提交订单：选择课程后，点击“提交订单”按钮开始刷课。

API接口
查询课程接口
URL: http://www.xn--zoxz78b5fn.cn/api.php?act=get

请求参数
uid: 固定为 4

key: 固定为 dgZIiIn72IAN1IIa

platform: 固定为 3

user: 用户的学号或账号

pass: 用户的密码

school: 用户的学校名称

提交订单接口
URL: http://www.xn--zoxz78b5fn.cn/api.php?act=add

请求参数
uid: 固定为 4

key: 固定为 dgZIiIn72IAN1IIa

platform: 固定为 3

school: 用户填写的学校

user: 用户填写的账号

pass: 用户填写的密码

kcname: 用户选择的课程名称（没有课程ID时）

kcid: 需要课程ID时填写（如课程名后有ID）

开发与贡献
前端部分：HTML, CSS, JavaScript

后端部分：与小储云平台的API对接，提交课程查询和订单数据。

注意事项
在使用本平台之前，确保输入的账号、密码和学校信息是正确的。

如果查询不到课程，请确认账号信息无误，或者联系客服。

License
MIT License - 全平台网课代刷平台
