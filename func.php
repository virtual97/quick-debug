<?php
/**
 * Dump function
 *
 * @param mixed $var
 * @param int $dump
 * @param int $trace
 */
function qqq($var, $dump = 0, $trace = 0)
{
    ob_start();
    if ($dump == 2) {
        var_export($var);
    } elseif ($dump == 1) {
        var_dump($var);
    } else {
        print_r($var);
    }
    if ($trace == 1) {
        echo PHP_EOL;
        $e = new Exception();
        echo $e->getTraceAsString();
    } elseif ($trace == 2) {
        echo PHP_EOL;
        debug_print_backtrace();
    }
    $res = ob_get_contents();
    ob_end_clean();
    if (!isAjax() && !extension_loaded('xdebug') && $dump == 1) {
        $res = htmlspecialchars($res);
    }
    if (!isset($_SERVER['argv']) || !$_SERVER['argv']) {
        echo PHP_EOL . (!isAjax() ? '<pre>' : '/*'), $res, (!isAjax() ? '</pre>' : '*/');
    } else {
        echo PHP_EOL, '  ', $res;
    }
}

/**
 * Dump function with stopping script on the end
 *
 * @param mixed $var
 * @param int $dump
 * @param int $trace
 */
function qqq1($var, $dump = 0, $trace = 0)
{
    qqq($var, $dump, $trace);
    exit(1);
}

/**
 * Dump HTML
 *
 * @param mixed $var
 * @param int $dump
 * @param int $trace
 */
function qqqHtml($var, $dump = 0, $trace = 0)
{
    if (is_string($var)) {
        $var = str_replace('><', ">\n<", $var);
    }
    qqq($var, $dump, $trace);
}

/**
 * Show exception
 *
 * @param Exception $e
 * @param bool $exit
 */
function qqqE(Exception $e, $exit = true)
{
    qqq($e->getMessage());
    qqq($e->getTraceAsString());

    if ($exit) {
        exit(1);
    }
}

/**
 * Check ajax (or console request)
 *
 * @return bool
 */
function isAjax()
{
    if (!defined('QQQ_AJAX')) {
        if (isset($_GET['isAjax']) && $_GET['isAjax'] == 'false') {
            $res = false;
        } else {
            $res = strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
                || @$_GET['isAjax'] == 'true' || @$_GET['isAjax'] == '1'
                || !isset($_SERVER['HTTP_HOST']);
        }
        define('QQQ_AJAX',  $res);
    }
    return QQQ_AJAX;
}

/**
 * Print class name
 *
 * @param object $obj
 * @param bool $exit
 */
function qqqClass($obj, $exit = false)
{
    if (!is_object($obj)) {
        $res = 'NaN';
    } else {
        $res = get_class($obj);
    }
    if ($exit) {
        qqq1($res);
    } else {
        qqq($res);
    }
}

/**
 * Show dump in the table
 *
 * @param array $value
 * @param bool $echo
 * @return string
 */
function qqqTable($value, $echo = true)
{
    if (count($value) == 0) {
        return;
    }
    $str = '<table border="1">';
    $cnt = 0;
    foreach ($value as $row) {
        $str .= "<tr>";
        if (is_array($row) || is_object($row) || $row instanceof Varien_Object && $row = $row->getData()) {
            if ($cnt == 0 && ++$cnt) {
                foreach ($row as $ii => $rrow) {
                    $str .= "<td>";
                    $str .= ($ii ? $ii : "&nbsp;");
                    $str .= "</td>";
                }
                $str .= "</tr>";
                $str .= "<tr>";
            }
            foreach ($row as $rrow) {
                $str .= "<td>";
                $str .= ($rrow ? $rrow : "&nbsp;");
                $str .= "</td>";
            }
        } else {
            $str .= "<td>";
            $str .= ($row ? $row : "&nbsp;");
            $str .= "</td>";
        }
        $str .= "</tr>";
    }
    $str .= "</table>";
    if ($echo)
        echo $str;

    return $str;
}

/**
 * Dump SQL with formatter
 *
 * @param string $sql          SQL-request
 * @param bool $echo
 * @return string
 */
function qqqSql($sql, $echo = true)
{
    $sql = str_replace(PHP_EOL, null,$sql);
    $sql = preg_replace('/[ |\t]{1,}/', ' ', $sql);
    //    $sql = str_ireplace('SELECT ', PHP_EOL . "SELECT ", $sql);
    $sql = str_ireplace(' WHERE ', PHP_EOL . " WHERE ", $sql);
    $sql = str_ireplace('UNION', PHP_EOL . PHP_EOL . "UNION" . PHP_EOL . PHP_EOL, $sql);
    $sql = str_ireplace(' ORDER ', PHP_EOL . " ORDER ", $sql);
    $sql = str_ireplace(' GROUP ', PHP_EOL . " GROUP ", $sql);
    $sql = str_ireplace(' LEFT ', PHP_EOL . " LEFT ", $sql);
    $sql = str_ireplace(' INNER ', PHP_EOL . " INNER ", $sql);
    $sql = str_ireplace(' FROM ', PHP_EOL . " FROM ", $sql);

    $sql = str_ireplace(' AND ', PHP_EOL . "     AND ", $sql);
    $sql = str_ireplace(' OR ', PHP_EOL . "     OR ", $sql);
    $sql = str_ireplace(',', "," . PHP_EOL ."    ", $sql);
    if ($echo) {
        qqq($sql);
    }
    return $sql;
}

/**
 * Calc micro time
 *
 * @param bool $finish
 * @param string $key
 * @param bool $echo
 * @return int
 */
function qqqMkTime($finish = false, $key = 'default', $echo = true)
{
    static $time;
    if ($finish) {
        list($mkTime, $stamp) = explode(' ', microtime());
        $t = $stamp + $mkTime - $time[$key];
        if ($echo) {
            qqq("Key '$key': $t");
        }
        return $t;
    } else {
        list($mkTime, $stamp) = explode(' ', microtime());
        $time[$key] = $stamp + $mkTime;
        return $time[$key];
    }
}


/**
 * Get DB profiler
 *
 * @param string $connectionName
 * @return mixed|Zend_Db_Profiler
 */
function profiler($connectionName = 'core_read')
{
    if (false == ($profiler = Mage::registry('profiler'))) {
        /** @var $resource Mage_Core_Model_Resource */
        $resource = Mage::getSingleton('core/resource');
        /** @var $db Varien_Db_Adapter_Pdo_Mysql */
        $db = $resource->getConnection($connectionName);
        $profiler = new Zend_Db_Profiler();
        $profiler->setEnabled(1);
        $db->setProfiler($profiler);
        Mage::register('profiler', $profiler);
    }
    return $profiler;
}

/**
 * Get profile queries list
 *
 * @return array
 */
function profilerQueries($echo = false)
{
    $profiler = profiler();
    $data = array();
    /** @var $query Zend_Db_Profiler_Query */
    $queryProfiles = $profiler->getQueryProfiles();
    if (false === $queryProfiles) {
        $data = 'Queries is FALSE.';
    } else {
        foreach ($queryProfiles as $query) {
            $data[] = array(
                'query'  => $query->getQuery(),
                'params' => $query->getQueryParams(),
            );
        }
    }
    if ($echo) {
        qqq($data);
    }
    return $data;
}
