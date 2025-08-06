<?php



// ------------> 最原生的 CURD，无关联其他数据。

function forum__create($arr) {
	
	$r = db_create('forum', $arr);
	
	return $r;
}

function forum__update($fid, $arr) {
	
	$r = db_update('forum', array('fid'=>$fid), $arr);
	
	return $r;
}

function forum__read($fid) {
	
	$forum = db_find_one('forum', array('fid'=>$fid));
	
	return $forum;
}

function forum__delete($fid) {
	
	$r = db_delete('forum', array('fid'=>$fid));
	
	return $r;
}

function forum__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 1000) {
	
	$forumlist = db_find('forum', $cond, $orderby, $page, $pagesize, 'fid');
	
	return $forumlist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function forum_create($arr) {
	
	$r = forum__create($arr);
	forum_list_cache_delete();
	
	return $r;
}

function forum_update($fid, $arr) {
	
	$r = forum__update($fid, $arr);
	forum_list_cache_delete();
	
	return $r;
}

function forum_read($fid) {
	
	global $conf, $forumlist;
	if($conf['cache']['enable']) {
		return empty($forumlist[$fid]) ? array() : $forumlist[$fid];
	} else {
		$forum = forum__read($fid);
		forum_format($forum);
		return $forum;
	}
	
}

// 关联数据删除
function forum_delete($fid) {
	//  把板块下所有的帖子都查找出来，此处数据量大可能会超时，所以不要删除帖子特别多的板块
	$cond = array('fid'=>$fid);
	$threadlist = db_find('thread', $cond, array(), 1, 1000000, '', array('tid', 'uid'));
	
	
	
	foreach ($threadlist as $thread) {
		thread_delete($thread['tid']);
	}
	
	$r = forum__delete($fid);
	
	forum_access_delete_by_fid($fid);
	
	forum_list_cache_delete();
	
	return $r;
}

function forum_find($cond = array(), $orderby = array('rank'=>-1), $page = 1, $pagesize = 1000) {
	
	$forumlist = forum__find($cond, $orderby, $page, $pagesize);
	if($forumlist) foreach ($forumlist as &$forum) forum_format($forum);
	
	return $forumlist;
}

// ------------> 其他方法

function forum_format(&$forum) {
	global $conf;
	if(empty($forum)) return;
	
	
	
	$forum['create_date_fmt'] = date('Y-n-j', $forum['create_date']);
	$forum['icon_url'] = $forum['icon'] ? $conf['upload_url']."forum/$forum[fid].png" : 'view/img/forum.png';
	$forum['accesslist'] = $forum['accesson'] ? forum_access_find_by_fid($forum['fid']) : array();
	$forum['modlist'] = array();
	if($forum['moduids']) {
		$modlist = user_find_by_uids($forum['moduids']);
		foreach($modlist as &$mod) $mod = user_safe_info($mod);
		$forum['modlist'] = $modlist;
	}
	
}

function forum_count($cond = array()) {
	
	$n = db_count('forum', $cond);
	
	return $n;
}

function forum_maxid() {
	
	$n = db_maxid('forum', 'fid');
	
	return $n;
}

// 从缓存中读取 forum_list 数据x
function forum_list_cache() {
	global $conf, $forumlist;
	$forumlist = cache_get('forumlist');
	
	
	
	if($forumlist === NULL) {
		$forumlist = forum_find();
		cache_set('forumlist', $forumlist, 60);
	}
	
	return $forumlist;
}

// 更新 forumlist 缓存
function forum_list_cache_delete() {
	global $conf;
	static $deleted = FALSE;
	if($deleted) return;
	
	
	
	cache_delete('forumlist');
	$deleted = TRUE;
	
}

// 对 $forumlist 权限过滤，查看权限没有，则隐藏
function forum_list_access_filter($forumlist, $gid, $allow = 'allowread') {
	global $conf, $grouplist;
	if(empty($forumlist)) return array();
	if($gid == 1) return $forumlist;
	$forumlist_filter = $forumlist;
	$group = $grouplist[$gid];
	
	
	
	foreach($forumlist_filter as $fid=>$forum) {
		if(empty($forum['accesson']) && empty($group[$allow]) || !empty($forum['accesson']) && empty($forum['accesslist'][$gid][$allow])) {
			unset($forumlist_filter[$fid]);
			unset($forumlist_filter[$fid]['modlist']);
		}
		unset($forumlist_filter[$fid]['accesslist']);
	}
	
	return $forumlist_filter;
}

function forum_filter_moduid($moduids) {
	$moduids = trim($moduids);
	if(empty($moduids)) return '';
	$arr = explode(',', $moduids);
	$r = array();
	foreach($arr as $_uid) {
		$_uid = intval($_uid);
		$_user = user_read($_uid);
		if(empty($_user)) continue;
		if($_user['gid'] > 4) continue;
		$r[] = $_uid;
	}
	return implode(',', $r);
}


function forum_safe_info($forum) {
	
	//unset($forum['moduids']);
	
	return $forum;
}



?>