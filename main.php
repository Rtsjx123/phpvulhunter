<?php

require_once 'global.php';

$smarty = new Smarty_setup();

/**
 * 处理单个文件的静态检测
 * 输入PHP文件
 * @param string $path
 */
function load_file($path){
	$cfg = new CFGGenerator() ;
	$cfg->getFileSummary()->setPath($path);
	
	$visitor = new MyVisitor() ;
	$parser = new PhpParser\Parser(new PhpParser\Lexer\Emulative) ;
	$traverser = new PhpParser\NodeTraverser ;

	$code = file_get_contents($path);
	$stmts = $parser->parse($code) ;
	$traverser->addVisitor($visitor) ;
	$traverser->traverse($stmts) ;
	$nodes = $visitor->getNodes() ;
	
	$pEntryBlock = new BasicBlock() ;
	$pEntryBlock->is_entry = true ;
	
	//开始分析
	$cfg->CFGBuilder($nodes, NULL, NULL, NULL) ;
}

/**
 * 将结果集转为前端的模式
 * @param ResultContext $resContext
 */
function convertResults($resContext){
    $ret = array() ;
    $resArr = $resContext->getResArr() ;
    foreach($resArr as $record){
        $item = array() ;
        $record = $record->getRecord() ;
        $item['type'] = $record['type'] ;
        $item['node_path'] = $record['node_path'] ;
        $item['var_path'] = $record['var_path'] ;
        
        //整理node代码
        $node = $record['node'] ;
        $node_item = array() ;
        if($node instanceof Symbol){
            $node_start = $node->getValue()->getAttribute('startLine') ;
            $node_end = $node->getValue()->getAttribute('endLine') ;
        }else{
            $node_start = $node->getAttribute('startLine') ;
            $node_end = $node->getAttribute('endLine') ;
        }
        
        $node_item['line'] = $node_start . "|" . $node_end ;
        $node_item['code'] = FileUtils::getCodeByLine($record['node_path'], $node_start, $node_end) ;
        $item['node'] = $node_item ;
        
        //整理var代码
        $var = $record['var'] ;
        $var_item = array() ;
        if($var instanceof Symbol){
            $var_start = $var->getValue()->getAttribute('startLine') ;
            $var_end = $var->getValue()->getAttribute('endLine') ;
        }else{
            $var_start = $var->getAttribute('startLine') ;
            $var_end = $var->getAttribute('endLine') ;
        }
        $var_item['line'] = $var_start . "|" . $var_end ;
        $var_item['code'] = FileUtils::getCodeByLine($record['var_path'], $var_start, $var_end) ;
        $item['var'] = $var_item ;
        
        array_push($ret, $item) ;
    }
    return $ret ;
}


if(!isset($_POST['path']) || !isset($_POST['type'])){
	$smarty->display('index.html') ;
	exit() ;
}

$t_start = time();
//1、从web ui中获取并加载项目工程
$project_path = $_POST['path'] ;  //扫描的工程路径
$scan_type = $_POST['type'] ;     //扫描的类型
$encoding = $_POST['encoding'] ;  //CMS的编码   UTF-8 或者  GBK
$scan_type = $scanType = strtoupper($scan_type);


//2、初始化模块
$allFiles = FileUtils::getPHPfile($project_path);
$mainlFiles = FileUtils::mainFileFinder($project_path);
$initModule = new InitModule() ;
$initModule->init($project_path, $allFiles) ;


//3、循环每个文件  进行分析工作
if(is_file($project_path)){
	load_file($project_path) ;
}elseif (is_dir($project_path)){
	$path_list = $mainlFiles;
	foreach ($path_list as $path){
		try{
		    //print_r($path.'<br/>');
			load_file($path) ;
			//传给templates
		}catch(Exception $e){
			continue ;
		}	
	}
}else{
	//请求不合法
	echo "工程不存在!" ;
	exit() ;
}

//4、获取ResultContext  序列化
$results = ResultContext::getInstance() ;
$serialPath = CURR_PATH . '/data/resultConetxtSerialData';

if(($serial_str = file_get_contents($serialPath))!=''){
    $results = unserialize($serial_str) ;
}

file_put_contents($serialPath, serialize($results)) ;

$t_end = time();
$t = $t_end - $t_start;
print_r($t);

//5、处理results 传给template

$template_res = convertResults(ResultContext::getInstance()) ;

$smarty->assign('results',$template_res);
$smarty->display('content.html');


?>