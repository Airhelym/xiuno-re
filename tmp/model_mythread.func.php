<?php

// ------------> 关联的 CURD，无关联其他数据。



function mythread_create($uid, $tid) {
	
	if($uid == 0) return TRUE; // 匿名发帖
	$thread = mythread_read($uid, $tid);
	if(empty($thread)) {
		$r = db_create('mythread', array('uid'=>$uid, 'tid'=>$tid));
		return $r;
	} else {
		return TRUE;
	}
	
}

function mythread_read($uid, $tid) {
	
	$mythread = db_find_one('mythread', array('uid'=>$uid, 'tid'=>$tid));
	
	return $mythread;
}

function mythread_delete($uid, $tid) {
	
	$r = db_delete('mythread', array('uid'=>$uid, 'tid'=>$tid));
	
	return $r;
}

function mythread_delete_by_uid($uid) {
	
	$r = db_delete('mythread', array('uid'=>$uid));
	
	return $r;
}

function mythread_delete_by_fid($fid) {
	
	$r = db_delete('mythread', array('fid'=>$fid));
	
	return $r;
}

function mythread_delete_by_tid($tid) {
	
	$r = db_delete('mythread', array('tid'=>$tid));
	
	return $r;
}

function mythread_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$mythreadlist = db_find('mythread', $cond, $orderby, $page, $pagesize);
	
	return $mythreadlist;
}

function mythread_find_by_uid($uid, $page = 1, $pagesize = 20) {
	
	$mythreadlist = mythread_find(array('uid'=>$uid), array('tid'=>-1), $page, $pagesize);
	if(empty($mythreadlist)) return array();
	$threadlist = array();
	foreach ($mythreadlist as &$mythread) {
		$threadlist[$mythread['tid']] = thread_read($mythread['tid']);
	}
	
	return $threadlist;
}



?>