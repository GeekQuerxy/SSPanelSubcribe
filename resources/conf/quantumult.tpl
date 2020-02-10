[SERVER]
{$All_Proxy}

[POLICY]
{$ProxyGroups['proxy_group']}
{$ProxyGroups['domestic_group']}
{$ProxyGroups['others_group']}
{$ProxyGroups['apple_group']}
{$ProxyGroups['auto_group']}
{$ProxyGroups['direct_group']}

[Rule]
{include file='rule/Apple.conf'}
{include file='rule/PROXY.conf'}
{include file='rule/DIRECT.conf'}

GEOIP,CN,🍂 Domestic
FINAL,☁️ Others

[DNS]
system, 119.29.29.29, 223.6.6.6, 114.114.114.114

[STATE]
STATE,AUTO
