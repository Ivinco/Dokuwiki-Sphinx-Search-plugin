<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class SphinxSearch
{
    private $_sphinx = null;
    private $_result = array();
    private $_index = null;
    public function  __construct($host, $port, $index)
    {
        $this->_sphinx = new SphinxClient();
        $this->_sphinx->SetServer($host, $port);
        $this->_sphinx->SetMatchMode(SPH_MATCH_EXTENDED2);
        $this->_sphinx->SetFieldWeights(array('categories' => 5, 'title' => 20, 'body' => 3));
        $this->_sphinx->SetFilter('level', array(1));

        $this->_index = $index;
    }

    public function search($query, $start, $resultsPerPage = 10)
    {
        $this->_sphinx->SetLimits($start, $resultsPerPage);
        $res = $this->_sphinx->Query($query, $this->_index);
        $this->_result = $res;
        if (empty($res['matches'])) {
            return false;
	}

        $pageMapper = new PageMapper();

        $pageCrcList = array_keys($res['matches']);
        $pagesIds = $pageMapper->getByCrc($pageCrcList);

        $pagesList = array();
        foreach ($pageCrcList as $crc){
            $data[$crc] = p_wiki_xhtml($pagesIds[$crc]['page']);
        }

        $pagesExcerpt = $this->_sphinx->BuildExcerpts($data, $this->_index, $query);
        $i = 0;
        $results = array();
        foreach($data as $crc => $notused){
            $results[$crc] = array( 'page' => $pagesIds[$crc]['page'], 'excerpt' => $pagesExcerpt[$i++], 'hid' => $pagesIds[$crc]['hid'], 'title' => $pagesIds[$crc]['title']);
        }
        return $results;
    }

    public function getTotalFound()
    {
        return !empty($this->_result['total_found'])?$this->_result['total_found'] : 0;
    }
}
