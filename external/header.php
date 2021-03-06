<?php

if (PHP_SAPI == 'cli') {
  $_SERVER['REMOTE_ADDR'] = null;
  $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
}

include(dirname(__FILE__) . '/../xhprof_lib/config.php');

//I'm Magic :)
class visibilitator
{
	public static function __callstatic($name, $arguments)
	{
		$func_name = array_shift($arguments);
		//var_dump($name);
		//var_dump("arguments" ,$arguments);
		//var_dump($func_name);
		if (is_array($func_name))
		{
			list($a, $b) = $func_name;
			if (count($arguments) == 0)
			{
				$arguments = $arguments[0];
			}
			return call_user_func_array(array($a, $b), $arguments);
			//echo "array call  -> $b ($arguments)";
		}else {
			call_user_func_array($func_name, $arguments);
		}
	}
}

// Only users from authorized IP addresses may control Profiling
if ($controlIPs === false || in_array($_SERVER['REMOTE_ADDR'], $controlIPs) || PHP_SAPI == 'cli')
{
  if (isset($_GET['_profile']))
  {
    //Give them a cookie to hold status, and redirect back to the same page
    setcookie('_profile', $_GET['_profile']);
    $newURI = str_replace(array('_profile=1','_profile=0'), '', $_SERVER['REQUEST_URI']);
    header("Location: $newURI");
    exit;
  }

  if (isset($_COOKIE['_profile']) && $_COOKIE['_profile'] || PHP_SAPI == 'cli' && ((isset($_SERVER['XHPROF_PROFILE']) && $_SERVER['XHPROF_PROFILE']) || (isset($_ENV['XHPROF_PROFILE']) && $_ENV['XHPROF_PROFILE'])))
  {
      $_xhprof['display'] = true;
      $_xhprof['doprofile'] = true;
      $_xhprof['type'] = 1;
  }
}


//Certain URLs should never have a link displayed. Think images, xml, etc.
foreach ($exceptionURLs as $url) {
    if (stripos($_SERVER['REQUEST_URI'], $url) !== FALSE)
    {
        $_xhprof['display'] = false;
        header('X-XHProf-No-Display: Trueness');
        break;
    }
}
unset($exceptionURLs);

//Certain urls should have their POST data omitted. Think login forms, other privlidged info
$_xhprof['savepost'] = true;
foreach ($exceptionPostURLs as $url)
{
    if (stripos($_SERVER['REQUEST_URI'], $url) !== FALSE)
    {
        $_xhprof['savepost'] = false;
        break;
    }
}
unset($exceptionPostURLs);

//Determine wether or not to profile this URL randomly
if ($_xhprof['doprofile'] === false)
{
    //Profile weighting, one in one hundred requests will be profiled without being specifically requested
    if (rand(1, $weight) == 1)
    {
        $_xhprof['doprofile'] = true;
        $_xhprof['type'] = 0;
    }
}
unset($weight);

// Certain URLS should never be profiled.
foreach($ignoreURLs as $url){
    if (stripos($_SERVER['REQUEST_URI'], $url) !== FALSE)
    {
        $_xhprof['doprofile'] = false;
        break;
    }
}
unset($ignoreURLs);

unset($url);

// Certain domains should never be profiled.
foreach($ignoreDomains as $domain){
    if (stripos($_SERVER['HTTP_HOST'], $domain) !== FALSE)
    {
        $_xhprof['doprofile'] = false;
        break;
    }
}
unset($ignoreDomains);
unset($domain);

//Display warning if extension not available
if (extension_loaded('xhprof') && $_xhprof['doprofile'] === true) {
    include_once dirname(__FILE__) . '/../xhprof_lib/utils/xhprof_lib.php';
    include_once dirname(__FILE__) . '/../xhprof_lib/utils/xhprof_runs.php';
    if (isset($ignoredFunctions) && is_array($ignoredFunctions) && !empty($ignoredFunctions)) {
        xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY, array('ignored_functions' => $ignoredFunctions));
    } else {
        xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
    }
}elseif(!extension_loaded('xhprof') && $_xhprof['display'] === true)
{
    $message = 'Warning! Unable to profile run, xhprof extension not loaded';
    trigger_error($message, E_USER_WARNING);
}

function xhprof_shutdown_function() {
    global $_xhprof;

	if (!defined('XHPROF_LIB_ROOT')) {
	  define('XHPROF_LIB_ROOT', dirname(dirname(__FILE__)) . '/xhprof_lib');
	}

	if (extension_loaded('xhprof') && $_xhprof['doprofile'] === true) {
		$profiler_namespace = $_xhprof['namespace'];  // namespace for your application
		$xhprof_data = xhprof_disable();
		$xhprof_runs = new XHProfRuns_Default();
		$run_id = $xhprof_runs->save_run($xhprof_data, $profiler_namespace, null, $_xhprof);
		if ($_xhprof['display'] === true && PHP_SAPI != 'cli')
		{
			// url to the XHProf UI libraries (change the host name and path)
			$profiler_url = sprintf($_xhprof['url'] . '/index.php?run=%s', $run_id, $profiler_namespace);
			$callGraph_url = sprintf($_xhprof['url'] . '/callgraph.php?run=%s&source=%s', $run_id, $profiler_namespace);

			echo '<span style="position:absolute;top:10px;right:10px;z-index:9999999">',
					'<a href="'. $profiler_url .'" target="_blank">xhprof</a>',
				' / ',
					'<a href="'. $callGraph_url .'" target="_blank">callgraph</a>',
				'</span>';
		}
	}

}

register_shutdown_function('xhprof_shutdown_function');
