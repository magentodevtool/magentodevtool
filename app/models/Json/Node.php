<?php

namespace Json;

class Node
{

    function getNode($path)
    {
        $value = $this;
        foreach (explode('/', $path) as $prop) {
            if (!is_object($value) || !isset($value->$prop)) {
                return null;
            }
            $value = $value->$prop;
        }
        return $value;
    }

    function setNode($path, $value)
    {
        $currNode = $this;
        $paths = explode('/', $path);
        $finalProp = array_pop($paths);
        $baseClass = get_class($this); // not __CLASS__! in order to be correct in case of extends
        foreach ($paths as $prop) {
            if (!isset($currNode->$prop)) {
                $currNode->$prop = new $baseClass;
            }
            $currNode = $currNode->$prop;
        }
        $currNode->$finalProp = $value;
    }

}
