<?php

function template($name, $vars = array(), $ajax = false)
{

    if ($ajax) {
        $allowedFiles = array(
            TPL_DIR . $name . '.ajax.phtml',
        );
    } else {
        $allowedFiles = array(
            TPL_DIR . $name . '.phtml',
            TPL_DIR . $name . '.ajax.phtml',
        );
    }

    $found = false;
    foreach ($allowedFiles as $allowedFile) {
        if (!file_exists($allowedFile)) {
            continue;
        }
        // use template_render to minimize conflicts between extract($vars) and scope vars
        $html = template_render($allowedFile, $vars);
        $found = true;
        break;
    }

    if (!$found) {
        trigger_error('template(' . var_export($name, true) . ') not found', E_USER_WARNING);
        return '';
    }

    return $html;

}

function template_render($_templateFile, $vars)
{
    ob_start();
    extract((array)$vars);
    include $_templateFile;
    return ob_get_clean();
}

function dispatchAction()
{

    $response = array(
        'return' => null,
        'exception' => null,
        'output' => null,
    );

    try {

        // make sure warnings won't brake json response
        ob_start();

        $actionFile = validateActionFile();
        if (!$actionFile) {
            return;
        }

        $actionName = preg_replace('~\..+$~', '', str_replace(ACTIONS_DIR, '', $actionFile));
        Events::dispatch('action.dispatch.before', array('action' => $actionName));

        $ARG = json_decode($_POST['ARG']);
        $actionFiles = getActionInitFiles($actionFile);
        $actionFiles[] = $actionFile;
        $response['return'] = includeActionFiles($actionFiles, compact('ARG', 'actionName'));

    } catch (Exception\Template $e) {
        $response['exception'] = array(
            "type" => "html",
            "message" => template($e->template, $e->vars),
        );
    } catch (Exception\Custom $e) {
        $response['return'] = array('success' => false, 'message' => $e->getMessage());
    } catch (Exception $e) {
        $response['exception'] = array(
            "type" => "text",
            "message" => $e->getMessage(),
        );
    }

    // clean all levels of output buffer to make sure json will be valid
    $output = null;
    while (ob_get_level()) {
        $output = ob_get_clean() . $output;
    }
    $response['output'] = $output;

    header('Content-type: application/json');
    die(json_encode($response));

}

function validateActionFile()
{

    if (isset($_REQUEST['action'])) {

        // XSR protection
        checkActionReferer();

        // flat path and check if exists
        if (!$actionFile = realpath(ACTIONS_DIR . $_REQUEST['action'] . '.php')) {
            error('action "' . $_REQUEST['action'] . '" doesn\'t exists');
        }

        // if inside ACTIONS_DIR (protect from include injection)
        if (!strpos($actionFile, realpath(ACTIONS_DIR)) === 0) {
            error('invalid action name');
        }

        return $actionFile;

    }
    return false;
}

function checkActionReferer()
{
    if (getHttpHost() !== getRefererDomain()) {
        error('Action denied from foreign domain');
    }
}

function getActionInitFiles($actionFile)
{

    $initFiles = array();

    $crumbs = explode('/', preg_replace('~^' . preg_quote(ACTIONS_DIR) . '~', '', $actionFile));
    array_pop($crumbs);

    $location = ACTIONS_DIR;
    foreach ($crumbs as $crumb) {
        if (file_exists($initFile = ($location .= "$crumb/") . '_init.php')) {
            $initFiles[] = $initFile;
        }
    }

    return $initFiles;

}

// safe include: allow to avoid variables conflicts in current scope
function includeActionFiles($___files, $___vars = array())
{
    extract($___vars);
    foreach ($___files as $___file) {
        $___return = include $___file;
    }
    return $___return;
}

function renderPage($baseTemplate = 'page', $defaultView = 'projects')
{

    try {

        $view = $defaultView;
        $viewTplPath = 'page/view/';
        if (isset($_GET['view'])) {
            $v = $_GET['view'];
            if (realpath(TPL_DIR . $viewTplPath . $v . '.phtml')
                && strpos(realpath(TPL_DIR . $viewTplPath . $v . '.phtml'), realpath(TPL_DIR)) === 0
            ) {
                $view = $v;
            } else {
                $view = '404';
            }
        }

        $viewTemplate = $viewTplPath . $view;

        Events::dispatch('page.render.before', array('view' => $view));

        page(array(
            'view' => $view,
            'viewTemplate' => $viewTemplate,
        ));

        $baseLayout = TPL_DIR . $baseTemplate . '.php';
        if (file_exists($baseLayout)) {
            include $baseLayout;
        }

        $layoutUpdate = TPL_DIR . $viewTemplate . '.php';
        if (file_exists($layoutUpdate)) {
            include $layoutUpdate;
        }

        echo template($baseTemplate);

    } catch (Exception $e) {
        // clean all levels of output buffer to make sure exception is at the top
        $output = null;
        while (ob_get_level()) {
            $output = ob_get_clean() . $output;
        }
        echo '<pre style="color: #bb0000">' . html2text($e) . '</pre>';
        echo "Interrupted output:<br><pre>" . html2text($output) . "</pre>";
    }

}

function page($data = array())
{
    static $page;
    if (is_null($page)) {
        $page = new stdClass();
    }
    foreach ($data as $k => $v) {
        $page->$k = $v;
    }
    return $page;
}

function error($message, $custom = false)
{
    if ($custom) {
        throw new Exception\Custom($message);
    }
    throw new Exception($message);
}

function error_template($template, $vars = array())
{
    throw new Exception\Template($template, $vars);
}

function error_bash($vars)
{
    throw new Exception\Bash($vars);
}

function getRefererDomain()
{
    if (!preg_match('~https?://([^/:]+)~', @$_SERVER['HTTP_REFERER'], $matches)) {
        return '';
    }
    return $matches[1];
}

function getHttpHost()
{
    $host = $_SERVER['HTTP_HOST'];
    $host = preg_replace('~:[0-9]+$~', '', $host);
    return $host;
}
