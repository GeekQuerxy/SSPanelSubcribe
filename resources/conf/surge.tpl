#!MANAGED-CONFIG {$userapiUrl}

#---------------------------------------------------#
## 更新：{date("Y-m-d h:i:s")}
#---------------------------------------------------#

[General]
loglevel = notify
dns-server = system, 119.29.29.29, 223.6.6.6, 80.80.80.80
skip-proxy = 127.0.0.1, 192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12, 100.64.0.0/10, 17.0.0.0/8, localhost, *.local, *.crashlytics.com
external-controller-access = MixChina@0.0.0.0:8233
allow-wifi-access = true
enhanced-mode-by-rule = false
exclude-simple-hostnames = true
ipv6 = true
replica = false
{if $surge >= 3}
http-listen = 0.0.0.0:8234
socks5-listen = 0.0.0.0:8235
internet-test-url = http://baidu.com
proxy-test-url = http://bing.com
test-timeout = 3
{else}
interface = 0.0.0.0
socks-interface = 0.0.0.0
port = 8234
socks-port = 8235
{/if}

[Replica]
hide-apple-request = true
hide-crashlytics-request = true
hide-udp = false
use-keyword-filter = false

[Proxy]
🚀 Direct = direct
{$All_Proxy}

[Proxy Group]
{$ProxyGroups}

[Rule]
{if $surge >= 3}
RULE-SET,SYSTEM,🍎 Only
RULE-SET,https://github.com/lhie1/Rules/raw/master/Surge/Surge%203/Provider/Proxy.list,🍃 Proxy
RULE-SET,https://github.com/lhie1/Rules/raw/master/Surge/Surge%203/Provider/Domestic.list,🍂 Domestic
RULE-SET,LAN,DIRECT
{else}
{include file='rule/Apple.conf'}
{include file='rule/PROXY.conf'}
{include file='rule/DIRECT.conf'}
{/if}

GEOIP,CN,🍂 Domestic
FINAL,☁️ Others,dns-failed

[Host]
localhost = 127.0.0.1
syria.sy = 127.0.0.1

{literal}
[URL Rewrite]
// Google_Service_HTTPS_Jump
^https?:\/\/(www\.)?g\.cn https://www.google.com 302
^https?:\/\/(www\.)?google\.cn https://www.google.com 302

// Wiki
^https?:\/\/.+.(m\.)?wikipedia\.org/wiki http://www.wikiwand.com/en 302
^https?:\/\/zh.(m\.)?wikipedia\.org/(zh-hans|zh-sg|zh-cn|zh(?=/)) http://www.wikiwand.com/zh 302
^https?:\/\/zh.(m\.)?wikipedia\.org/zh-[a-zA-Z]{2,} http://www.wikiwand.com/zh-hant 302
{/literal}
[Header Rewrite]
^https?://www.zhihu.com/question/ header-replace User-Agent Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.45 Safari/537.36

[MITM]
skip-server-cert-verify = true
hostname = api.zhihu.com, www.zhihu.com
ca-passphrase = GeekQu
ca-p12 = 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
