<?php

namespace App\Models;

use App\Utils\Tools;
use EasySwoole\EasySwoole\Config;

class Node extends Models
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'ss_node';

    protected $casts = [
        'node_speedlimit' => 'float',
        'traffic_rate'    => 'float',
        'mu_only'         => 'int',
        'sort'            => 'int',
    ];

    /**
     * 获取节点出口地址
     */
    public function getServer(): string
    {
        $out = '';
        $explode = explode(';', $this->server);
        if (in_array($this->sort, [0, 10]) && isset($explode[1]) && stripos($explode[1], 'server=') !== false) {
            $out = Tools::parse_args($explode[1])['server'];
        }
        return ($out != '' ? $out : $explode[0]);
    }

    /**
     * 获取 SS/SSR 节点
     *
     * @param User $user
     * @param int  $mu_port
     * @param int  $relay_rule_id
     * @param int  $is_ss
     * @param bool $emoji
     */
    public function getItem(User $user, int $mu_port = 0, int $relay_rule_id = 0, int $is_ss = 0, bool $emoji = false):? array
    {
        $relay_rule = Relay::where('id', $relay_rule_id)
            ->where(
                static function ($query) use ($user) {
                    $query->Where('user_id', '=', $user->id)
                        ->orWhere('user_id', '=', 0);
                }
            )
            ->orderBy('priority', 'DESC')
            ->orderBy('id')
            ->first();
        $node_name = $this->name;
        if ($relay_rule != null) {
            $node_name .= ' - ' . $relay_rule->dist_node()->name;
        }
        if ($mu_port != 0) {
            $mu_user = User::where('port', '=', $mu_port)->where('is_multi_user', '<>', 0)->first();
            if ($mu_user == null) {
                return null;
            }
            $mu_user->obfs_param = $user->getMuMd5();
            $mu_user->protocol_param = $user->id . ':' . $user->passwd;
            $user = $mu_user;
            $node_name .= (Config::getInstance()->getConf('SUB.disable_sub_mu_port') ? '' : ' - ' . $mu_port . ' 单端口');
        }
        if ($is_ss) {
            if (!Tools::SSCanConnect($user)) {
                return null;
            }
            $user = Tools::getSSConnectInfo($user);
            $return_array['type'] = 'ss';
        } else {
            if (!Tools::SSRCanConnect($user)) {
                return null;
            }
            $user = Tools::getSSRConnectInfo($user);
            $return_array['type'] = 'ssr';
        }
        $return_array['address']        = $this->getServer();
        $return_array['port']           = $user->port;
        $return_array['protocol']       = $user->protocol;
        $return_array['protocol_param'] = $user->protocol_param;
        $return_array['obfs']           = $user->obfs;
        $return_array['obfs_param']     = $user->obfs_param;
        if ($mu_port != 0 && strpos($this->server, ';') !== false) {
            $node_tmp             = Tools::OutPort($this->server, $this->name, $mu_port);
            $return_array['port'] = $node_tmp['port'];
            $node_name            = $node_tmp['name'];
        }
        $return_array['passwd'] = $user->passwd;
        $return_array['method'] = $user->method;
        $return_array['remark'] = ($emoji ? Tools::addEmoji($node_name) : $node_name);
        $return_array['class']  = $this->node_class;
        $return_array['group']  = Config::getInstance()->getConf('appName');
        $return_array['ratio']  = ($relay_rule != null ? $this->traffic_rate + $relay_rule->dist_node()->traffic_rate : $this->traffic_rate);

        return $return_array;
    }

    /**
     * 获取 V2Ray 节点
     *
     * @param User $user
     * @param int  $mu_port
     * @param int  $relay_rule_id
     * @param int  $is_ss
     * @param bool $emoji
     */
    public function getV2RayItem(User $user, int $mu_port = 0, int $relay_rule_id = 0, int $is_ss = 0, bool $emoji = false): array
    {
        $item           = Tools::v2Array($this->server);
        $item['type']   = 'vmess';
        $item['remark'] = ($emoji ? Tools::addEmoji($this->name) : $this->name);
        $item['id']     = $user->getUuid();
        $item['class']  = $this->node_class;
        return $item;
    }

    /**
     * 获取 V2RayPlugin | obfs 节点
     *
     * @param User $user 用户
     * @param int  $mu_port
     * @param int  $relay_rule_id
     * @param int  $is_ss
     * @param bool $emoji
     *
     * @return array|null
     */
    public function getV2RayPluginItem(User $user, int $mu_port = 0, int $relay_rule_id = 0, int $is_ss = 0, bool $emoji = false)
    {
        $return_array = Tools::ssv2Array($this->server);
        // 非 AEAD 加密无法使用
        if ($return_array['net'] != 'obfs' && !in_array($user->method, Tools::getSupportParam('ss_aead_method'))) {
            return null;
        }
        $return_array['remark']         = ($emoji ? Tools::addEmoji($this->name) : $this->name);
        $return_array['address']        = $return_array['add'];
        $return_array['method']         = $user->method;
        $return_array['passwd']         = $user->passwd;
        $return_array['protocol']       = 'origin';
        $return_array['protocol_param'] = '';
        if ($return_array['net'] == 'obfs') {
            $return_array['obfs_param'] = $user->getMuMd5();
        } else {
            $return_array['obfs'] = 'v2ray';
            if ($return_array['tls'] == 'tls' && $return_array['net'] == 'ws') {
                $return_array['obfs_param'] = ('mode=ws;security=tls;path=' . $return_array['path'] .
                    ';host=' . $return_array['host']);
            } else {
                $return_array['obfs_param'] = ('mode=ws;security=none;path=' . $return_array['path'] .
                    ';host=' . $return_array['host']);
            }
            $return_array['path'] = ($return_array['path'] . '?redirect=' . $user->getMuMd5());
        }
        $return_array['class'] = $this->node_class;
        $return_array['group'] = Config::getInstance()->getConf('appName');
        $return_array['type'] = 'ss';
        $return_array['ratio'] = $this->traffic_rate;

        return $return_array;
    }

    /**
     * Trojan 节点
     *
     * @param User $user 用户
     * @param int  $mu_port
     * @param int  $relay_rule_id
     * @param int  $is_ss
     * @param bool $emoji
     */
    public function getTrojanItem(User $user, int $mu_port = 0, int $relay_rule_id = 0, int $is_ss = 0, bool $emoji = false): array
    {
        $server = explode(';', $this->server);
        $opt    = [];
        if (isset($server[1])) {
            $opt = Tools::parse_args($server[1]);
        }
        $item['remark']   = ($emoji ? Tools::addEmoji($this->name) : $this->name);
        $item['type']     = 'trojan';
        $item['address']  = $server[0];
        $item['port']     = (isset($opt['port']) ? (int) $opt['port'] : 443);
        $item['passwd']   = $user->getUuid();
        $item['host']     = $item['address'];
        if (isset($opt['host'])) {
            $item['host'] = $opt['address'];
        }
        return $item;
    }
}
