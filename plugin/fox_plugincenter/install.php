<?php
/*
 * 奇狐插件中心 安装文件
 * QQ:77798085
 */

!defined('DEBUG') AND exit('Forbidden');

$modelfilepath = APP_PATH . 'model/plugin.func.php';
$modelbackname = file_backname($modelfilepath);

if(!is_file($modelbackname)){
    // 备份文件
    $r = file_backup($modelfilepath);
    $r == FALSE AND message(-1, '备份文件失败');
    
    // 替换plugin.func.php
    $fox_modelfilepath = APP_PATH . 'plugin/fox_plugincenter/model/plugin.func.php';
    $r = xn_copy($fox_modelfilepath, $modelfilepath);
    $r == FALSE AND message(-1, '替换model文件失败');
}

//清空缓存
cache_truncate();
$runtime = NULL;
rmdir_recusive($conf['tmp_path'], 1);
?>
