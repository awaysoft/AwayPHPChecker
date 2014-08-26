<?php
/*  @Name:AwayPHPChecker
    @Author:Tom<tom@awaysoft.com>
    @LastModify:2014-08-20
    @Description:AwayPHPChecker是一个PHP安全工具，它可以校验php文件，检查常见危险
    @Copyright:本程序采用Apache 2.0授权协议
*/

/* 密码，如果不需要密码则留空 */
define('PASSWORD', '1234');
/* 需要检查的文件后缀，使用|分隔 */
define('EXTENSION', '.php|.inc');
/* 需要检查的路径 */
define('RUNDIRECTORY', dirname(__FILE__));
/* 每次处理的文件数目 */
define('MAKEFILENUM', 100);

/* 首页控制器 */
function IndexController() {
    template('Index');
}

/* 首页模板 */
function IndexTemplate($args) {
    template('Header');
    $html = <<<EOT
    <a href='?c=hash'>创建目录文件hash列表</a> <br />
    <a href='?c=checkHash'>检查目录文件hash是否正确</a> <br />
    <a href='?c=check'>检查后门</a>
EOT;
    echo $html;
    template('Footer');
}

/* 创建目录文件hash初始控制器 */
function HashController() {
    $args = array('title' => '创建目录文件hash列表');
    template('Hash', $args);
}

/* 创建目录文件hash模板 */
function HashTemplate($args) {
    template('Header', $args);
    
    $html = <<<EOT
    请输入想要保存hash清单的文件名：
    <form action='?c=doHash' method='post'>
        <input type='text' name='filename' value='hash.dat' />
        <input type='submit' value='开始' />
    </form>    
EOT;
    echo $html;
    
    template('Footer');
}

/* 创建目录文件 */
function DoHashController() {
    $args = array('title' => '创建目录文件hash列表');
    template('Header', $args);
    $file_name = post('filename');
    if (!$file_name) {
        if ($_SESSION['file_name']) {
            $file_name = $_SESSION['file_name'];
        } else {
            $file_name = 'hash.dat';
        }
    }
    $_SESSION['file_name'] = $file_name;
    echo "hash列表将保存在：{$file_name}<br />";
    
    $step = (int)get('step');
    $step = ($step < 0 ? 0 : $step);
    if ($step === 0) {
        /* 搜索文件列表 */
        $file_list = search_file();
        $file_count = count($file_list);
        if ($file_count > 0) {
            /* 创建hash文件 */
            $hash_list = array();
            $file_content = json_encode($hash_list);
            file_put_contents($file_name, $file_content);
            echo '建立文件列表完成，总共找到' . count($file_list) . '个需要hash的文件';
            jump('?c=DoHash&step=1');
        } else {
            echo '文件搜索完成，未找到需要hash的文件';
        }
    } else {
        /* 初始化各变量 */
        $start_num = ($step - 1) * MAKEFILENUM + 1;
        $end_num = $step * MAKEFILENUM;
        $file_list = $_SESSION['file_list'];
        $file_count = count($file_list);
        if ($end_num > $file_count) {
            $end_num = $file_count;
        }
        /* 读取hash文件 */
        $file_content = file_get_contents($file_name);
        $hash_list = json_decode($file_content, TRUE);
        if (!$hash_list) {
            $hash_list = array();
        }
        echo "总共需要hash{$file_count}个文件，正在处理第{$start_num}到{$end_num}个文件<br />";
        for ($i = $start_num - 1; $i < $end_num; ++$i) {
            $afile_name = $file_list[$i];
            $hash = sha1($afile_name);
            $hash_list[$afile_name] = array(
                'sha1' => $hash
            );
        }
        $file_content = json_encode($hash_list);
        file_put_contents($file_name, $file_content);
        
        echo "num:" . count($hash_list) . "<br />";
        
        /* 如果不是列表最后，跳转到下一步 */
        if ($end_num < $file_count) {
            $step ++;
            jump("?c=DoHash&step={$step}");
        } else {
            echo '文件hash完成！<br />';
            echo '<a href="?">返回</a>';
        }
    }
    
    template('Footer');
}

/* 搜索指定目录中所有符合后缀的文件 */
function search_file() {
    /* 文件列表结果 */
    $file_list = array();
    /* 用于广度优先搜索的临时目录列表 */
    $dir_list = array(RUNDIRECTORY);
    /* 需要搜索的后缀数组 */
    $extensions = explode('|', EXTENSION);
    while (count($dir_list) > 0) {
        /* 获取当前目录列表第一项 */
        $dir_name = array_shift($dir_list);
        $dir = opendir($dir_name);
        if ($dir !== FALSE) {
            while(($file_name = readdir($dir))) {
                /* 排除.和.. */
                if ($file_name == '.' || $file_name == '..') {
                    continue;
                }
                $real_name = $dir_name . DIRECTORY_SEPARATOR . $file_name;
                if (is_dir($real_name)) {
                    array_push($dir_list, $real_name);
                } else {
                    /* 获取文件后缀 */
                    $pathinfo = pathinfo($real_name);
                    $ext = '.' . strtolower($pathinfo['extension']);
                    /* 符合后缀的文件 */
                    if (in_array($ext, $extensions)) {
                        array_push($file_list, $real_name);
                    }
                }
            }
        }
        closedir($dir);
    }
    $_SESSION['file_list'] = $file_list;
    return $file_list;
}

/*  跳转到指定的url地址
    @url: string, 需要跳转到的地址
*/
function jump($url) {
    $html = <<<EOT
<div>正在跳转到：{$url}</div>
<script type='text/javascript'>
    setTimeout(function(){
        location.href='{$url}';
    }, 1000);
</script>
EOT;
    echo $html;
}


/* 默认头模板 */
function HeaderTemplate($args) {
    if (!$args || !$args['title']) {
        $args['title'] = 'AwayPHPChecker';
    }
    $html = <<<EOT
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="zh">
<head>
  <meta charset="utf-8">
  <title>{$args['title']}</title>
</head>
<body>
EOT;
    echo $html;
}

/* 默认尾模板 */
function FooterTemplate($args) {
    $html = <<<EOT
</body>
</html>
EOT;
    echo $html;
}

/* 登录控制器 */
function LoginController() {
    $password = post('password');
    if ($password === PASSWORD) {
        $_SESSION['password'] = $password;
        header('location:?c=index');
    } else {
        $args = array('msg' => '密码错误');
        template('Login', $args);
    }
}

/* 登录模板 */
function LoginTemplate($args) {
    $args['title'] = '登录';
    if (!$args['msg']) {
        $args['msg'] = '';
    }
    template('Header', $args);
    $html = <<<EOT
    <div>{$args['msg']}</div>
    <form action='?c=login' method='post'>
        <input type='password' name='password' />
        <input type='submit' value='登录' />
    </form>    
EOT;
    echo $html;
    template('Footer', $args);    
}

/* 初始化函数 */
function init() {
    session_start();
    if (PASSWORD !== '' && get('c') !== 'login') {
        if ($_SESSION['password'] !== PASSWORD) {
            template('Login');
            exit();
        }
    }
}

/* 框架运行函数 */
function run() {    
    /* 初始化 */
    init();
    /* 获取控制器 */
    if (param_count() > 0) {
        /* 获取命令模式控制器 */
        $controller = param_get(1);
    } else {
        /* 获取网页模式 */
        $controller = get('c');
    }
    if (!$controller) {
        $controller = 'Index';
    }
    
    /* 移交控制权到相应的控制器 */
    controller($controller);
}

/* 模板输出接口函数
    @name: string, 模板名称
    @args: mixed, 传递到模板的参数，建议用关联数组
 */
function template($name, $args = '') {
    $templateName = $name . 'Template';
    if (function_exists($templateName)) {
        $templateName($args);
    } else {
        template('Error', "模板函数{$templateName}未找到！");
    }
}

/* 控制器接口函数
    @name: string, 控制器名称
 */
function controller($name) {
    $controllerName = $name . 'Controller';
    if (function_exists($controllerName)) {
        $controllerName();
    } else {
        template('Error', "控制器函数{$controllerName}未找到！");
    }
}

/*  程序打开参数个数 */
function param_count() {
    global $argc;
    return $argc - 1;
}

/*  获取程序参数 
    @index: integer, 参数的位置
*/
function param_get($index) {
    global $argc, $argv;
    if ($index > $argc) {
        return '';
    } else {
        return $argv[$index];
    }
}

/*  GET方法
    @name: string, GET参数
    @filter: 过滤函数
    @default: 默认值
*/
function get($name, $filter = 'htmlspecialchars', $default = '') {
    $result = $_GET[$name];
    if (!isset($result)) {
        $result = $default;
    }
    return $filter($result);
}

/*  POST方法
    @name: string, GET参数
    @filter: 过滤函数
    @default: 默认值
*/
function post($name, $filter = 'htmlspecialchars', $default = '') {
    $result = $_POST[$name];
    if (!isset($result)) {
        $result = $default;
    }
    return $filter($result);
}

/* 默认输出错误信息函数 */
function ErrorTemplate($args) {
    $argsObj = array('title' => '错误');
    template('Header', $argsObj);
    echo $args;
    template('Footer');
}

/* 运行框架 */
run();

