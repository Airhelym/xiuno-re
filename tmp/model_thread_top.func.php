<?php



// 置顶主题

function thread_top_change($tid, $top = 0) {
	
	$thread = thread__read($tid);
	if(empty($thread)) return FALSE;
	if($top != $thread['top']) {
		thread__update($tid, array('top'=>$top));
		$fid = $thread['fid'];
		$tid = $thread['tid'];
		thread_top_cache_delete();
		
		$arr = array('fid'=>$fid, 'tid'=>$tid, 'top'=>$top);
		$r = db_replace('thread_top', $arr);
		return $r;
	}
	
	return FALSE;
}

function thread_top_delete($tid) {
	
	$r = db_delete('thread_top', array('tid'=>$tid));
	
	return $r;
}

function thread_top_find($fid = 0) {
	
	if($fid == 0) {
		$threadlist = db_find('thread_top', array('top'=>3), array('tid'=>-1), 1, 100, 'tid');
	} else {
		$threadlist = db_find('thread_top', array('fid'=>$fid, 'top'=>1), array('tid'=>-1), 1, 100, 'tid');
	}
	$tids = arrlist_values($threadlist, 'tid');
	$threadlist = thread_find_by_tids($tids);
	
	return $threadlist;
}

function thread_top_find_cache() {
	
	global $conf;
	$threadlist = cache_get('thread_top_list');
	if($threadlist === NULL) {
		$threadlist = thread_top_find();
		cache_set('thread_top_list', $threadlist);
	} else {
		// 重新格式化时间
		foreach($threadlist as &$thread) {
			thread_format_last_date($thread);
		}
	}
	
	return $threadlist;
}

function thread_top_cache_delete() {
	
	global $conf;
	static $deleted = FALSE;
	if($deleted) return;
	cache_delete('thread_top_list');
	$deleted = TRUE;
	
}

function thread_top_update_by_tid($tid, $newfid) {
	
	$r = db_update('thread_top', array('tid'=>$tid), array('fid'=>$newfid));
	
	return $r;
}




?>