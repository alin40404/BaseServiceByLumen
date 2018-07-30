<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

use \MongoDB\Operation\FindOneAndUpdate;

/**
 * Class Base
 * 基础model类
 * @package App\Models
 */
class Base extends Eloquent
{
    protected $primaryKey = '_id';          // 默认主键名
    public $incrementing = false;           // 主键不自增，自写代码控制主键增长
    private $_sequence_collection = '_sequence';    // 保存自增计数器的collection

    /**
     * 创建&更新时间保存为Linux时间戳
     * @return \Illuminate\Support\Carbon|int|\MongoDB\BSON\UTCDateTime
     */
    public function freshTimestamp()
    {
        return time();
    }

    /**
     * 避免转换时间戳为时间字符串
     * @param $value
     * @return \DateTime|\Illuminate\Support\Carbon|int|\MongoDB\BSON\UTCDateTime|string
     */
    public function fromDateTime($value)
    {
        return $value;
    }

    /**
     * 从数据库获取的的时间戳格式
     * @return string
     */
    public function getDateFormat()
    {
        return 'U';
        //return 'd-m-Y H:i:s';
    }

    /**
     * 获取自增id
     * @param $collection   collection名
     * @return mixed
     */
    public function getSequence($collection = null)
    {
        if (is_null($collection)) $collection = $this->collection;
        $seq = DB::getCollection($this->_sequence_collection)->findOneAndUpdate(
            ['_id' => $collection],
            ['$inc' => ['seq' => 1]],
            ['new' => true, 'upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );
        return $seq->seq;
    }


    /**
     * 根据id获取信息
     * @param $id
     * @param $table
     * @param array $fields
     * @return mixed
     */
    protected static function _getById($id, $table, $fields = [])
    {
        $query = DB::table($table)->where('id', $id);
        if (!empty($fields)) $query = $query->select($fields);
        return $query->first();
    }

    /**
     * 插入数据
     * @param $rows 可以是单条，也可以是多条
     * @param $table
     * @return mixed
     */
    protected static function _insert($rows, $table)
    {
        if (count($rows) == count($rows, 1)) {
            $rows['created_at'] = $rows['updated_at'] = time();
        } else {
            foreach ($rows as &$row) {
                $rows['created_at'] = $row['updated_at'] = time();
            }
        }
        return DB::table($table)->insert($rows);
    }

    /**
     *
     * @param $id
     * @param $row
     * @param $table
     * @return mixed
     */
    protected static function _update($id, $row, $table)
    {
        $row['updated_at'] = time();
        return DB::table($table)->where('id', $id)->update($row);
    }

    /**
     * 根据connection_code，创建数据库连接(如果本身就是数据库连接，直接返回)
     * @param null $mixed (null：连接默认数据库；数字：根据配置文件连接相应数据库；若本身就是数据库连接，直接返回自身；其他：返回false)
     * @return bool|\Laravel\Lumen\Application|mixed|null
     */
    protected static function makeDB($mixed = null)
    {
        if (is_null($mixed)) return app('db');
        if (is_numeric($mixed)) {
            $connections = config('database.connection_code');
            if (empty($connections[$mixed])) return false;
            return app('db')->connection($connections[$mixed]);
        }
        return $mixed;
    }

    /**
     * 构建where条件
     * @param $query
     * @param $where
     */
    protected static function _buildWhereArray(&$query, $where)
    {
        foreach ($where as $v) {
            if (count($v) == 2) {
                if (is_array($v[1])) {
                    $query->whereIn($v[0], $v[1]);
                } else {
                    $query->where($v[0], $v[1]);
                }
            } else if (count($v) == 3) {
                $query->where($v[0], $v[1], $v[2]);
            }
        };
    }

    /**
     * 构建select字段
     * @param $query
     * @param $fields
     */
    protected static function _buildSelect(&$query, $fields)
    {
        if (is_array($fields)) {
            foreach ($fields as $k => $v) {
                if ($k == 0) {
                    $query->select($v);
                } else {
                    $query->addSelect($v);
                }
            }
        } else {
            $query->select($fields);
        }
    }


    /**
     * SQL运算
     * @param $v
     * @return bool
     */
    protected static function _operator($operator,&$query,$where)
    {
        switch ( strtolower($operator) ){
            case 'in':
                $query->whereIn($where[0],$where[2]);
                break;
            case 'between':
                $query->whereBetween($where[0],$where[2]);
                break;
            default:
                $query->where([$where]);
                break;
        }
        return $query;
    }

    /**
     * 构建where条件
     * @param $query
     * @param $where = [
     *       [ 'id',1 ],
     *       [ 'id',1 ],
     *  ]
     */
    protected static function _buildWhere(&$query, $where)
    {
        foreach ($where as $k => $v) {
            if (is_array($v)) {
                self::_operator($v[1],$query,$v);
            } else {
                $query->where($k, $v);
            }
        };
        return $query;
    }

}