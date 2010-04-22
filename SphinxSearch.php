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
    private $_query = '';

    private $_snippetSize = 256;
    private $_aroundKeyword = 5;

    private $_titlePriority = 20;
    private $_bodyPriority = 5;
    private $_categoriesPriority = 10;
    
    public function  __construct($host, $port, $index)
    {
        $this->_sphinx = new SphinxClient();
        $this->_sphinx->SetServer($host, $port);
        $this->_sphinx->SetMatchMode(SPH_MATCH_EXTENDED2);        

        $this->_index = $index;
    }

    public function search($keywords, $categories, $start, $resultsPerPage = 10)
    {
        $this->_sphinx->SetFieldWeights(array('categories' => $this->_categoriesPriority, 'title' => $this->_titlePriority, 'body' => $this->_bodyPriority));

        $this->_sphinx->SetLimits($start, $resultsPerPage+100);
        $query = '';
        if (!empty($keywords) && empty($categories)){
            $query = "@(body,title,categories) {$keywords}";
        } else {
            $query = "@(body,title) {$keywords} @categories ".$categories;
        }
        $this->_query = $query;
        $res = $this->_sphinx->Query($query, $this->_index);
        $this->_result = $res;
        if (empty($res['matches'])) {
            return false;
	}

        $pageMapper = new PageMapper();

        $pagesIds = $pageMapper->getByCrc(array_keys($res['matches']), $resultsPerPage);
        if (empty($pagesIds)){
            
            return false;
        }

        $pagesList = array();
        $body = array();
        $titleText = array();
        $category = array();
        foreach ($pagesIds as $crc => $data){
            if (!empty($data['hid'])){
                $bodyHtml = p_render('xhtml',p_get_instructions(getSectionByTitleLevel($data['page'], $data['title'], true)),$info);
            } else {
                $bodyHtml = p_wiki_xhtml($data['page']);
            }
            $body[$crc] = strip_tags($bodyHtml);
            $titleText[$crc] = strip_tags($data['title_text']);
            $category[$crc] = $data['page'];
        }        

        $starQuery = $this->starQuery($keywords);
        $bodyExcerpt = $this->getExcerpt($body, $starQuery);
        $titleTextExcerpt = $this->getExcerpt($titleText, $starQuery);
        $i = 0;
        $results = array();
        foreach($body as $crc => $notused){
            $results[$crc] = array(
                'page' => $pagesIds[$crc]['page'],
                'bodyExcerpt' => $bodyExcerpt[$i],
                'titleTextExcerpt' => $titleTextExcerpt[$i],
                'hid' => $pagesIds[$crc]['hid'],
                'title' => $pagesIds[$crc]['title'],
                'title_text' => $pagesIds[$crc]['title_text']
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
        return $this->_sphinx->BuildExcerpts($data, $this->_index, $query,
                    array(
                        'limit' => $this->_snippetSize,
                        'around' => $this->_aroundKeyword
                    )
                );
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

    public function getQuery()
    {
        return $this->_query;
    }

    public function setSnippetSize($symbols = 256)
    {
        $this->_snippetSize = $symbols;
    }

    public function setArroundWordsCount($words = 5)
    {
        $this->_aroundKeyword = $words;
    }

    public function setTitlePriority($priority)
    {
        $this->_titlePriority = $priority;
    }

    public function setBodyPriority($priority)
    {
        $this->_bodyPriority = $priority;
    }

    public function setCategoriesPriority($priority)
    {
        $this->_categoriesPriority = $priority;
    }
}
