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
        $body = array();
        $title = array();
        $category = array();
        foreach ($pageCrcList as $crc){
            if (!empty($pagesIds[$crc]['hid'])){
                $bodyHtml = p_render('xhtml',p_get_instructions(getSection($pagesIds[$crc]['page'], $pagesIds[$crc]['title'])),$info);
            } else {
                $bodyHtml = p_wiki_xhtml($pagesIds[$crc]['page']);
            }
            $body[$crc] = strip_tags($bodyHtml);
            $title[$crc] = strip_tags($pagesIds[$crc]['title']);
            $category[$crc] = $pagesIds[$crc]['page'];
        }

        $starQuery = $this->starQuery($query);
        $bodyExcerpt = $this->getExcerpt($body, $starQuery);
        $titleExcerpt = $this->getExcerpt($title, $starQuery);
        $i = 0;
        $results = array();
        foreach($body as $crc => $notused){
            $results[$crc] = array(
                'page' => $pagesIds[$crc]['page'],
                'bodyExcerpt' => $bodyExcerpt[$i],
                'titleExcerpt' => $titleExcerpt[$i],
                'hid' => $pagesIds[$crc]['hid'],
                'title' => $pagesIds[$crc]['title']
            );
            $i++;
        }
        return $results;
    }

    public function getTotalFound()
    {
        return !empty($this->_result['total_found'])?$this->_result['total_found'] : 0;
    }

    public function getExcerpt($data, $query)
    {
        return $this->_sphinx->BuildExcerpts($data, $this->_index, $query);
    }

    public function starQuery($query)
    {
        $words = explode(" ", $query);
        $starQuery = '';
        foreach($words as $word){
            $starQuery .= "*".$word."* ";
        }
        return $starQuery;
    }
}
