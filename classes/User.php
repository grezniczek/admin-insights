<?php namespace DE\RUB\AdminInsightsExternalModule;

use ExternalModules\Framework;
use ExternalModules\ExternalModules;

class User
{
    /** @var Framework */
    private $fw;

    /** @var string */
    private $username;

    /** @var array User info as in the redcap_user_information table */
    private $user_info = false;

    /**
     * Constructor
     * @param Framework $fw 
     * @param string $username 
     * @return void 
     */
    function __construct($fw, $username) {
        $this->fw = $fw;
        $this->username = $username;
    }

    function getRights($project_ids = null) {
        return ExternalModules::getUserRights($project_ids, $this->username);
    }

    function hasDesignRights($project_id = null) {
        if($this->isSuperUser()) {
            return true;
        }
        if(!$project_id) {
            $project_id = $this->fw->requireProjectId();
        }
        $rights = $this->getRights($project_id);
        return $rights['design'] === '1';
    }

    function getUsername() {
        return $this->username;
    }

    /**
     * Indicates whether the user has full access to all REDCap projects in the system and has maximum privileges within those projects. Within the Control Center interface, the user can access and use the following pages that pertain to project administration: To-Do List, Edit a Project's Settings, Survey Link Lookup, and API Tokens.
     * @return bool 
     */
    function isSuperUser() {
        return $this->hasAdminAttribute("super_user");
    }

    /**
     * Indicates whether the user can access, modify, and (if using Table-based authentication) create REDCap user accounts. The following pages can be accessed and utilized: Browse Users, Add Users, User Allowlist, and Email Users.
     * @return bool 
     */
    function isAccountManager() {
        return $this->hasAdminAttribute("account_manager");
    }

    /**
     * Indicates whether the user can access the 'Administrator Privileges' page and can set admin rights for any user.
     * @return bool 
     */
    function canSetAdministorPrivileges() {
        return $this->hasAdminAttribute("admin_rights");
    }

    /**
     * Indicates whether the user can modify settings on all system configuration pages in the Control Center, which includes all pages listed under the 'Miscellaneous Modules' and 'System Configuration' sections on the left-hand menu. Note: If the user does not have this specific privilege but does have at least one other administrator privilege, they may still access and view the system configuration pages but only in read-only mode.
     * @return bool 
     */
    function canAccessSystemConfig() {
        return $this->hasAdminAttribute("access_system_config");
    }

    /**
     * Indicates whether the user can access tools used for upgrading the REDCap software, including notifications about new versions available and also accessing the Easy Upgrade feature (if enabled). Note: This admin privilege does not apply when upgrading REDCap using traditional methods (i.e., when not using the Easy Upgrade) because the traditional upgrade process occurs mostly outside of the REDCap user interface in a database client and via direct server access.
     * @return bool 
     */
    function canAccessSystemUpgrade() {
        return $this->hasAdminAttribute("access_system_upgrade");
    }

    /**
     * Indicates whether the user has the ability to install External Modules from the REDCap Repo, and can enable and configure them at the system level. This does not apply to enabling and configuring an External Module in a project, which is governed by other user privileges. Note: If the user does not have this specific privilege but does have at least one other administrator privilege, they may still access and view the External Modules page in the Control Center but only in read-only mode.
     * @return bool 
     */
    function canAccessExternalModuleInstall() {
        return $this->hasAdminAttribute("access_external_module_install");
    }

    /**
     * Indicates whether the user can access and utilize all pages listed under the 'Dashboard' section of the Control Center's left-hand menu.
     * @return bool 
     */
    function canAccessAdminDashboards() {
        return $this->hasAdminAttribute("access_admin_dashboards");
    }

    /**
     * Indicates whether the user can access the Control Center (in any capacity).
     * @return bool 
     */
    function canAccessControlCenter() {
        $attributes = array(
            "super_user",
            "account_manager",
            "admin_rights",
            "access_system_config",
            "access_system_upgrade",
            "access_external_module_install",
            "access_admin_dashboard",
        );
        foreach ($attributes as $attribute) {
            if ($this->hasAdminAttribute($attribute)) return true;
        }
        return false;
    }

    /**
     * Gets the user's primary e-mail address.
     * @return string
     */
    function getEmail() {
        $userInfo = $this->getUserInfo();
        return $userInfo['user_email'];
    }


    /**
     * Checks whether the given admin attribute applies to the user.
     * @param string $attribute 
     * @return bool 
     */
    private function hasAdminAttribute($attribute) {
        $userInfo = $this->getUserInfo();
        // Fallback for REDCap < 10.1.0
        return isset($userInfo[$attribute]) ? $userInfo[$attribute] === 1 : $userInfo["super_user"] === 1;
    }


    private function getUserInfo() {
        if(!$this->user_info) {
            $results = $this->fw->query(
                "SELECT * FROM redcap_user_information WHERE username = ?", 
                [$this->username]
            );
            $this->user_info = $results->fetch_assoc();
        }
        return $this->user_info;
    }
}
