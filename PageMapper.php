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

        $dataPath = fullpath($conf['savedir']);
        if (!@file_exists($dataPath)) {
           $dataPath = fullpath(DOKU_INC . $conf['savedir']);
        }
        $this->_dbpath = $dataPath . '/sphinxsearch/' . $this->_database;


        if (false != ($db = new PDO("sqlite:".$this->_dbpath))) {            
            $q = @$db->query("SELECT 1 FROM {$this->_table} limit 1");
            if ($q === false) {
                $result = $db->query("CREATE TABLE {$this->_table} ( page varchar(1024), page_crc int(11), hid varchar(1024), title_text varchar(1024), title varchar(1024), unique (page, page_crc))");
                if (!$result) {
                    echo "\nPDO::errorInfo():\n";
                    print_r($db->errorInfo());
                    exit;
                }

            }
        } else {
            echo "\nPDO::errorInfo():\n";
            print_r($db->errorInfo());
            exit;
        }
        $this->_db = $db;
    }

    public function add($page, $title_text, $title, $hid='')
    {
        $result = $this->_db->query("REPLACE into {$this->_table}(page, page_crc, hid, title, title_text) values(".$this->_db->quote($page).",
                                    '".sprintf('%u', crc32($page.$hid))."',
                                    ".$this->_db->quote($hid).",
                                    ".$this->_db->quote($title).",
                                    ".$this->_db->quote($title_text).")");
        if (!$result) {
            echo "\nPDO::errorInfo():\n";
            print_r($this->_db->errorInfo());
        }
    }

    public function getAll()
    {
        $result = $this->_db->query("select * from {$this->_table}");
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByCrc($pageCrcList)
    {
        $sql = sprintf("select * from {$this->_table} where page_crc in (%s)", implode(",", $pageCrcList));
        $result = $this->_db->query($sql);
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        
        $pages = array();
        foreach($rows as $row){
            $pages[$row['page_crc']] = array('page' => $row['page'], 'hid' => $row['hid'], 'title' => $row['title'], 'title_text' => $row['title_text']);
        }
        $results = array();
        foreach($pageCrcList as $crc){
            $results[$crc] = $pages[$crc];
        }
        return $results;
    }    
}
