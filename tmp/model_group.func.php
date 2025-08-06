<?php



// ------------> 最原生的 CURD，无关联其他数据。

function group__create($arr) {
	
	$r = db_create('group', $arr);
	
	return $r;
}

function group__update($gid, $arr) {
	
	$r = db_update('group', array('gid'=>$gid), $arr);
	
	return $r;
}

function group__read($gid) {
	
	$group = db_find_one('group', array('gid'=>$gid));
	
	return $group;
}

function group__delete($gid) {
	
	$r = db_delete('group', array('gid'=>$gid));
	
	return $r;
}

function group__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 1000) {
	
	$grouplist = db_find('group', $cond, $orderby, $page, $pagesize, 'gid');
	
	return $grouplist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function group_create($arr) {
	
	$r = group__create($arr);
	group_list_cache_delete();
	forum_access_padding($arr['gid'], TRUE); // 填充
	
	return $r;
}

function group_update($gid, $arr) {
	
	$r = group__update($gid, $arr);
	group_list_cache_delete();
	
	return $r;
}

function group_read($gid) {
	
	$group = group__read($gid);
	group_format($group);
	
	return $group;
}

function group_delete($gid) {
	
	$r = group__delete($gid);
	group_list_cache_delete();
	forum_access_padding($gid, FALSE); // 删除
	
	return $r;
}

function group_find($cond = array(), $orderby = array('gid'=>1), $page = 1, $pagesize = 1000) {
	
	$grouplist = group__find($cond, $orderby, $page, $pagesize);
	if($grouplist) foreach ($grouplist as &$group) group_format($group);
	
	return $grouplist;
}

// ------------> 其他方法

function group_format(&$group) {
	
	
}

function group_name($gid) {
	global $grouplist;
	return isset($grouplist[$gid]['name']) ? $grouplist[$gid]['name'] : '';
}


function group_count($cond = array()) {
	$n = db_count('group', $cond);
	
	return $n;
}

function group_maxid() {
	
	$n = db_maxid('group', 'gid');
	
	return $n;
}

// 从缓存中读取 forum_list 数据
function group_list_cache() {
	$grouplist = cache_get('grouplist');
	
	if($grouplist === NULL || $grouplist === FALSE) {
		$grouplist = group_find();
		cache_set('grouplist', $grouplist);
	}
	
	return $grouplist;
}

// 更新 forumlist 缓存
function group_list_cache_delete() {
	
	$r = cache_delete('grouplist');
	
	return $r;
}




?>