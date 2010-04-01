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
        $this->_sphinx->SetFieldWeights(array('categories' => 10));

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
            $pagesList[] = $pagesIds[$crc];
            $data[$pagesIds[$crc]] = p_wiki_xhtml($pagesIds[$crc]);
        }

        $pagesExcerpt = $this->_sphinx->BuildExcerpts($data, $this->_index, $query);
        $i = 0;
        foreach($data as $key => $v){
            $data[$key] = $pagesExcerpt[$i++];
        }
        return $data;
    }

    public function getTotalFound()
    {
        return !empty($this->_result['total_found'])?$this->_result['total_found'] : 0;
    }
}
