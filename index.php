<?
// Для работы скрипта установите модуль Live API
// http://marketplace.1c-bitrix.ru/solutions/bitrix.liveapi/

// Для запуска скприта через консоль укажите путь к рутовой папке сайта
$_SERVER['DOCUMENT_ROOT'] = '';

ini_set('memory_limit','128M');
ini_set('max_execution_time','0');

date_default_timezone_set('Europe/Moscow');

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include.php");

if ( (bool) CModule::IncludeModule('bitrix.liveapi') !== true) {
	echo ('Install live_api module'."\n");
	die();
}


// Файл с описанием API
define('DATA_FILE',$_SERVER["DOCUMENT_ROOT"].'/bitrix/managed_cache/live_api.data');

// Вспомогательные константы
define('_CONST','const');
define('_EVENTS', 'events');
define('_METHODS','methods');

// Папка для сохранения сниппетов
define('SAVE_DIR',dirname(__FILE__) . '/snippets/');

// Проверка наличия дирки, если нету то создаем
if ( (bool) is_dir(SAVE_DIR) !== true) {
	mkdir(SAVE_DIR) or die('Can"t craete save dir ' . SAVE_DIR);
}

// Проверка наличия файла с данными
if (file_exists(DATA_FILE) !== true) {
	echo 'Run scaner in live_api module';
	die();
}

// Шаблон сниппета
$template = "<snippet>
	<content><![CDATA[#content]]></content>
	<tabTrigger>#trigger</tabTrigger>
	<description>Bitrix #desc</description>
	<scope>source.php</scope>
</snippet>";

include(DATA_FILE);

// Все модули 
$arModules = array_keys($DATA);

// Что бы не возникали повторы
$arExists = array();

// Список модулей для исключения
$arIgnore = array(
	'bitrix.liveapi',
	'd2mg.ordercall',
	'devart.bconsole',
	'devart.editable',
	'bitrix.eshop',
	'eshopapp'
);

echo 'Start parser!'."\n";

foreach ($arModules as $moduleId) {
	// Игнорим "Игнор Лист""
	if (in_array($moduleId, $arIgnore)) continue;

	// Тащим 3 золотые вещи, Константы, события и Методы (функции)
	list($arRes,$arEvt,$arConst) = unserialize($DATA[$moduleId]);
	
	// Перебираем константы
	foreach ($arConst as $key => $value) {
		saveSnippet($moduleId, _CONST, array($key, $value));
	}

	// События
	foreach ($arEvt as $key => $value) {
		saveSnippet($moduleId, _EVENTS, array($key, $value));
	}

	// Методы
	foreach ($arRes as $key => $value) {
		saveSnippet($moduleId, _METHODS, array($key, $value));
	}
}

echo "\n".'ok!'."\n";

function saveSnippet($module, $type, $params)
{
	global $template, $arExists;

	if ( (is_array($params) && count($params) == 0) || (empty($params)) ) {
		return ;
	}
	
	$content = '';
	$trigger = '';
	$desc = '';
	$filename = '';
	
	switch ($type) 
	{
		case _CONST:
			list($content, $desc) = $params;
			$trigger = $content;
			$filename = SAVE_DIR . '/' . $module . '/constant/' . $content . '.sublime-snippet';
			break;

		case _EVENTS:
			list($content, $desc) = $params;
			$trigger = $content;
			$filename = SAVE_DIR . '/' . $module . '/events/' . $content . '.sublime-snippet';
			break;

		case _METHODS:			
			list($content, $args) = $params;
			$desc = 'API';
			$trigger = $content;
			$filename = SAVE_DIR . '/' . $module . '/methods/' . $content . '.sublime-snippet';
			if (isset($args['ARGS']) && trim($args['ARGS']) != '') {
				if (strpos($args['ARGS'], ',') > 0) {
					$args = explode(',', $args['ARGS']);
				} else {
					$args = array( trim($args['ARGS']) );
				}

				$arArgs = '';
				foreach ($args as $id => $arg) {
					if (strpos($arg, '=') > 0 | strpos($arg, '&') > 0) {
						$arg = str_replace('$', '\$', $arg);
					} else {
						$arg = str_replace('$', '', $arg);
					}
					
					$arArgs[] = sprintf('${%d:%s}',$id+1, trim($arg) );
				}

				$content = sprintf('%s(%s)',$content, implode(',', $arArgs));
			} else {
				$content = sprintf('%s()',trim($content));
			}

			break;

		default:
			return ;
			break;
	}

	if (in_array($trigger, $arExists)) return ;
	$arExists[] = trim($trigger);

	$from = array('#content', '#desc', '#trigger');
	$to = array( trim($content), trim($desc), trim($trigger));

	if (is_dir(dirname($filename)) !== true) {
		mkdir(dirname($filename),0777,true);
	}

	$content = str_replace($from, $to, $template);

	file_put_contents($filename, $content);
}

?>