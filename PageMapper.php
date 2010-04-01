<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class PageMapper
{
    private $_database = 'pagedata';
    private $_table = 'pages';
    private $_db = null;
    public function  __construct()
    {
        global $conf;
        $this->_dbpath = DOKU_INC . $conf['savedir'] . '/sphinxsearch/'.$this->_database;

        if (false != ($db = new PDO("sqlite:".$this->_dbpath))) {
            $q = @$db->query("SELECT 1 FROM {$this->_table} limit 1");
            if ($q === false) {
                $db->query("CREATE TABLE {$this->_table} (page varchar(1024), page_crc int(11))");
            }
        }
        $this->_db = $db;
    }

    public function add($page)
    {
        $this->_db->query("REPLACE into {$this->_table}(page, page_crc) values('{$page}', '".crc32($page)."')");
    }

    public function getAll()
    {
        $result = $this->_db->query("select * from {$this->_table}");
        return $result->fetchAll($query);
    }

    public function getByCrc($pageCrcList)
    {
        $sql = sprintf("select * from {$this->_table} where page_crc in (%s)", implode(",", $pageCrcList));
        $result = $this->_db->query($sql);
        $rows = $result->fetchAll($query);
        $pages = array();
        foreach($rows as $row){
            $pages[$row['page_crc']] = $row['page'];
        }
        return $pages;
    }
}
