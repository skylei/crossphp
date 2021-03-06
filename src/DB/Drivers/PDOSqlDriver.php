<?php
/**
 * Cross - a micro PHP 5 framework
 *
 * @link        http://www.crossphp.com
 * @license     http://www.crossphp.com/license
 * @version     1.3.0
 */
namespace Cross\DB\Drivers;

use Cross\DB\SQLAssembler\SQLAssembler;
use Cross\Exception\CoreException;
use Cross\I\PDOConnecter;
use PDOException;
use PDOStatement;
use Exception;
use PDO;

/**
 * @Auth: wonli <wonli@live.com>
 * Class PDOSqlDriver
 * @package Cross\DB\Drivers
 */
class PDOSqlDriver
{
    /**
     * @var PDOStatement
     */
    public $stmt;

    /**
     * @var PDO
     */
    public $pdo;

    /**
     * @var string
     */
    public $sql;

    /**
     * 链式查询每条语句的标示符
     *
     * @var int
     */
    protected $qid = 0;

    /**
     * 以qid为key,存储链式查询生成的sql语句
     *
     * @var array
     */
    protected $querySQL = array(0);

    /**
     * 联系查询生成的参数缓存
     *
     * @var array
     */
    protected $queryParams;

    /**
     * @var string|array
     */
    protected $params;

    /**
     * @var PDOConnecter
     */
    protected $connecter;

    /**
     * @var SQLAssembler
     */
    protected $SQLAssembler;

    /**
     * 创建数据库连接
     *
     * @param PDOConnecter $connecter
     * @param SQLAssembler $SQLAssembler
     * @throws CoreException
     */
    public function __construct(PDOConnecter $connecter, SQLAssembler $SQLAssembler)
    {
        $this->setConnecter($connecter);
        $this->setSQLAssembler($SQLAssembler);

        $this->pdo = $this->connecter->getPDO();
        if (!$this->pdo instanceof PDO) {
            throw new CoreException("init pdo failed!");
        }
    }

    /**
     * 获取单条数据
     *
     * @param string $table
     * @param string $fields
     * @param string|array $where
     * @return mixed
     */
    public function get($table, $fields, $where)
    {
        $this->SQLAssembler->get($table, $fields, $where);
        return $this->getPrepareResult();
    }

    /**
     * 获取表中所有数据
     *
     * @param string $table
     * @param string $fields
     * @param string|array $where
     * @param int|string $order
     * @param int|string $group_by
     * @param int|string $limit 0 表示无限制
     * @return mixed
     */
    public function getAll($table, $fields, $where = '', $order = 1, $group_by = 1, $limit = 0)
    {
        $this->SQLAssembler->getAll($table, $fields, $where, $order, $group_by, $limit);
        return $this->getPrepareResult(true);
    }

    /**
     * 插入数据
     *
     * @see SQLAssembler::add()
     * @param string $table 要插入的数据表
     * @param string $data 要插入的数据,批量插入时的数据结构如下
     * @param bool $multi 批量插入数据时,开启事务
     * @param array $insert_data 插入的数据列表
     * @param bool $openTA
     * @return bool|mixed
     * @throws CoreException
     */
    public function add($table, $data, $multi = false, & $insert_data = array(), $openTA = false)
    {
        $this->SQLAssembler->add($table, $data, $multi, $insert_data);
        $this->sql = $this->SQLAssembler->getSQL();
        $this->params = $this->SQLAssembler->getParams();

        if ($multi) {
            if (!empty($this->params)) {
                $inc_name = $this->getAutoIncrementName($table);
                $stmt = $this->prepare($this->sql);

                if ($openTA) {
                    $this->beginTA();
                    try {
                        if (!empty($this->params)) {
                            foreach ($this->params as $p) {
                                if ($stmt->exec($p)) {
                                    $add_data_info = array_combine($data['fields'], $p);
                                    if ($inc_name) {
                                        $add_data_info[$inc_name] = $stmt->insertId();
                                    }

                                    $insert_data[] = $add_data_info;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $insert_data = array();
                        $this->rollBack();
                        throw new CoreException($e->getMessage());
                    }
                    $this->commit();
                } else {
                    if (!empty($this->params)) {
                        foreach ($this->params as $p) {
                            if ($stmt->exec($p)) {
                                $add_data_info = array_combine($data['fields'], $p);
                                if ($inc_name) {
                                    $add_data_info[$inc_name] = $stmt->insertId();
                                }

                                $insert_data[] = $add_data_info;
                            }
                        }
                    }
                }
            }
            return true;
        } else {
            $result = $this->prepare($this->sql)->exec($this->params);
            $last_insert_id = $result->insertId();
            if ($last_insert_id > 0) {
                return $last_insert_id;
            }

            return true;
        }
    }

    /**
     * 带分页的数据查询
     *
     * @param string $table
     * @param string $fields 字段名
     * @param string|array $where
     * @param int $order
     * @param array $page
     * @param int $group_by
     * @return mixed
     */
    public function find($table, $fields, $where, $order = 1, & $page = array('p' => 1, 'limit' => 50), $group_by = 1)
    {
        $total = $this->get($table, 'COUNT(*) as total', $where);

        $page['result_count'] = (int)$total['total'];
        $page['limit'] = max(1, (int)$page['limit']);
        $page['total_page'] = ceil($page['result_count'] / $page['limit']);

        if ($page['p'] <= $page['total_page']) {
            $page['p'] = max(1, $page['p']);
            $this->SQLAssembler->find($table, $fields, $where, $order, $page, $group_by);
            return $this->getPrepareResult(true);
        }

        return array();
    }

    /**
     * 数据更新
     *
     * @param string $table
     * @param string|array $data
     * @param string|array $where
     * @return bool
     * @throws CoreException
     */
    public function update($table, $data, $where)
    {
        $this->SQLAssembler->update($table, $data, $where);
        $this->sql = $this->SQLAssembler->getSQL();
        $this->params = $this->SQLAssembler->getParams();

        $this->prepare($this->sql)->exec($this->params);
        return true;
    }

    /**
     * 删除
     *
     * @see SQLAssembler::del()
     * @param string $table
     * @param string|array $where
     * @param bool $multi 是否批量删除数据
     * @param bool $openTA 是否开启事务 默认关闭
     * @return bool
     * @throws CoreException
     */
    public function del($table, $where, $multi = false, $openTA = false)
    {
        $this->SQLAssembler->del($table, $where, $multi);
        $this->sql = $this->SQLAssembler->getSQL();
        $this->params = $this->SQLAssembler->getParams();
        if ($multi) {
            if ($openTA) {
                $this->beginTA();
                try {
                    if (!empty($this->params)) {
                        $stmt = $this->prepare($this->sql);
                        foreach ($this->params as $p) {
                            $stmt->exec($p);
                        }
                    }
                } catch (Exception $e) {
                    $this->rollBack();
                    throw new CoreException($e->getMessage());
                }
                $this->commit();
            } else {
                if (!empty($this->params)) {
                    $stmt = $this->prepare($this->sql);
                    foreach ($this->params as $p) {
                        $stmt->exec($p);
                    }
                }
            }
        } else {
            $this->prepare($this->sql)->exec($this->params);
        }

        return true;
    }

    /**
     * 执行一条SQL 语句 并返回结果
     *
     * @param string $sql
     * @param int $fetch_style
     * @param int $cursor_orientation
     * @param int $cursor_offset
     * @return mixed
     * @throws CoreException
     */
    public function fetchOne(
        $sql,
        $fetch_style = PDO::FETCH_ASSOC,
        $cursor_orientation = PDO::FETCH_ORI_NEXT,
        $cursor_offset = 0
    ) {
        try {
            return $this->pdo->query($sql)->fetch($fetch_style, $cursor_orientation, $cursor_offset);
        } catch (Exception $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * 执行sql 并返回所有结果
     *
     * @param string $sql
     * @param int $fetch_style
     * @param null $fetch_argument
     * @param array $ctor_args
     * <pre>
     * 当fetch_style为PDO::FETCH_CLASS时, 自定义类的构造函数的参数。
     * </pre>
     * @return array
     * @throws CoreException
     */
    public function fetchAll($sql, $fetch_style = PDO::FETCH_ASSOC, $fetch_argument = null, $ctor_args = array())
    {
        try {
            $data = $this->pdo->query($sql);
            if (null !== $fetch_argument) {
                switch ($fetch_style) {
                    case PDO::FETCH_CLASS:
                        return $data->fetchAll($fetch_style, $fetch_argument, $ctor_args);

                    default:
                        return $data->fetchAll($fetch_style, $fetch_argument);
                }

            } else {
                return $data->fetchAll($fetch_style);
            }
        } catch (Exception $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * 执行sql 用于无返回值的操作
     *
     * @param string $sql
     * @return int
     * @throws CoreException
     */
    public function execute($sql)
    {
        try {
            return $this->pdo->exec($sql);
        } catch (Exception $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * 绑定sql语句,执行后给出返回结果
     *
     * @param bool $_fetchAll
     * @param int $fetch_style
     * @return mixed
     * @throws CoreException
     */
    public function getPrepareResult($_fetchAll = false, $fetch_style = PDO::FETCH_ASSOC)
    {
        $this->sql = $this->SQLAssembler->getSQL();
        $this->params = $this->SQLAssembler->getParams();

        return $this->prepare($this->sql)->exec($this->params)->stmtFetch($_fetchAll, $fetch_style);
    }

    /**
     * 链式查询以select开始,跟原生的sql语句保持一致
     *
     * @param string $fields
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function select($fields = '*')
    {
        $this->makeQueryID();
        $this->querySQL[$this->qid] = $this->SQLAssembler->select($fields);
        return $this;
    }

    /**
     * @param $table
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function from($table)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->from($table);
        return $this;
    }

    /**
     * @param $where
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function where($where)
    {
        $params = array();
        $this->querySQL[$this->qid] .= $this->SQLAssembler->where($where, $params);
        $this->queryParams[$this->qid] = $params;

        return $this;
    }

    /**
     * @param $start
     * @param bool $end
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function limit($start, $end = false)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->limit($start, $end);
        return $this;
    }

    /**
     * @param $offset
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function offset($offset)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->offset($offset);
        return $this;
    }

    /**
     * @param $order
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function orderBy($order)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->orderBy($order);
        return $this;
    }

    /**
     * @param $group
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function groupBy($group)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->groupBy($group);
        return $this;
    }

    /**
     * @param $having
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function having($having)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->having($having);
        return $this;
    }

    /**
     * @param $procedure
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    function procedure($procedure)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->procedure($procedure);
        return $this;
    }

    /**
     * @param $var_name
     * @return \Cross\DB\Drivers\PDOSqlDriver
     */
    public function into($var_name)
    {
        $this->querySQL[$this->qid] .= $this->SQLAssembler->into($var_name);
        return $this;
    }

    /**
     * 返回链式查询当前生成的prepare语句
     *
     * @return mixed
     */
    function getSQL()
    {
        return array(
            'sql' => $this->querySQL[$this->qid],
            'params' => $this->queryParams[$this->qid]
        );
    }

    /**
     * 生成qid
     */
    private function makeQueryID()
    {
        do {
            $qid = mt_rand(1, 99999);
            if (!isset($this->querySQL[$qid])) {
                $this->qid = $qid;
                break;
            }
        } while (true);
    }

    /**
     * 绑定链式查询生成的sql语句并返回stmt对象
     *
     * @param bool $execute 是否调用stmt的execute
     * @param array $prepare_params prepare时的参数
     * @return PDOStatement
     * @throws CoreException
     */
    public function stmt($execute = true, $prepare_params = array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY))
    {
        //如果不是以select开始调用,抛出异常
        if ($this->qid == 0) {
            throw new CoreException("链式风格的查询必须以->select()开始");
        }

        $sql = $this->querySQL[$this->qid];
        try {
            $stmt = $this->pdo->prepare($sql, $prepare_params);
            if ($execute) {
                $execute_params = array();
                if (isset($this->queryParams[$this->qid])) {
                    $execute_params = $this->queryParams[$this->qid];
                }
                $stmt->execute($execute_params);
            }

            unset($this->querySQL[$this->qid], $this->queryParams[$this->qid]);
            return $stmt;
        } catch (Exception $e) {
            throw new CoreException(sprintf("%s<br>SQL: %s", $e->getMessage(), $sql));
        }
    }

    /**
     * 参数绑定
     *
     * @param string $statement
     * @param array $params
     * @return $this
     * @throws CoreException
     */
    public function prepare($statement, $params = array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY))
    {
        try {
            $this->stmt = $this->pdo->prepare($statement, $params);
            if (!$this->stmt) {
                throw new CoreException("PDO prepare failed!");
            }

            return $this;
        } catch (PDOException $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * 执行参数绑定
     *
     * @param array $args
     * @return $this
     * @throws CoreException
     */
    public function exec($args = array())
    {
        try {
            $this->stmt->execute($args);
            return $this;
        } catch (PDOException $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * 返回参数绑定结果
     *
     * @param bool $_fetchAll
     * @param int $fetch_style
     * @return mixed
     * @throws CoreException
     */
    public function stmtFetch($_fetchAll = false, $fetch_style = PDO::FETCH_ASSOC)
    {
        if (!$this->stmt) {
            throw new CoreException('stmt init failed!');
        }

        if (true === $_fetchAll) {
            return $this->stmt->fetchAll($fetch_style);
        }

        return $this->stmt->fetch($fetch_style);
    }

    /**
     * 获取自增字段名
     *
     * @param string $table_name
     * @return bool
     */
    public function getAutoIncrementName($table_name)
    {
        return $this->connecter->getPK($table_name);
    }

    /**
     * @return PDOConnecter
     */
    public function getConnecter()
    {
        return $this->connecter;
    }

    /**
     * 设置PDOConnecter对象
     *
     * @param PDOConnecter $connecter
     */
    public function setConnecter(PDOConnecter $connecter)
    {
        $this->connecter = $connecter;
    }

    /**
     * @return SQLAssembler
     */
    public function getSQLAssembler()
    {
        return $this->SQLAssembler;
    }

    /**
     * 设置SQLAssembler对象
     *
     * @param SQLAssembler $SQLAssembler
     */
    public function setSQLAssembler(SQLAssembler $SQLAssembler)
    {
        $this->SQLAssembler = $SQLAssembler;
    }

    /**
     * 解析fields
     *
     * @param string|array $fields
     * @return string
     */
    public function parseFields($fields)
    {
        return $this->SQLAssembler->parseFields($fields);
    }

    /**
     * 解析where
     *
     * @param string|array $where
     * @param string|array $params
     * @return string
     */
    public function parseWhere($where, & $params)
    {
        return $this->SQLAssembler->parseWhere($where, $params);
    }

    /**
     * 解析order
     *
     * @param string|array $order
     * @return int|string
     */
    public function parseOrder($order)
    {
        return $this->SQLAssembler->parseOrder($order);
    }

    /**
     * 解析group
     *
     * @param string $group_by
     * @return int
     */
    public function parseGroup($group_by)
    {
        return $this->SQLAssembler->parseGroup($group_by);
    }

    /**
     * 开启事务
     *
     * @return bool
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * @return bool
     */
    public function beginTA()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * 回滚
     *
     * @return bool
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    /**
     * 返回最后操作的id
     *
     * @return string
     */
    public function insertId()
    {
        return $this->connecter->lastInsertID();
    }
}
