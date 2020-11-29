<?php

//основные переменные и функции низкого уровня
require_once("engine/vars/global.inc.php");

if (DEBUGGING) { error_reporting(E_ALL); } else { error_reporting(0); }

require_once("engine/vars/accessory.inc.php");
require_once("engine/kernel/functions.inc.php");
require_once("engine/kernel/strings.inc.php");
require_once("engine/kernel/mysql.inc.php");

//вспомогательные функции высокого уровня
require_once("engine/kernel/accessory.inc.php");

//классы
require_once("modules/navigator/navigator.class.php");
require_once("modules/users/users.class.php");
require_once("modules/structure/structure.class.php");
require_once("modules/constants/constants.class.php");
require_once("modules/styles/styles.class.php");

//специализированные модули
require_once("engine/kernel/maket.inc.php");
require_once("engine/kernel/tags.inc.php");

define("SCRIPT_STARTTIME",getmicrotime());

if (!mysql_openconnection(CONTINUE_ON_ERROR)) { 
	halt('Не удается подключиться к MySQL',PROJECT_TITLE.' '.VIRTUAL_PATH."\n</br>".SQL_HOST.'->'.SQL_DATABASE.': '.mysql_error(), SEND_ERROR_MESSAGE); 
}
//создаваться глобальные переменные
$current = array();
$thispage = array();

parse_request_uri();

define("REQUEST_URI", $_SERVER["REQUEST_URI"]);

$current['theme']='default';
$current['maket']='standart';
$current['headers'] = $default_headers;

//инициализируем основные объекты и модули
if ((DEBUGGING) && (@$_GET['install']=='all')) {
	include_once("modules/navigator/do!install.inc.php");
	include_once("modules/navigator/!open.inc.php"); 

	include_once("modules/users/do!install.inc.php");
	include_once("modules/users/!open.inc.php"); 

	include_once("modules/structure/do!install.inc.php");
	include_once("modules/structure/!open.inc.php");

	include_once("modules/styles/do!install.inc.php");
	include_once("modules/styles/!open.inc.php"); 

	include_once("modules/constants/do!install.inc.php");
	include_once("modules/constants/!open.inc.php"); 
} else {
	include_once("modules/navigator/!open.inc.php"); 
	include_once("modules/users/!open.inc.php"); 
	include_once("modules/structure/!open.inc.php");
	include_once("modules/styles/!open.inc.php"); 
	include_once("modules/constants/!open.inc.php"); 
}

//если указан параметр $_GET['action']=='install'. Выполняется !install.inc.php
if ((DEBUGGING) && (isset($_GET['install']))) {
	$modules = read_modules_list('install');
	foreach ($modules as $current_module_name => $current_module) {
		if (($_GET['install']=='all') || ($_GET['install']==$current_module_name)) {
			if (file_exists("modules/$current_module_name/do!install.inc.php")) {
				include_once("modules/$current_module_name/do!install.inc.php");
			} else {
				print_warning_message('Ошибка в настройках системы. Не удалось прочитать конфигурационный файл.','Ошибка в файле списка модулей или указанный модуль не существует. Модуль: '.$current_module_name.'. Файл '.'modules/'.$current_module_name.'/do!install.inc.php не существует!');
			}
		}
	}
}

//Выполняется !autorun.inc.php для всех модулей.
$modules = read_modules_list('autorun');
foreach ($modules as $current_module_name => $current_module) {
	if (file_exists("modules/$current_module_name/!autorun.inc.php")) {
		require_once("modules/$current_module_name/!autorun.inc.php");
	} else {
		print_warning_message('Ошибка в настройках системы. Не удалось прочитать конфигурационный файл.','Ошибка в файле списка модулей или указанный модуль не существует. Модуль: '.$current_module_name.'. Файл '.'modules/'.$current_module_name.'/!autorun.inc.php не существует!');
	}
}

if ((DEBUGGING) && (isset($_GET['install']))) { die('<br><b>Установка завершена</b><br>'); }

if (!include('modules/cache/before.inc.php')) {

	//определяем какой модуль запускать
	$current['nid']=$navigator->find_by_path($current['url']['path'],'?'.$current['url']['args']);
	if (is_array($current['nid'])) { // указатель навигатора найден
		$points = $structure->get_index('link="nid:'.$current['nid']['id'].'"','','');
		if ((is_array($points)) && (count($points)>0)) {
			$current['structure']['points'] = $points;
			$branch='';
			foreach ($current['structure']['points'] as $name => $point) {
				$current['structure']['points'][$name] = $structure->prepare_to_output($point);
				$branch.=$point['branch'].',';
			}
			$current['structure']['branch'] = $branch;
		} else {
			$current['structure']['points'] = false; 
		}
		unset($points); unset($point);
		$menulink = tag_to_array($current['nid']['link']);  
		if (!is_array($menulink)) {
			halt('Ошибка в настройках!','Ошибочный параметр link в указателе навигатора '.$current['nid']['id']);
		}
		$current_module_name = $menulink['module'];
		$current_module_file = $menulink['file'];
		if (strlen($current_module_file)==0) { $current_module_file='index'; }
		if (file_exists("modules/$current_module_name/do!$current_module_file.inc.php")) {
				include("modules/$current_module_name/do!$current_module_file.inc.php");
		} else {
			halt_404('','url='.$current['url']['path'].'<br> Ошибка в конфигурации! Указатель навигатора '.$current['nid']['id'].' <br> Модуль '.$current_module_name.' или файл do!'.$current_module_file.'.inc.php не найдены');
		}
	} else { // указатель навигатора не найден
		// пробуем модуль/файл
		$url = $current['url']['path'];
		if (strpos($url,'/')===false) {
			$current_module_name = $url;
			$current_module_file = 'index';
		} else {
			$current_module_name = cut_before_substr($url,'/');
			$url=substr($url,1);
			$current_module_file = cut_before_substr($url,'/');
			$url=substr($url,1);
			$current['url']['additional_path']=$url;
			if (strlen($current_module_file)==0) { $current_module_file='index'; }
		}
		if ((file_exists("modules/$current_module_name/do!$current_module_file.inc.php"))) {
			include("modules/$current_module_name/do!$current_module_file.inc.php");
		} else {
			halt_404('','url='.$current['url']['path'].' <br> Модуль '.$current_module_name.' или файл do!'.$current_module_file.'.inc.php не найдены');
		}
	}

	//загрузить макет, если один из модулей его не загрузил
	if (!isset($current['loaded_maket'])) {
		$current['loaded_maket']=read_maket_to_str($current['theme'], $current['maket'], true, true);
	}

	//все не проставленные значения $thispage заполняем значениями "поумолчанию" из *-places.htm
	$default_places = parse_maket_places($current['theme'], $current['maket']);
	$thispage = merge_array($default_places, $thispage);
	
	/*if ((strpos(getenv('HTTP_USER_AGENT'),'Yandex')!==false) && (strlen($current['url']['path'])<=1)) {
		$thispage['content']='<noindex>'.$thispage['content'].'</noindex>';
	}*/

	//постепенный вывод макета с обработкой тегов
	send_headers($current['headers']);
	$current['thispage'] = update_tags($current['loaded_maket'],true);
	//страница выведена. в $current['thispage'] находиться результат работы

	if (USE_CACHE) { include('modules/cache/after.inc.php'); }

}

//закрытие
require_once("modules/structure/!close.inc.php");
require_once("modules/users/!close.inc.php");
require_once("modules/navigator/!close.inc.php");


mysql_closeconnection(DEBUGGING);

$runtime = Round(getmicrotime() - SCRIPT_STARTTIME,2);
if (DEBUGGING) {
	$pagestat = analize_content($current['thispage']);
	print "<hr>\n<small>Время выполнения скрипта: <b>$runtime</b> секунд  - <b>".CACHED."</b>. Пользователь: <b>".USER_NAME."</b> (авторизировался:".USER_AUTHORIZED."; администратор:".USER_ADMINISTRATOR.")<br>\n";
	print $pagestat."</small><br>\n";
}
print "\n<!--runtime:$runtime (Last-Modified:".date('Y-m-d H:i:s',strtotime($current['headers']['Last-Modified'])).", Expires:".date('Y-m-d H:i:s',strtotime($current['headers']['Expires'])).") ".CACHED."-->";
print "\n".'<!--'.getenv('HTTP_USER_AGENT').'-->'; if (strpos(getenv('HTTP_USER_AGENT'),'Yandex')!==false) { print '<!-- bot -->'; }
?>