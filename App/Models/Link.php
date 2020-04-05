<?php

namespace App\Models;

class Link extends Models
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'link';

    /**
     * 取得 token 对应用户
     */
    public function getUser(): User
    {
        return User::find($this->userid);
    }
}
