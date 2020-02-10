<?php

namespace App\Models;

/**
 * Node Model
 */

use App\Utils\{Tools, URL};

class Node extends Model
{
    protected $connection = 'default';
    protected $table = 'ss_node';

    protected $casts = [
        'node_speedlimit' => 'float',
        'traffic_rate' => 'float',
        'mu_only' => 'int',
        'sort' => 'int',
    ];

    public function getNodeIp()
    {
        $node_ip_str = $this->attributes['node_ip'];
        $node_ip_array = explode(',', $node_ip_str);
        return $node_ip_array[0];
    }

    public function getServer()
    {
        $explode = explode(';', $this->attributes['server']);
        if (stripos($explode[1], 'server=') !== false) {
            return URL::parse_args($explode[1])['server'];
        } else {
            return $explode[0];
        }
    }

    public function getOffsetPort($port)
    {
        return Tools::OutPort($this->attributes['server'], $this->attributes['name'], $port)['port'];
    }
}
