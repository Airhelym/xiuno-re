<?php



// ------------> 最原生的 CURD，无关联其他数据。

function thread__create($arr) {
	
	$r = db_insert('thread', $arr);
	
	return $r;
}

function thread__update($tid, $arr) {
	
	$r = db_update('thread', array('tid'=>$tid), $arr);
	
	return $r;
}

function thread__read($tid) {
	
	$thread = db_find_one('thread', array('tid'=>$tid));
	
	return $thread;
}

function thread__delete($tid) {
	
	$r = db_delete('thread', array('tid'=>$tid));
	
	return $r;
}

function thread__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	
	$arrlist = db_find('thread', $cond, $orderby, $page, $pagesize, 'tid', array('tid'));
	if(empty($arrlist)) return array();
	
	$tidarr = arrlist_values($arrlist, 'tid');
	$threadlist = db_find('thread', array('tid'=>$tidarr), $orderby, 1, $pagesize, 'tid');
	
	
	return $threadlist;
}

function thread_create($arr, &$pid) {
	global $conf, $gid;
	$fid = $arr['fid'];
	$uid = $arr['uid'];
	$subject = $arr['subject'];
	$message = $arr['message'];
	$time = $arr['time'];
	$longip = $arr['longip'];
	$doctype = $arr['doctype'];
	
	# 论坛帖子数据，一页显示，不分页。
	$post = array(
		'tid'=>0,
		'isfirst'=>1,
		'uid'=>$uid,
		'create_date'=>$time,
		'userip'=>$longip,
		'message'=>$message,
		'doctype'=>$doctype,
	);
	
	
	
	$pid = post__create($post, $gid);
	if($pid === FALSE) return FALSE;
	
	// 创建主题
	$thread = array (
		'fid'=>$fid,
		'subject'=>$subject,
		'uid'=>$uid,
		'create_date'=>$time,
		'last_date'=>$time,
		'firstpid'=>$pid,
		'lastpid'=>$pid,
		'userip'=>$longip,
	);
	
	
	
	$tid = thread__create($thread);
	if($tid === FALSE) {
		post__delete($pid);
		return FALSE;
	}
	// 板块总数+1, 用户发帖+1
	
	// 更新统计数据
	$uid AND user__update($uid, array('threads+'=>1));
	forum__update($fid, array('threads+'=>1, 'todaythreads+'=>1));
	
	// 关联
	post__update($pid, array('tid'=>$tid), $tid);

	// 我参与的发帖
	$uid AND mythread_create($uid, $tid);
	
	// 关联附件
	attach_assoc_post($pid);
	
	// 全站发帖数
	runtime_set('threads+', 1);
	runtime_set('todaythreads+', 1);
	
	// 更新板块信息。
	forum_list_cache_delete();
	
	
	
	return $tid;
}

// 不要在大循环里调用此函数！比较耗费资源。
function thread_update($tid, $arr) {
	global $conf;
	$thread = thread__read($tid);
	
	
	
	if(isset($arr['subject']) && $arr['subject'] != $thread['subject']) {
		$thread['top'] > 0 AND thread_top_cache_delete();
	}
	
	// 更改 fid, 移动主题，相关资源也需要更新
	if(isset($arr['fid']) && $arr['fid'] != $thread['fid']) {
		forum__update($arr['fid'], array('threads+'=>1));
		forum__update($thread['fid'], array('threads-'=>1));
		thread_top_update_by_tid($tid, $arr['fid']);
	}
	
	if(!$arr) return TRUE;
	
	$r = thread__update($tid, $arr);
	
	
	return $r;
}

// views + 1
function thread_inc_views($tid, $n = 1) {
	
	global $conf;
	if(!$conf['update_views_on']) return TRUE;
	$sqladd = strpos($conf['db']['type'], 'mysql') === FALSE ? '' : ' LOW_PRIORITY';
	$r = db_exec("UPDATE$sqladd `bbs_thread` SET views=views+$n WHERE tid='$tid'");
	
	return $r;
}

function thread_read($tid) {
	
	$thread = thread__read($tid);
	thread_format($thread);
	
	return $thread;
}

// 从缓存中读取，避免重复从数据库取数据，主要用来前端显示，可能有延迟。重要业务逻辑不要调用此函数，数据可能不准确，因为并没有清理缓存，针对 request 生命周期有效。
function thread_read_cache($tid) {
	
	static $cache = array(); // 用静态变量只能在当前 request 生命周期缓存，要跨进程，可以再加一层缓存： memcached/xcache/apc/
	if(isset($cache[$tid])) return $cache[$tid];
	$cache[$tid] = thread_read($tid);
	
	return $cache[$tid];
}

// 删除主题
function thread_delete($tid) {
	global $conf;
	$thread = thread__read($tid);
	if(empty($thread)) return TRUE;
	$fid = $thread['fid'];
	$uid = $thread['uid'];
	
	
	
	// 删除所有回帖，同时更新 posts 统计数
	$n = post_delete_by_tid($tid);
	
	// 删除我的主题
	$uid AND mythread_delete($uid, $tid);
	
	// 清除相关缓存
	forum_list_cache_delete();
	
	$r = thread__delete($tid);
	if($r === FALSE) return FALSE;
	
	// 更新统计
	forum__update($fid, array('threads-'=>1));
	user__update($uid, array('threads-'=>1));
	
	// 全站统计
	runtime_set('threads-', 1);
	
	
	
	return $r;
}

function thread_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$threadlist = thread__find($cond, $orderby, $page, $pagesize);
	if($threadlist) foreach ($threadlist as &$thread) thread_format($thread);
	
	return $threadlist;
}

// $order: tid/lastpid
// 按照: 发帖时间/最后回复时间 倒序，不包含置顶帖
function thread__find_by_fid($fid, $page = 1, $pagesize = 20, $order = 'lastpid') {
	global $conf, $forumlist, $runtime;
	$forum = $fid ? $forumlist[$fid] : array();
	$threads = empty($forum) ? $runtime['threads'] : $forum['threads'];
	
	
	
	$cond = array();
	$fid AND $cond['fid'] = $fid;
	
	$desc = TRUE;
	$limitpage = 50000; // 如果需要防止 CC 攻击，可以调整为 5000
	if($page > 100) {
		$totalpage = ceil($threads / $pagesize);
		$halfpage = ceil($totalpage / 2);
		if($halfpage > $limitpage && $page < ($totalpage - $limitpage)) {
			$page = $limitpage;
		}
		if($page > $halfpage) {
			$page = max(1, $totalpage - $page + 1) ;
			$threadlist = thread_find($cond, array($order=>1), $page, $pagesize);
			$threadlist = array_reverse($threadlist, TRUE);
			$desc = FALSE;
		}
	}
	if($desc) {
		$orderby = array($order=>-1);
		$threadlist = thread_find($cond, $orderby, $page, $pagesize);
	}
	
	
	
	return $threadlist;
}

// $order: tid/lastpid
// 按照: 发帖时间/最后回复时间 倒序，包含置顶帖
function thread_find_by_fid($fid, $page = 1, $pagesize = 20, $order = 'lastpid') {
	global $conf, $forumlist, $runtime;

	

	$threadlist = thread__find_by_fid($fid, $page, $pagesize, $order);

	
	
	// 查找置顶帖
	if($order == $conf['order_default'] && $page == 1) {
		$toplist3 = thread_top_find(0);
		$toplist1 = thread_top_find($fid);
		//$toplist = thread_top_find($fid);
		$threadlist = $toplist3 + $toplist1 + $threadlist;
	}
	
	
	return $threadlist;
}

// 从多个版块获取列表数据
function thread_find_by_fids($fids, $page = 1, $pagesize = 20, $order = 'lastpid', $threads = FALSE) {
	
	
	
	$threadlist = thread_find(array('fid'=>$fids), array($order=>-1), $page, $pagesize);
	
	
	
	return $threadlist;
}

// 默认搜索标题
function thread_find_by_keyword($keyword) {
	
	$threadlist = db_find('thread', array('subject'=>array('LIKE'=>$keyword)), array(), 1, 60);
	$threadlist = arrlist_multisort($threadlist, 'tid', FALSE); // 用 PHP 排序，mysql 排序消耗太大。
	if($threadlist) {
		foreach ($threadlist as &$thread) {
			thread_format($thread);
			$thread['subject'] = post_highlight_keyword($thread['subject'], $keyword);
		}
	}
	
	return $threadlist;
}


function thread_format(&$thread) {
	
	global $conf, $forumlist;
	if(empty($thread)) return;
	
	
	
	$thread['create_date_fmt'] = humandate($thread['create_date']);
	$thread['last_date_fmt'] = humandate($thread['last_date']);
	
	$user = user_read_cache($thread['uid']);
	$thread['username'] = $user['username'];
	$thread['user_avatar_url'] = $user['avatar_url'];
	$thread['user'] = $user;
	
	$forum = isset($forumlist[$thread['fid']]) ? $forumlist[$thread['fid']] : array('name'=>'');
	$thread['forumname'] = $forum['name'];
	
	if($thread['last_date'] == $thread['create_date']) {
		//$thread['last_date'] = 0;
		$thread['last_date_fmt'] = '';
		$thread['lastuid'] = 0;
		$thread['lastusername'] = '';
	} else {
		$lastuser = $thread['lastuid'] ? user_read_cache($thread['lastuid']) : array();
		$thread['lastusername'] = $thread['lastuid'] ? $lastuser['username'] : lang('guest');
	}
	
	$thread['url'] = "thread-$thread[tid].htm";
	$thread['user_url'] = "user-$thread[uid]".($thread['uid'] ? '' : "-$thread[firstpid]").".htm";
	
	$thread['top_class'] = $thread['top'] ? 'top_'.$thread['top'] : '';

	$thread['pages'] = ceil($thread['posts'] / $conf['postlist_pagesize']);
		
	
}

function thread_format_last_date(&$thread) {
	
	if($thread['last_date'] != $thread['create_date']) {
		$thread['last_date_fmt'] = humandate($thread['last_date']);
	} else {
		$thread['create_date_fmt'] = humandate($thread['create_date']);
	}
	
}

function thread_count($cond = array()) {
	
	$n = db_count('thread', $cond);
	
	return $n;
}

function thread_maxid() {
	
	$n = db_maxid('thread', 'tid');
	
	return $n;
}

function thread_safe_info($thread) {
	
	unset($thread['userip']);
	if(!empty($thread['user'])) {
		$thread['user'] = user_safe_info($thread['user']);
	}
	
	return $thread;
}

function thread_get_level($n, $levelarr) {
	
	foreach($levelarr as $k=>$level) {
		if($n <= $level) return $k;
	}
	
	return $k;
}


// 对 $threadlist 权限过滤
function thread_list_access_filter(&$threadlist, $gid) {
	global $conf, $forumlist;
	if(empty($threadlist)) return;
	
	
	
	foreach($threadlist as $tid=>$thread) {
		if(empty($forumlist[$thread['fid']]['accesson'])) continue;
		if($thread['top'] > 0) continue;
		if(!forum_access_user($thread['fid'], $gid, 'allowread')) {
			unset($threadlist[$tid]);
		}
	}
	
}

function thread_find_by_tids($tids, $order = array('lastpid'=>-1)) {
	
	//$start = ($page - 1) * $pagesize;
	//$tids = array_slice($tids, $start, $pagesize);
	if(!$tids) return array();
	$threadlist = db_find('thread', array('tid'=>$tids), $order, 1, 1000, 'tid');
	if($threadlist) foreach($threadlist as &$thread) thread_format($thread);
	
	return $threadlist;
}

// 查找 lastpid
function thread_find_lastpid($tid) {
	$arr = db_find_one("post", array('tid'=>$tid), array('pid'=>-1), array('pid'));
	$lastpid = empty($arr) ? 0 : $arr['pid'];
	return $lastpid;
}

// 更新最后的 uid pid
function thread_update_last($tid) {
	$lastpid = thread_find_lastpid($tid);
	if(empty($lastpid)) return;
	
	$lastpost = post__read($lastpid);
	if(empty($lastpost)) return;
	
	$r = thread__update($tid, array('lastpid'=>$lastpid, 'lastuid'=>$lastpost['uid'], 'last_date'=>$lastpost['create_date']));

	return $r;
}



?>