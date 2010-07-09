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
    private $_resultsPerPage = 10;

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

    public function setSearchAllQuery($keywords, $categories)
    {
        $starKeyword = $this->starQuery($keywords);
        $this->_query = "(@categories $starKeyword) | (@(body,title) {$keywords})";
    }

    public function setSearchAllQueryWithCategoryFilter($keywords, $categories)
    {
        $starKeyword = $this->starQuery($keywords);
        if(strpos($categories, "-") === 0){
            $categories = '-"'.substr($categories, 1).'"';
        }
        $this->_query = "(@categories {$categories}) & ((@(body,title) {$keywords}) | (@categories {$starKeyword}))";
    }

    public function setSearchCategoryQuery($keywords, $categories)
    {
        $starKeyword = $this->starQuery($keywords);
        if (!empty($categories)){
            $this->_query = "(@categories $categories)";
        } else {
            $this->_query = "(@categories $starKeyword)";
        }
    }

    public function search($start, $resultsPerPage = 10)
    {
        $this->_resultsPerPage = $resultsPerPage;

        $this->_sphinx->SetFieldWeights(array('categories' => $this->_categoriesPriority, 'title' => $this->_titlePriority, 'body' => $this->_bodyPriority));

        $this->_sphinx->SetLimits($start, $resultsPerPage+100);

        $res = $this->_sphinx->Query($this->_query, $this->_index);
        $this->_result = $res;

        if (empty($res['matches'])) {
            return false;
	}
        return true;
    }

    public function getPages()
    {
        $pagesIdsAll = $this->getPagesIds();
        $this->_offset = 0;
        $counter = 0;
        $tmpRes = array();
        $pagesIds = array();
        foreach($pagesIdsAll as $id => $pageData){
            $this->_offset++;
            if(auth_quickaclcheck($pageData['page']) >= AUTH_READ){
                if(!isset($tmpRes[$pageData['page']])){
                    $tmpRes[$pageData['page']] = 1;
                    $counter++;
                } 
                $pagesIds[$id] = $pageData;
                if ($counter == $this->_resultsPerPage){
                    break;
                }
            }             
        }        
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
            $bodyHtml = preg_replace("#[\s]+?</li>#", "</li>;", $bodyHtml);
            $bodyHtml = htmlspecialchars_decode($bodyHtml);
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

    public function getPagesIds()
    {
        $pageMapper = new PageMapper();

        return $pageMapper->getByCrc(array_keys($this->_result['matches']));
    }

    public function getOffset()
    {
        return $this->_offset;
    }

    public function getError()
    {
        return $this->_sphinx->GetLastError();
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
                        'around' => $this->_aroundKeyword,
                        'weight_order' => 1,
                        'sp' => 1
                    )
                );
    }

    public function starQuery($query)
    {
        $query = $this->removeStars($query);
        $words = explode(" ", $query);
        foreach($words as $id => $word){
            $words[$id] = "*".$word."*";
        }
        return implode(" ", $words);
    }

    public function removeStars($query)
    {
        $words = explode(" ", $query);
        foreach($words as $id => $word){
            $words[$id] = trim($word, "*");
        }
        return implode(" ", $words);
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
