<?php

class ACL
{
    static protected $actionsAcl = null;

    static protected $actionsAllowedForAll = array(
        'template',
        'project/installation/getForm',
        'project/installation/depins/count',
        'projects/remote/getStatusHtml',
        'projects/remote/options/save',
        'devtool/getStatusHtml',
    );

    static public function actionDispatchBefore($action)
    {
        if (!static::isAllowed($action)) {
            error('Access denied for user "' . LDAP_USER . '" for action "' . $action . '"');
        }
    }

    static public function isAllowed($action)
    {

        // skip ACL for local installation
        if (LDAP_USER === USER) {
            return true;
        }

        $actionsAcl = static::getActionsAcl();

        if ($actionsAcl->allowed === '*') {
            return !isset($actionsAcl->denied[$action]);
        } else {
            return isset($actionsAcl->allowed[$action]);
        }
    }

    static protected function getActionsAcl()
    {
        if (!is_null(static::$actionsAcl)) {
            return static::$actionsAcl;
        }

        $acl = (object)array(
            'allowed' => static::$actionsAllowedForAll,
            'denied' => '*'
        );

        if ($groupAcl = static::getGroupActionsAcl()) {
            $acl = $groupAcl;
        }

        if ($acl->allowed === '*') {
            $acl->denied = array_fill_keys($acl->denied, 1);
        } else {
            $acl->allowed = array_fill_keys(
                array_merge(
                    static::$actionsAllowedForAll,
                    $acl->allowed
                ),
                1
            );
        }

        static::$actionsAcl = $acl;

        return static::$actionsAcl;
    }

    static protected function getGroupActionsAcl()
    {
        $users = \Users::getData();
        if (!isset($users->{LDAP_USER})) {
            return false;
        }

        $user = $users->{LDAP_USER};
        if (!isset($user->group)) {
            return false;
        }

        $group = $user->group;
        $groups = \Groups::getData();
        if (!isset($groups->$group->ACL->actions)) {
            return false;
        }

        $actions = $groups->$group->ACL->actions;
        if (!isset($actions->allowed) && !isset($actions->denied)) {
            return false;
        }

        if (!isset($actions->allowed) || !isset($actions->denied)) {
            error('Not supported ACL configuration: missing "' . (isset($actions->allowed) ? 'denied' : 'allowed') . '" field');
        }

        if (!($actions->allowed == '*' xor $actions->denied == '*')) {
            error('Not supported ACL configuration: missing or excess "*"');
            return false;
        }

        return $actions;
    }
}
