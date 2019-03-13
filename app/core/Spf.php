<?php

class spf
{

    public static $function;
    public static $args;

    protected static $alreadyIncluded = array();

    public static function run()
    {
        if (!static::$function) {
            error('SPF: function name required');
        }
        static::includeIncludes();
        return call_user_func_array(static::getName(), static::$args);
    }

    protected static function includeIncludes()
    {
        if (isset(static::$alreadyIncluded[static::$function])) {
            return;
        }
        $includes = static::getIncludesRecursive();
        $includes[] = static::$function;
        foreach ($includes as $function) {
            if (!function_exists(static::getName($function))) {
                require static::getFile($function);
            }
        }
        static::$alreadyIncluded[static::$function] = 1;
    }

    protected static function getIncludesRecursive($function = null)
    {
        $function = $function ?: static::$function;
        $includes = $currIncludes = static::getIncludes($function);
        foreach ($currIncludes as $include) {
            $includes = array_merge($includes, static::getIncludesRecursive($include));
        }
        return array_unique($includes);
    }

    protected static function getIncludes($function = null)
    {
        $function = $function ?: static::$function;
        preg_match_all('~^\s*#[ \t]*incspf[ \t]+([^\s]+)[ \t\r]*$~ism', static::getText($function), $matches);
        return $matches[1];
    }

    public static function getText($function = null)
    {
        $function = $function ?: static::$function;
        $file = static::getFile($function);
        $fileText = file_get_contents($file);
        if (!$fileText) {
            error("SPF: can't load file $file");
        }
        return $fileText;
    }

    protected static function getFile($function = null)
    {
        $function = $function ?: static::$function;
        $file = APP_DIR . 'SPF/' . $function . '.php';
        if (!file_exists($file)) {
            error("SPF: file $file doesn't exist");
        }
        return $file;
    }

    public static function getName($function = null)
    {
        $function = $function ?: static::$function;
        return '\\SPF\\' . str_replace('/', '\\', $function);
    }

    public static function getIncludesText()
    {
        $text = '';
        foreach (static::getIncludesRecursive() as $function) {
            $code = static::getText($function);
            $code = preg_replace('~\?>$~ism', '', $code) . "\n?>";
            $text .= $code;
        }
        return $text === '' ? '<?php ?>' : $text;
    }

}
