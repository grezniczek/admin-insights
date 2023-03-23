<?php namespace DE\RUB\AdminInsightsExternalModule;

use Exception;
use ExternalModules\AbstractExternalModule;
use InvalidArgumentException;

require_once "classes/User.php";
require_once "classes/InjectionHelper.php";

/**
 * Provides enhancements to the External Module Management pages.
 */
class AdminInsightsExternalModule extends AbstractExternalModule {

    /**
     * EM Framework (tooling support)
     * @var \ExternalModules\Framework
     */
    private $fw;

    /**
     * @var InjectionHelper
     */
    public $ih = null;


    private $js_injected = false;

    function __construct() {
        parent::__construct();
        $this->fw = $this->framework;
        $this->ih = InjectionHelper::init($this);
    }

    #region Hooks

    function redcap_data_entry_form ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        $user = new User($this->fw, defined("USERID") ? USERID : null);
        // Only catering for super users
        if (!$user->isSuperUser()) return;

        $features = ["reveal-hidden"];
        if ($this->getFeatureState("data-entry-annotations") === "on") {
            $features[] = "data-entry-annotations";
        }
        $context = [
            "pid" => $project_id,
            "record" => $record,
            "form" => $instrument,
            "event_id" => $event_id,
            "instance" => $repeat_instance,
            "dag_id" => $group_id,
            "is_survey" => false
        ];
        $this->add_features($features, $context);
    }

    function redcap_survey_page ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id = NULL, $repeat_instance = 1) {
        // Only catering for super users
        if (!\Session::hasAdminSessionCookie()) return;
        
        $features = ["reveal-hidden"];
        if ($this->getFeatureState("survey-annotations") === "on") {
            $features[] = "survey-annotations";
        }
        $context = [
            "pid" => $project_id,
            "record" => $record,
            "form" => $instrument,
            "event_id" => $event_id,
            "instance" => $repeat_instance,
            "dag_id" => $group_id,
            "is_survey" => true
        ];
        $this->add_features($features, $context);
    }

    function redcap_every_page_top($project_id = null) {
        $user = new User($this->fw, defined("USERID") ? USERID : null);

        // Only catering for super users
        if (!$user->isSuperUser()) return;

        // Determine what page we are on
        $page = defined("PAGE") ? PAGE : "";
        if ($page == "") return;

        $context = [
            "pid" => $project_id,
            "page" => $page
        ];

        // Online Designer (with form loaded)
        if (PageInfo::GetDesignerForm() !== null) {
            $context["form"] = $_GET["page"];
            if ($this->getFeatureState("designer-enhancements") === "on") {
                $this->add_features(["designer-enhancements"], $context);
            }
        }
        // Record Home Page (of an existing record)
        else if (PageInfo::IsExistingRecordHomePage()) {
            $context["record"] = $_GET["id"];
            $context["arm"] = $_GET["arm"];
            $this->add_features(["show-record-log-rhp", "query-record-rhp"], $context);
        }
        // Database Query Tool
        else if (PageInfo::IsDatabaseQueryTool() && isset($_GET["ai-query-for"])) {
            $this->add_features(["query-record-dqt"], $context);
        }
        // Ensure JS has been injected
        if (!PageInfo::IsSurvey() && $project_id && !$this->js_injected) {
            $this->add_features([], $context);
        }
    }

    function redcap_module_link_check_display($project_id, $link) {
        $user = new User($this->fw, defined("USERID") ? USERID : null);
        // Only cater to super users
        if (!$user->isSuperUser()) return null;

        if ($project_id && $link["key"] == "toggle-designer-enhancements") {
            $state = $this->getFeatureState("designer-enhancements");
            $link["name"] = $this->tt("link_designer_enhancements") . "<span id=\"ai-designer-enhancements-state\">" . $this->tt("link_state_{$state}") . "</span>";
            return $link;
        }
        if ($project_id && $link["key"] == "toggle-data-entry-annotations") {
            $state = $this->getFeatureState("data-entry-annotations");
            $link["name"] = $this->tt("link_data_entry_annotations") . "<span id=\"ai-data-entry-annotations-state\">" . $this->tt("link_state_{$state}") . "</span>";
            return $link;
        }
        if ($project_id && $link["key"] == "toggle-survey-annotations") {
            $state = $this->getFeatureState("survey-annotations");
            $link["name"] = $this->tt("link_survey_annotations") . "<span id=\"ai-survey-annotations-state\">" . $this->tt("link_state_{$state}") . "</span>";
            return $link;
        }
        return null;
    }

    function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id) {
        $user = new User($this->fw, $user_id);
        // Only cater to super users
        if (!$user->isSuperUser()) throw new Exception("Insufficient rights.");

        switch($action) {
            case 'toggle-data-entry-annotations': {
                return $this->toggleFeatureState("data-entry-annotations");
            }
            case 'toggle-survey-annotations': {
                return $this->toggleFeatureState("survey-annotations");
            }
            case 'toggle-designer-enhancements': {
                return $this->toggleFeatureState("designer-enhancements");
            }
        }
        return null;
    }

    #endregion

    #region Feature Management

    /**
     * Adds features to a page. This can be called multiple times
     * @param string[] $features List of the features to add
     * @param Array $context 
     * @return void 
     */
    private function add_features($features, $context) {
        $config = [
            "features" => [],
            "errors" => []
        ];
        foreach ($features as $feature) {
            $feature_func = "feature__" . str_replace("-", "_", $feature);
            $feature_config = ["feature" => $feature];
            try {
                if ($this->$feature_func($context, $feature_config) === true) {
                    // Only add when true is returned
                    $config["features"][] = $feature_config;
                }
            }
            catch (\Throwable $ex) {
                $config["errors"][] = [
                    "msg" => "Failed to add feature '$feature'.",
                    "details" => $ex->getMessage()
                ];
            }
        }
        $this->inject_js();
        $this->initialize_js($config);
    }

    /**
     * Loads the JS support file and initializes the JSMO (only once)
     */
    private function inject_js() {
        // Only do this once
        if ($this->js_injected) return;
        // Inject JS and CSS
        $this->ih->js("js/admin-insights.js", PageInfo::IsSurvey());
        $this->ih->css("css/admin-insights.css", PageInfo::IsSurvey());
        $this->initializeJavascriptModuleObject();
        $this->js_injected = true;
    }

    /**
     * Initializes the JS support file with configuration data (this can be called multiple times)
     * @param Array $config 
     * @return void 
     */
    private function initialize_js($config) {
        $jsmo_name = $this->getJavascriptModuleObjectName();
        $config["version"] = $this->VERSION;
        $config["debug"] = $this->getSystemSetting("debug-mode") == true;
        // JS
        print "\n<script>$(() => DE_RUB_AdminInsights.init(".json_encode($config).", {$jsmo_name}));</script>\n";
    }

    #endregion

    #region Features

    // Features must be called "feature__" + feature id and take $context and (by ref) $config as arguments and
    // return a boolean (determining whether the feature will be included in the JS config)

    private function feature__designer_enhancements($context, &$config) {
        $config["fields"] = [];
        $config["codeTitle"] = $this->tt("designer_code_title");
        $Proj = new \Project($context["pid"]);
        $fields = array_keys($Proj->forms[$context["form"]]["fields"]);
        foreach ($fields as $field) {
            $misc = $Proj->metadata[$field]["misc"] ?? "";
            $config["fields"][$field] = $misc;
        }
        return true;
    }

    private function feature__data_entry_annotations($context, &$config) {
        return $this->form_annotations($context, $config);
    }

    private function feature__survey_annotations($context, &$config) {
        return $this->form_annotations($context, $config);
    }

    private function form_annotations($context, &$config) {
        $config["fields"] = [];
        $Proj = new \Project($context["pid"]);
        if ($context["is_survey"]) {
            $page_num = isset($_GET["__page__"]) ? intval($_GET["__page__"]) : 1;
            $page_fields = \Survey::getPageFields($context["form"], true)[0][$page_num];
            $config["isSurvey"] = true;
        }
        else {
            $page_fields = array_keys($Proj->forms[$context["form"]]["fields"]);
            $config["isSurvey"] = false;
        }
        foreach ($page_fields as $field) {
            $misc = $Proj->metadata[$field]["misc"] ?? "";
            $config["fields"][$field] = $misc;
        }
        return true;
    }

    private function feature__reveal_hidden($context, &$config) {
        $config["linkLabel"] = "<span class=\"badge badge-info\" style=\"font-weight:normal;font-size:80%;\">AI</span> ".$this->tt("reveal_hidden_link_label");
        $config["isSurvey"] = $context["is_survey"];
        return true;
    }

    private function feature__query_record_rhp($context, &$config) {
        $config["record"] = $context["record"];
        $config["pid"] = $context["pid"];
        $config["dqtLink"] = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/ControlCenter/database_query_tool.php";
        $config["labelData"] = "<span class=\"badge badge-info\" style=\"font-weight:normal\">AI</span> ". $this->tt("query_record_data_link_label");
        $config["labelLogs"] = "<span class=\"badge badge-info\" style=\"font-weight:normal\">AI</span> ". $this->tt("query_record_logs_link_label");
        return true;
    }

    private function feature__query_record_dqt($context, &$config) {
        $mode = $_GET["ai-query-for"];
        if (!in_array($mode, ["data","logs"], true)) return false;
        $record = db_escape($_GET["ai-query-id"]);
        $pid = PageInfo::SanitizeProjectID($_GET["ai-query-pid"]);
        if ($pid == null) return false;
        if ($mode == "data") {
            $config["query"] = "SELECT *\n FROM redcap_data\n WHERE `project_id` = {$pid} AND `record` = '{$record}'";
        }
        else {
            $log_event_table = db_escape(\REDCap::getLogEventTable($pid));
            $config["query"] = "SELECT *\n FROM {$log_event_table}\n WHERE `project_id` = {$pid} AND `pk` = '{$record}'\n ORDER BY `log_event_id` DESC";
        }
        $config["csrfToken"] = $this->getCSRFToken();
        return true;
    }

    private function feature__show_record_log_rhp($context, &$config) {
        $config["record"] = $context["record"];
        $config["url"] = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/Logging/index.php?pid=".$context["pid"];
        $config["label"] = "<span class=\"badge badge-info\" style=\"font-weight:normal\">AI</span> ". $this->tt("show_logging_link_label");
        return true;
    }

    #endregion



    #region Helpers

    private function getFeatureState($feature) {
        return $this->getProjectSetting("show-$feature") == true ? "on" : "off";
    }

    private function setFeatureState($feature, $state) {
        $this->setProjectSetting("show-$feature", $state === "on");
        return $state;
    }

    private function toggleFeatureState($feature) {
        $state = $this->setFeatureState($feature, $this->getFeatureState($feature) === "on" ? "off" : "on");
        return $this->tt("link_state_{$state}");
    }

    #endregion



    function insertFieldAnnotations($form, $designer = false) {
        global $Proj;
        foreach ($Proj->forms[$form]["fields"] as $field => $_) {
            $annotations = $Proj->metadata[$field]["misc"];
            print "<div class=\"emdt-field-annotation\" data-target=\"{$field}\" style=\"display:none;font-weight:normal;padding:0.5em;margin-top:0.5em;background-color:#fafafa;\"><code style=\"white-space:pre;margin-top:0.5em;\">{$annotations}</code></div>\n";
        }
        ?>
        <style>
            .copy-field-name { 
                cursor: hand !important;
            }
            .copy-field-name:hover {
                color: var(--bs-primary);
            }
        </style>
        <script>
            // EMM Tools - Append Field Annotations
            function EMMTools_init() {
                var designer = <?= json_encode($designer) ?>;
                if (designer) {
                    $('span[data-kind="variable-name"]').each(function() {
                        const $this = $(this)
                        $this.addClass('copy-field-name text-info')
                        $this.on('mousedown', function(e) {
                            e.stopImmediatePropagation()
                        })
                        const field = $this.text()
                        $this.on('click', function() {
                            EMMTools_copyTextToClipboard(field);
                            $this.css('background-color', 'red')
                            setTimeout(function() {
                                $this.css('background-color', 'transparent')
                            }, 200)
                            return false;
                        })
                        $annotation = $('.emdt-field-annotation[data-target="' + field + '"]')
                        if ($annotation.length) {
                            const $badge = $('<span class="badge badge-info" style="font-weight:normal;">EMDT</span>');
                            $badge.attr('title', $annotation.text()).css('margin-left','1em');
                            $('#design-' + field + ' span.od-field-icons').append($badge);
                        }
                    })
                }
                else {
                    $('.emdt-field-annotation').each(function() {
                        const $annotation = $(this);
                        const field = $annotation.attr('data-target');
                        const $badge = $('<span class="badge badge-info" style="font-weight:normal;">EMDT</span>');
                        const embedded = $('[sq_id="' + field + '"]').hasClass('row-field-embedded');
                        $badge.css('margin-bottom','0.5em');
                        $annotation.prepend('<br>');
                        $annotation.prepend($badge);
                        $badge.after('<small><i> &ndash; ' + field + '</i></small>');
                        if (embedded) {
                            $badge.removeClass('badge-info').addClass('badge-warning');
                            var $embed = $('span.rc-field-embed[var="' + field + '"]')
                            $embed.parents('tr[sq_id]').find('td').not('.questionnum').first().append($annotation);
                            $badge.css('cursor', 'crosshair');
                            $badge.on('mouseenter', function() {
                                $embed.css('outline', 'red dotted 2px');
                            });
                            $badge.on('mouseleave', function() {
                                $embed.css('outline','none');
                            });
                            $badge.on('click', function() {
                                $embed.find('input').focus();
                            });
                        }
                        else {
                            $('div[data-mlm-field="' + field + '"]').after($annotation);
                        }
                        $annotation.show();
                    })
                }
            }
            $(function() {
                if (<?=json_encode($designer)?>) {
                    const EMMTools_reloadDesignTable = reloadDesignTable
                    reloadDesignTable = function(form_name, js) {
                        EMMTools_reloadDesignTable(form_name, js)
                        setTimeout(function() {
                            EMMTools_init()
                        }, 50)
                    }
                }
                EMMTools_init()
            });
        </script>
        <?php
    }

}

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