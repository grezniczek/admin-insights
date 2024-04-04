<?php namespace DE\RUB\AdminInsightsExternalModule;

use Exception;
use ExternalModules\AbstractExternalModule;

require_once "classes/InjectionHelper.php";
require_once "classes/PageInfo.php";
require_once "classes/User.php";

/**
 * Provides enhancements to the External Module Management pages.
 */
class AdminInsightsExternalModule extends AbstractExternalModule {

    #region Constructor & Variables

    private $js_injected = false;

    private $ai_icon = "<span class=\"badge badge-info\" style=\"font-weight:normal;font-size:80%;\">AI</span> ";

    #endregion

    #region Hooks

    function redcap_data_entry_form ($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance = 1) {
        $user = new User($this->framework, defined("USERID") ? USERID : null);
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

    function redcap_survey_page ($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1) {
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
        $user = new User($this->framework, defined("USERID") ? USERID : null);

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
        $user = new User($this->framework, defined("USERID") ? USERID : null);
        // Only cater to super users
        if (!$user->isSuperUser()) return null;


        $get_state_icon = function($id) {
            $state = $this->getFeatureState($id);
            return "<span id=\"ai-$id-state\">".$this->getFeatureStateIcon($state)."</span>";
        };

        if ($project_id && $link["key"] == "toggle-designer-enhancements") {
            $link["name"] = $this->ai_icon . $this->tt("link_designer_enhancements") . $get_state_icon("designer-enhancements");
            return $link;
        }
        if ($project_id && $link["key"] == "toggle-data-entry-annotations") {
            $link["name"] = $this->ai_icon . $this->tt("link_data_entry_annotations") . $get_state_icon("data-entry-annotations");
            return $link;
        }
        if ($project_id && $link["key"] == "toggle-survey-annotations") {
            $link["name"] = $this->ai_icon . $this->tt("link_survey_annotations") . $get_state_icon("survey-annotations");
            return $link;
        }
        // Opens a dialog (no function yet)
        // if ($project_id && $link["key"] == "feature-dialog") {
        //     $link["name"] = $this->ai_icon . $this->tt("link_feature_dialog");
        //     return $link;
        // }

        return null;
    }

    function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id) {
        $user = new User($this->framework, $user_id);
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
            case "get-feature-dialog": {
                return $this->generateFeatureDialog();
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
        $ih = InjectionHelper::init($this);
        $ih->js("js/admin-insights.js", PageInfo::IsSurvey());
        $ih->css("css/admin-insights.css", PageInfo::IsSurvey());
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


    #region A feature dialog

    function generateFeatureDialog() {
        $title = $this->tt("feature_dialog_title");
        return <<<END
        <div id="ai-feature-dialog" class="modal modal-dialog-centered modal-dialog-scrollable modal-lg" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{$this->ai_icon} $title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Modal body text goes here.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save changes</button>
                </div>
                </div>
            </div>
        </div>
        END;
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
        return $this->getFeatureStateIcon($state);
    }

    private function getFeatureStateIcon($state) {
        return $state == "on" 
            ? "<i class=\"fas fa-toggle-on text-success ms-2\"></i>"
            : "<i class=\"fas fa-toggle-off ms-2\" style=\"opacity:.5;\"></i>";
    }

    #endregion

}