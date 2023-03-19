<?php namespace DE\RUB\AdminInsightsExternalModule;

use Exception;
use ExternalModules\AbstractExternalModule;
use InvalidArgumentException;

require_once "classes/User.php";

/**
 * Provides enhancements to the External Module Management pages.
 */
class AdminInsightsExternalModule extends AbstractExternalModule {

    /**
     * EM Framework (tooling support)
     * @var \ExternalModules\Framework
     */
    private $fw;

    private $js_injected = false;

    function __construct() {
        parent::__construct();
        $this->fw = $this->framework;
    }

    #region Hooks

    function redcap_data_entry_form ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {

    }

    function redcap_survey_page ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id = NULL, $repeat_instance = 1) {
    }

    function redcap_every_page_top($project_id = null) {
        $user = new User($this->fw, defined("USERID") ? USERID : null);

        // Only catering for super users
        if (!$user->isSuperUser()) return;

        // Determine what page we are on
        $page = defined("PAGE") ? PAGE : "";
        if ($page == "") return;

        // Online Designer (with form loaded)
        if ($project_id != null && $page == "Design/online_designer.php" && isset($_GET["page"])) {
            $this->add_features(["form_designer_enhancements"], [
                "pid" => $project_id,
                "form" => $_GET["page"]
            ]);
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

    private function add_features($features, $context) {
        $config = [];
        foreach ($features as $feature) {
            $config = $this->$feature($context, $config);
        }
        $this->inject_js();
        $this->initialize_js($config);
    }

    private function form_designer_enhancements($context, $config) {



        return $config;
    }


    function xxx() {
        return; 


        // Database Query Tool Shortcuts
        if (PageInfo::IsProjectExternalModulesManager() || PageInfo::IsSystemExternalModulesManager() || PageInfo::IsDatabaseQueryTool()) {
            if (PageInfo::IsProjectExternalModulesManager()) {
                $query_link = APP_PATH_WEBROOT . "ControlCenter/database_query_tool.php?query-pid={$project_id}&module-prefix=";
                ?>
                <script>
                    $(function(){
                        $('#external-modules-enabled tr[data-module]').each(function() {
                            const tr = $(this);
                            const moduleName = tr.attr('data-module');
                            const queryLink = $('<a target="_blank" href="<?=$query_link?>' + moduleName + '" style="margin-right:1em;"></a>');
                            queryLink.html('<i class="fas fa-database" style="margin-right:2px;"></i> <?=js_escape($this->fw->tt("mysqllink_label"))?>');
                            const purgeLink = $('<a href="javascript:"></a>');
                            purgeLink.html('<i class="fas fa-database text-danger" style="margin-right:2px;"></i> <?=js_escape($this->fw->tt("mysqlpurge_project_label"))?>');
                            purgeLink.on('click', () => DE_RUB_EMDTools.purgeSettings(moduleName, <?=$project_id?>));
                            const td = tr.find('td').first();
                            if (td.find('div.external-modules-byline').length) {
                                const div = td.find('div.external-modules-byline').first()
                                div.append(queryLink)
                                div.append(purgeLink)
                            }
                            else {
                                const div = $('<div class="external-modules-byline"></div>')
                                div.append(queryLink)
                                div.append(purgeLink)
                                queryLink.css('display', 'block')
                                queryLink.css('margin-top', '7px')
                                td.append(div)
                            }
                        })
                    })
                </script>
                <?php
            }
            else if (PageInfo::IsSystemExternalModulesManager()) {
                $query_link = APP_PATH_WEBROOT . "ControlCenter/database_query_tool.php?query-pid=0&module-prefix=";
                ?>
                <script>
                    $(function(){
                        $('#external-modules-enabled tr[data-module]').each(function() {
                            const tr = $(this);
                            const moduleName = tr.attr('data-module');
                            const queryLink = $('<a target="_blank" href="<?=$query_link?>' + moduleName + '" style="margin-right:1em;"><i class="fas fa-database" style="margin-right:2px;"></i></a>');
                            queryLink.html('<i class="fas fa-database" style="margin-right:2px;"></i> <?=js_escape($this->fw->tt("mysqllink_label"))?>')
                            const purgeLink = $('<a href="javascript:"></a>');
                            purgeLink.html('<i class="fas fa-database text-danger" style="margin-right:2px;"></i> <?=js_escape($this->fw->tt("mysqlpurge_cc_label"))?>');
                            purgeLink.on('click', () => DE_RUB_EMDTools.purgeSettings(moduleName, null));
                            const td = tr.find('td').first();
                            if (td.find('div.external-modules-byline').length) {
                                const div = td.find('div.external-modules-byline').first();
                                div.append(queryLink);
                                div.append(purgeLink);
                            }
                            else {
                                const div = $('<div class="external-modules-byline"></div>');
                                div.append(queryLink);
                                div.append(purgeLink);
                                queryLink.css('display', 'block');
                                queryLink.css('margin-top', '7px');
                                td.append(div);
                            }
                        })
                    })
                </script>
                <?php
            }
            else if (PageInfo::IsDatabaseQueryTool()) {
                $prefix = $_GET["module-prefix"];
                $record = $_GET["query-record"];
                $mode = $_GET["query-for"] == "data" ? "data" : "logs";
                $pid = PageInfo::SanitizeProjectID($_GET["query-pid"]);
                $pid_clause = $pid === 0 ? "project_id IS NULL" : "project_id = {$pid}";
                $execute = false;
                if ($prefix) {
                    $result = $this->fw->query("
                        select external_module_id 
                        from redcap_external_modules 
                        where directory_prefix = ?",
                        [ $prefix ]);
                    $module_id = ($result->fetch_assoc())["external_module_id"];
                    $query = "SELECT * FROM redcap_external_module_settings\n" . 
                            "WHERE external_module_id = {$module_id} -- {$prefix}\n" . 
                            "AND {$pid_clause}";
                    $execute = $module_id !== null;
                }
                else if ($record && $pid > 0) {
                    $record = db_escape($record);
                    if ($mode == "data") {
                        $query = "SELECT * FROM redcap_data\n WHERE `project_id` = {$pid} AND `record` = '{$record}'";
                    }
                    else if ($mode == "logs") {
                        $log_event_table = \REDCap::getLogEventTable($pid);
                        $query = "SELECT * FROM {$log_event_table}\n WHERE `project_id` = {$pid} AND `pk` = '{$record}'\n ORDER BY `log_event_id` DESC";
                    }
                    $execute = !empty($record);
                }
                if ($execute) {
                    // Insert EM Framework CSRF token. Note: Need to set for both names! Not clear why this is needed.
                    $token = $this->getCSRFToken();
                    ?>
                    <script>
                        $(function() {
                            $('#query').val(<?=json_encode($query)?>)
                            $('#form').append('<input type="hidden" name="redcap_external_module_csrf_token" value="<?=$token?>">')
                            $('#form').append('<input type="hidden" name="redcap_csrf_token" value="<?=$token?>">')
                            $('#form').submit()
                        })
                    </script>
                    <?php
                }
            }
        }

        // Query for record data.
        if ($user->isSuperUser()) {
            if (PageInfo::IsExistingRecordHomePage()) {
                $record_id = urlencode(strip_tags(label_decode(urldecode($_GET['id']))));
                $data_link = APP_PATH_WEBROOT . "ControlCenter/database_query_tool.php?query-pid={$project_id}&query-record={$record_id}&query-for=data";
                ?>
                <script>
                    $(function(){
                        var $ul = $('#recordActionDropdown')
                        $ul.append('<li class="ui-menu-item"><a href="<?=$data_link?>" target="_blank" style="display:block;" tabindex="-1" role="menuitem" class="ui-menu-item-wrapper"><span style="vertical-align:middle;color:#065499;"><i class="fas fa-database"></i> <?=$this->fw->tt("mysqllink_record_data")?></span></a></li>')
                    })
                </script>
                <?php
            }
            // Query for record logs.
            if (PageInfo::IsExistingRecordHomePage()) {
                $record_id = urlencode(strip_tags(label_decode(urldecode($_GET['id']))));
                $logs_link = APP_PATH_WEBROOT . "ControlCenter/database_query_tool.php?query-pid={$project_id}&query-record={$record_id}&query-for=logs";
                ?>
                <script>
                    $(function(){
                        var $ul = $('#recordActionDropdown')
                        $ul.append('<li class="ui-menu-item"><a href="<?=$logs_link?>" target="_blank" style="display:block;" tabindex="-1" role="menuitem" class="ui-menu-item-wrapper"><span style="vertical-align:middle;color:#065499;"><i class="fas fa-database" style="color:red;"></i> <?=$this->fw->tt("mysqllink_record_logs")?></span></a></li>')
                    })
                </script>
                <?php
            }
        }

        // Toggle Field Annotations
        if ($user->isSuperUser() && $project_id != null) {
            ?>
            <script>
                function EMDTToggleShowFieldAnnotations() {
                    var $state = $('#emdt-fieldannotations-state')
                    if ($state.attr('working') == '1') return
                    var state = $state.text()
                    $state.html('<i class="fas fa-spinner fa-spin"></i>').attr('working','1')
                    $.ajax({
                        url: '<?= $this->getUrl("toggle-fieldannotations.php") ?>',
                        data: { redcap_csrf_token: '<?= $this->getCSRFToken() ?>' },
                        method: 'POST',
                        success: function(data, textStatus, jqXHR) {
                            console.log('AJAX done: ', data, jqXHR)
                            state = data
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('EMDT - Failed to toggle Show Field Annotations state: ' + errorThrown)
                        },
                        complete: function() {
                            $state.text(state)
                            $state.attr('working', '0')
                        } 
                    })
                }
            </script>
            <?php
        }
    }


    /**
     * Adds and initializes the JS support file
     */
    private function inject_js() {
        // Only do this once
        if ($this->js_injected) return;

        // Inline for survey
        if (PageInfo::IsSurvey()) {
            print "\n<script>\n";
            $js = file_get_contents(dirname(__FILE__)."js/admin-insights.js");
            print $js;
            print "\n</script>\n";
        }
        else {
            print "\n<script src='{$this->getUrl('js/admin-insights.js')}'></script>\n";
        }
        $this->js_injected = true;
    }

    private function initialize_js($config) {
        $this->initializeJavascriptModuleObject();
        $jsmo_name = $this->getJavascriptModuleObjectName();
        $config["version"] = $this->VERSION;
        $config["debug"] = $this->getSystemSetting("debug-mode") == true;
        // JS
        print "\n<script>$(() => DE_RUB_AdminInsights.init(".json_encode($config).", {$jsmo_name}));</script>\n";
    }

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
        </style>
        <script>
            /**
             * Copies a string to the clipboard (fallback method for older browsers)
             * @param {string} text
             */
            function EMMTools_fallbackCopyTextToClipboard(text) {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                // Avoid scrolling to bottom
                textArea.style.top = "0";
                textArea.style.left = "0";
                textArea.style.position = "fixed";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                } catch {
                    error('Failed to copy text to clipboard.')
                }
                document.body.removeChild(textArea);
            }
            /**
             * Copies a string to the clipboard (supported in modern browsers)
             * @param {string} text
             * @returns
             */
            function EMMTools_copyTextToClipboard(text) {
                if (!navigator.clipboard) {
                    EMMTools_fallbackCopyTextToClipboard(text);
                    return;
                }
                navigator.clipboard.writeText(text).catch(function() {
                    error('Failed to copy text to clipboard.')
                })
            }
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

    function inspectProjectObject() {
        global $Proj, $lang;
        $user = new User($this->fw, defined("USERID") ? USERID : null);
        if ($user->isSuperUser()) {

            $script_url = $this->getUrl("js/json-viewer.js");
            print "<script src=\"{$script_url}\"></script>\n";
            // Fully(?) populate data
            $Proj->loadEvents();
            $Proj->loadEventsForms();
            $Proj->loadMetadata();
            $Proj->loadProjectValues();
            $Proj->loadSurveys();
            $Proj->getUniqueEventNames();
            $Proj->getUniqueGroupNames();
            $Proj->getGroups();

            ?>
            <style>
                #projectobject-tabContent {
                    margin-top:0.5em;
                    max-width:800px;
                }
                pre {
                    border: none;
                    background: none;
                    font-size: 12px;
                }
                h4 {
                    font-size: 1.2em;
                    font-weight: bold;
                    margin-bottom: 1em;
                }
                .emm-badge {
                    font-weight: normal;
                }
            </style>
            <h4><?=$this->tt("projectobjectinspector_title")?></h4>
            <nav>
                <div class="nav nav-tabs" id="projectobject-tab" role="tablist">
                    <a class="nav-item nav-link active" id="emm-json-tab" data-toggle="tab" href="#emm-json" role="tab" aria-controls="emm-json" aria-selected="false">JSON</a>
                    <a class="nav-item nav-link" id="printr-tab" data-toggle="tab" href="#printr" role="tab" aria-controls="printr" aria-selected="true">print_r</a>
                    <a class="nav-item nav-link" id="vardump-tab" data-toggle="tab" href="#vardump" role="tab" aria-controls="vardump" aria-selected="false">var_dump</a>
                </div>
            </nav>
            <div class="tab-content" id="projectobject-tabContent">
                <div class="tab-pane fade" id="printr" role="tabpanel" aria-labelledby="printr-tab">
                    <pre><?php print_r($Proj); ?></pre>
                </div>
                <div class="tab-pane fade" id="vardump" role="tabpanel" aria-labelledby="vardump-tab">
                    <pre><?php var_dump($Proj); ?></pre>
                </div>
                <div class="tab-pane fade show active" id="emm-json" role="tabpanel" aria-labelledby="emm-json-tab">
                    <div id="json-menu">
                        <a href="javascript:emdtJsonCollapseAll();">Collapse all</a> | 
                        <a href="javascript:emdtJsonExpandAll();">Expand all</a>
                    </div>
                    <div id="json"></div>
                </div>
            </div>
            <script>
                function emdtJsonCollapseAll() {
                    $('a.list-link').not('.collapsed').each(function(){
                        this.click();
                    })
                }
                function emdtJsonExpandAll() {
                    $('a.list-link.collapsed').each(function(){
                        this.click()
                    })
                }

                $(function(){
                    var jsonViewer = new JSONViewer();
                    var json = <?= json_encode($Proj) ?>;
                    document.querySelector("#json").appendChild(jsonViewer.getContainer());
                    jsonViewer.showJSON(json, -1, 2);
                });
            </script>
            <?php
        }
        else {
            print $lang["global_05"];
        }
    }

    /**
     * Checks whether a module is enabled for a project or on the system.
     *
     * @param string $prefix A unique module prefix.
     * @param string $pid A project id (optional).
     * @return mixed False if the module is not enabled, otherwise the enabled version of the module (string).
     * @throws InvalidArgumentException
     **/
    public function _isModuleEnabled($prefix, $pid = null) {
        if (method_exists($this->framework, "isModuleEnabled")) {
            return $this->framework->isModuleEnabled($prefix, $pid);
        }
        else {
            if (empty($prefix)) {
                throw new InvalidArgumentException("Prefix must not be empty.");
            }
            if ($pid !== null && !is_int($pid) && ($pid * 1 < 1)) {
                throw new InvalidArgumentException("Invalid value for pid");
            }
            $enabled = \ExternalModules\ExternalModules::getEnabledModules($pid);
            return array_key_exists($prefix, $enabled) ? $enabled[$prefix] : false;
        }
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
        return self::getPage() === "DataEntry/index.php";
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