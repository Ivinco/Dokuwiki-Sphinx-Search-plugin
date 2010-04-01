<?php
/**
 * Script to search in uploaded pdf documents
 *
 * @author Dominik Eckelmann <eckelmann@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN . 'action.php');
require_once(DOKU_PLUGIN . 'sphinxsearch/sphinxapi.php');
require_once(DOKU_PLUGIN . 'sphinxsearch/PageMapper.php');
require_once(DOKU_PLUGIN . 'sphinxsearch/SphinxSearch.php');


class action_plugin_sphinxsearch extends DokuWiki_Action_Plugin {

    /**
	* return some info
	*/
    function getInfo() {
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
	}

    /**
	* Register to the content display event to place the results under it.
	*/
    /**
     * register the eventhandlers
     */
    function register(&$controller){
        $controller->register_hook('ACTION_ACT_PREPROCESS',
                                   'BEFORE',
                                   $this,
                                   'handle_act_preprocess',
                                   array());

        $controller->register_hook('TPL_ACT_UNKNOWN',
                                   'BEFORE',
                                   $this,
                                   'handle_act_unknown',
                                   array());
    }

    /**
     * Checks if 'googlesearch' was given as action, if so we
     * do handle the event our self and no further checking takes place
     */
    function handle_act_preprocess(&$event, $param){
        if($event->data != 'sphinxsearch') return; // nothing to do for us

        $event->stopPropagation(); // this is our very own action, no need to check other plugins
        $event->preventDefault();  // we handle it our self, thanks
    }

    /**
     * If our own 'googlesearch' action was given we produce our content here
     */
    function handle_act_unknown(&$event, $param){
        if($event->data != 'sphinxsearch') return; // nothing to do for us

        // we can handle it -> prevent others
        $event->stopPropagation();
        $event->preventDefault();

        global $QUERY;
        $this->_search($QUERY,$_REQUEST['start']);
    }    

    /**
     * do the search and displays the result
     */
    function _search($query, $start) {

        $start = (int) $start;
        if($start < 0) $start = 0;

	// backup the config array
	$cp = $conf;

        $search = new SphinxSearch($this->getConf('host'), $this->getConf('port'), $this->getConf('index'));
        $pagesList = $search->search($query, $start, $this->getConf('maxresults'));
        $totalFound = $search->getTotalFound();

        echo '<h2>Found '.$totalFound .' documents for query "' . hsc($query).'"</h2>';
	echo '<div class="search_result">';
        // printout the results
	foreach ($pagesList as $id => $excerpt) {
            $metaData = p_get_metadata($id);
            echo '<a href="/doku.php/'.$id.'" title="" class="wikilink1">'.hsc($id).' ('.hsc($metaData['title']).')'.'</a><br/>';
            echo '<span class="search_cnt">Last modified:'.date("Y-m-d H:i",$metaData['date']['modified']).'</span>';
            echo '<div class="search_snippet">';
            echo $excerpt;
            echo '</div>';            
            echo '<br />';
	}
        echo '</div>';
        echo '<div class="sphinxsearch_nav">';
        if ($start > 1){
            $prev = $start - $this->getConf('maxresults');
            if($prev < 0) $prev = 0;

            echo $this->external_link(wl('',array('do'=>'sphinxsearch','id'=>$query,'start'=>$prev),'false','&'),
                                      'prev','wikilink1 gs_prev',$conf['target']['interwiki']);
        }
        echo ' ';
        if($start + $this->getConf('maxresults') < $totalFound){
            $next = $start + $this->getConf('maxresults');

            echo $this->external_link(wl('',array('do'=>'sphinxsearch','id'=>$query,'start'=>$next),'false','&'),
                                      'next','wikilink1 gs_next',$conf['target']['interwiki']);
        }
        echo '</div>';
        
    }

     function searchform(){
          global $lang;
          global $ACT;
          global $QUERY;

          // don't print the search form if search action has been disabled
          if (!actionOk('sphinxsearch')) return false;

          print '<form action="'.wl().'" accept-charset="utf-8" class="search" id="dw__search"><div class="no">';
          print '<input type="hidden" name="do" value="sphinxsearch" />';
          print '<input type="text" ';
          if($ACT == 'sphinxsearch') print 'value="'.htmlspecialchars($QUERY).'" ';
          print 'id="qsearch__in" accesskey="f" name="id" class="edit" title="[ALT+F]" />';
          print '<input type="submit" value="'.$lang['btn_search'].'" class="button" title="'.$lang['btn_search'].'" />';
          print '</div></form>';
          return true;
    }
}

?>