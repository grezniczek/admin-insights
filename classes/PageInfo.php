<?php namespace DE\RUB\AdminInsightsExternalModule;

class PageInfo {

    private static function getPage() {
        return defined("PAGE") ? PAGE : false;
    }

    public static function IsRecordHomePage() {
        return self::getPage() === "DataEntry/record_home.php";
    }

    public static function IsExistingRecordHomePage() {
        return self::IsRecordHomePage() && !isset($_GET["auto"]);
    }

    public static function IsSystemExternalModulesManager() {
        return self::getPage() === "manager/control_center.php";
    }

    public static function IsProjectExternalModulesManager() {
        return self::getPage() === "manager/project.php";
    }

    public static function IsDevelopmentFramework($module) {
        return strpos($module->framework->getUrl("dummy.php"), "/external_modules/?prefix=") !== false;
    }

    public static function IsDatabaseQueryTool() {
        return self::getPage() === "ControlCenter/database_query_tool.php";
    }

    public static function IsDesigner() {
        return self::getPage() === "Design/online_designer.php";
    }

    public static function GetDesignerForm() {
        if (self::IsDesigner() && isset($_GET["page"])) {
            return $_GET["page"];
        }
        return null;
    }

    public static function IsDataEntry() {
        return self::getPage() === "DataEntry/index.php" && isset($_GET["page"]);
    }

    public static function IsExistingRecordDataEntry() {
        return self::IsDataEntry() && !isset($_GET["auto"]);
    }

    public static function IsSurvey() {
        return self::getPage() === "surveys/index.php";
    }

    public static function HasGETParameter($name) {
        return isset($_GET[$name]);
    }

    public static function SanitizeProjectID($pid) {
        $clean = is_numeric($pid) ? $pid * 1 : null;
        return is_int($clean) ? $clean : null;
    }
}

