// Admin Insights - REDCap External Module
// Dr. Günther Rezniczek, Ruhr-Universität Bochum, Marien Hospital Herne
// @ts-check
;(function() {

//#region Constants & Variables

const moduleNamePrefix = 'DE_RUB_';
const moduleName = 'AdminInsights';

// @ts-ignore
const MODULE = window[moduleNamePrefix + moduleName] ?? {
    init: initialize,
    toggle: toggleFeature
};
// @ts-ignore
window[moduleNamePrefix + moduleName] = MODULE;

let config = {
    debug: false,
    version: '??'
};
let JSMO = {};

let initialized = false;

const activeFeatures = new Set();

const features = new Map();
features.set('reveal-hidden', { added: false, init: addRevealHidden });
features.set('survey-annotations', { added: false, init: addFormAnnotations });
features.set('data-entry-annotations', { added: false, init: addFormAnnotations });
features.set('designer-enhancements', { added: false, init: addDesignerEnhancements });
features.set('query-record-rhp', { added: false, init: addQueryRecordLinks });
features.set('query-record-dqt', { added: false, init: addQueryRecordExecute });
features.set('show-record-log-rhp', { added: false, init: addViewRecordLoggingLink });

//#endregion

//#region Initialization

/**
 * Initializes the module
 * @param {Object} config_data 
 * @param {Object} jsmo_obj 
 */
function initialize(config_data, jsmo_obj = null) {
    if (!initialized) {
        if (config_data) {
            config = config_data;
        }
        if (jsmo_obj) {
            JSMO = jsmo_obj;
        }
        initialized = true;
        log('Initialized', config);
    }
    else {
        // Merge configuration
        config.features = [...config.features, ...config_data.features];
        log('Updated configuration', config);
    }
    // Report any errors
    for (const error of config_data.errors) {
        error(error.msg, error.details)
    }
    // Add features
    for (const featureConfig of config.features) {
        const feature = features.get(featureConfig.feature);
        if (feature) {
            log('Adding feature "' + featureConfig.feature + '"', featureConfig);
            feature.added = true;
            feature.init(featureConfig);
        }
        else {
            error('Unknown feature:', feature);
        }
    }
}

/**
 * Reacts to clicks on the EM links, toggling features on/off
 * @param {string} feature 
 */
function toggleFeature(feature) {
    if (['data-entry-annotations','survey-annotations','designer-enhancements'].includes(feature)) {
        const $state = $('#ai-' + feature + '-state');
        if ($state.attr('working') == '1') return;
        const $spinner = $('<i class="fas fa-spinner fa-spin"></i>').css('margin-left','.5em');
        $state.attr('working', '1').append($spinner);
        JSMO.ajax('toggle-' + feature)
        .then((data) => {
            log(`Toggled feature '${feature}'`);
            $state.html(data);
        })
        .catch((err) => {
            error(`Failed to toggle '${feature}'.`, err);
        })
        .finally(() => {
            $state.attr('working', '0');
            $spinner.remove();
        });
    }
}

//#endregion

//#region Features

/**
 * Adds a button to reveal hidden fields (can hide them again, too)
 * @param {Object} config Feature config
 */
function addRevealHidden(config) {
    // Some constants
    const hiddenClasses = ['@HIDDEN'];
    if (config.isSurvey) {
        hiddenClasses.push('@HIDDEN-SURVEY');
    }
    else {
        hiddenClasses.push('@HIDDEN-FORM');
    }
    const hiddenSelector = hiddenClasses.map(c => '.\\' + c).join(', ');
    const hiddenStore = 'data-ai-hidden-classes';
    const hiddenMarker = 'ai-hidden-shown';
    const hiddenN = $(hiddenSelector).length;
    // Do not add the link if there is nothing to be revealed
    if (hiddenN < 1) return;
    // Construct Link
    const $link = $('<a href="javascript:;" class="btn btn-link btn-xs fs11"></a>');
    if (config.isSurvey) {
        $link.css({
            'color': $('#auto-fill-btn').css('color'), 
            'font-size': $('#auto').css('font-size')
        });
    }
    else {
        $link.css('color', '#007bffcc').css('text-decoration', 'underline');
    }
    $link.html(config.linkLabel + ' (' + hiddenN + ')');
    $link.find('.badge').css('margin-left','-4px');
    // Add logic
    $link.on('click', () => {
        let reveal = true;
        $('.' + hiddenMarker).each(function() {
            const $this = $(this);
            const thisField = $this.attr('sq_id');
            const embedded = $this.hasClass('row-field-embedded');
            // Restore original classes
            const classes = $this.attr(hiddenStore) ?? '';
            $this.removeClass(hiddenMarker).addClass(classes);
            if (embedded) $('.rc-field-embed[var="' + thisField + '"]').addClass('hide');
            reveal = false;
        })
        log('Toggled hidden fields: ' + (reveal ? 'Reveal' : 'Restore (Hide)'));
        if (reveal) {
            $(hiddenSelector).each((_,el) => {
                const $this = $(el);
                const thisField = $this.attr('sq_id');
                // Store original classes
                const classes = hiddenClasses.filter(c => $this.hasClass(c)).join(' ');
                $this.attr(hiddenStore, classes);
                // Is the field embedded? 
                const embedded = $this.hasClass('row-field-embedded');
                const $drawnOn = embedded ? $('.rc-field-embed[var="' + thisField + '"]') : $this;
                // Apply marker and reveal
                $this.addClass(hiddenMarker);
                $drawnOn.css({
                    'outline': 'solid 2px',
                    'outline-offset': config.isSurvey ? '-3px' : '-1px',
                    'outline-color': 'argb(255,0,0,.25)'
                });
                $drawnOn.removeClass(embedded ? 'hide' : classes);
            });
        }
    });
    if (config.isSurvey) {
        // Add to admin controls
        $('#admin-controls-div').append('<br>').append($link);
    }
    else {
        // Hook into displayFormSaveBtnTooltip()
        const orig_displayFormSaveBtnTooltip = window['displayFormSaveBtnTooltip'];
        window['displayFormSaveBtnTooltip'] = function() {
            $link.appendTo('body');
            orig_displayFormSaveBtnTooltip();
            // Add reveal link
            $('#auto-fill-btn').after($link).after('<br>');
        };
        // Display link
        window['displayFormSaveBtnTooltip']();
    }
}

/**
 * Adds field annotations to data entry forms and surveys
 * @param {Object} config 
 * @returns 
 */
function addFormAnnotations(config) {
    const isSurvey = config.isSurvey;
    if (typeof config.fields !== 'object') return;
    // Shazam support
    if (typeof window['Shazam'] !== 'undefined') {
        window['Shazam'].beforeDisplayCallback = function() {
            log('Compensating for Shazam');
            $('tr[sq_id].shazam-vanished .ai-field-annotation').each(function() {
                const $annotation = $(this);
                const $tr = $annotation.parents('[sq_id]');
                const fieldName = $tr.attr('sq_id');
                const $shazCont = $('.shazam [name="' + fieldName + '"]').parents('.shazam');
                const $td = $('.shazam [name="' + fieldName + '"]').parents('tr[sq_id]').find('td.labelrc').last();
                const $sa = $annotation.clone(true);
                const $badge = $sa.find('.badge')
                $badge.addClass('ai-embedded');
                $td.append($sa);
                setupEmbeddeBadgeBehavior($badge, $shazCont);
            });
        }
    }
    const $badgeTemplate = $('<span class="badge badge-info" style="font-weight:normal;margin-bottom:.5em;">AI</span>');
    const $copyTemplate = $('<a href="javascript:;" style="position:absolute;top:3px;right:5px;"><i class="far fa-copy fs10"></i></a>')
    for (const field of Object.keys(config.fields)) {
        const embedded = $('[sq_id="' + field + '"]').hasClass('row-field-embedded');
        const $annotation = $('<div class="ai-field-annotation" style="position:relative;font-weight:normal;padding:0.5em;margin-top:0.5em;background-color:#fafafa;"></div>');
        const $code = $('<code style="white-space:pre;margin-top:0.5em;"></code>');
        const text = '' + config.fields[field] ?? '';
        if (text.length > 0) {
            $code.text(text);
            $annotation.append($code);
            const $copy = $copyTemplate.clone();
            $copy.on('click', () => {
                copyTextToClipboard(text);
            });
            $annotation.append($copy);
            $annotation.prepend('<br>');
        }
        $annotation.prepend('<small><i> &ndash; <span class="ai-copy-field-name">' + field + '</span></i></small>')
        const $badge = $badgeTemplate.clone();
        $annotation.prepend($badge);
        if (embedded) {
            const $embed = $('span.rc-field-embed[var="' + field + '"]')
            $embed.parents('tr[sq_id]').find('td').not('.questionnum').first().append($annotation);
            setupEmbeddeBadgeBehavior($badge, $embed);
        }
        else {
            $('div[data-mlm-field="' + field + '"]').after($annotation);
        }
        // Add copy field name functionality
        $annotation.find('.ai-copy-field-name').on('click', (e) => {
            copyTextToClipboard(e.ctrlKey ? '['+field+']' : field);
            const $copy = $(e.target);
            $copy.addClass('clicked');
            setTimeout(() => {
                $copy.removeClass('clicked');
            }, 300);
        });
    }
}
function setupEmbeddeBadgeBehavior($badge, $container) {
    $badge.addClass('ai-embedded');
    $badge.on('mouseenter', function() {
        $container.addClass('ai-embedded-outline');
    });
    $badge.on('mouseleave', function() {
        $container.removeClass('ai-embedded-outline');
    });
    $badge.on('click', function() {
        $container.find('input').trigger('focus');
    });
}

/**
 * Adds enhancements to the Online Designer's field list
 * @param {Object} config 
 */
function addDesignerEnhancements(config) {
    $('span[data-kind="variable-name"]').each(function() {
        const $field = $(this);
        const fieldName = $field.text();
        const $variable = $field.prev('i');
        const $wrapper = $('<span class="ai-copy-field-name"></span>');
        $variable.before($wrapper);
        $wrapper.append($variable);
        $wrapper.append('&nbsp;');
        $wrapper.append($field);
        $wrapper.on('mousedown', function(e) {
            e.stopImmediatePropagation();
        })
        $wrapper.on('click', function(e) {
            copyTextToClipboard(e.ctrlKey ? '['+fieldName+']' : fieldName);
            $wrapper.addClass('clicked');
            setTimeout(() => {
                $wrapper.removeClass('clicked');
            }, 300);
            return false;
        })
        const text = '' + config.fields[fieldName] ?? '';
        const $badge = $('<span class="badge badge-info ai-badge" style="font-weight:normal;">AI</span>');
        const $code = $('<div><div class="ai-code-wrapper"><code class="ai-code"></code></div></div>');
        $code.find('code').text(text);
        // @ts-ignore
        const popover = new bootstrap.Popover($badge.get(0), {
            html: true,
            placement: 'bottom',
            title: config.codeTitle,
            content: $code.html(),
            trigger: 'click' + (text.length > 0 ? ' hover' : '')
        });
        $badge.addClass('ai-badge-annotations');
        $badge.on('inserted.bs.popover', () => {
            const $tip = $(popover.tip);
            $tip.css({
                'max-width': '500px',
                'min-width': '200px'
            });
            const $edit = $('<button class="btn btn-xs ai-code-edit">Edit</button>');
            $tip.find('.popover-header').append($edit);
        });
        $('#design-' + fieldName + ' span.od-field-icons').append($badge);
    });
}

/**
 * Creates a menu item for the "Choose action for record" menu on the Record Home Page
 * @param {string} href 
 * @param {string} label 
 * @param {Function|null} onClick 
 */
function createRecordHomePageActionListItem(href, label, onClick = null) {
    const $li = $('<li class="ui-menu-item"><a target="_blank" style="display:block;" tabindex="-1" role="menuitem" class="ui-menu-item-wrapper"><span style="vertical-align:middle;color:#065499;"></span></a></li>');
    $li.find('a').attr('href', href);
    $li.find('span').html(label);
    if (onClick != null) {
        // @ts-ignore
        $li.on('click', onClick);
    }
    return $li;
}

/**
 * Returns the ul element of the "Choose action for record" menu on the Record Home Page
 */
function getRecordHomePageActionMenu() {
    return  $('#recordActionDropdown');
}

/**
 * Adds links to query a records data or logs to the action dropdown on the Record Home Page.
 * @param {Object} config 
 */
function addQueryRecordLinks(config) {
    const url = new URL(config.dqtLink);
    url.searchParams.set('ai-query-pid', config.pid);
    url.searchParams.set('ai-query-id', config.record);
    const $menu = getRecordHomePageActionMenu();
    const icon = '<i class="fas fa-database"></i> ';
    // Query data
    url.searchParams.set('ai-query-for', 'data');
    $menu.append(createRecordHomePageActionListItem(url.toString(), icon + config.labelData));
    // Query logs
    url.searchParams.set('ai-query-for', 'logs');
    $menu.append(createRecordHomePageActionListItem(url.toString(), icon + config.labelLogs));
}

/**
 * Adds a link to view the Logging page for a record to the action dropdown on the Record Home Page.
 * @param {Object} config 
 */
function addViewRecordLoggingLink(config) {
    const url = new URL(config.url);
    url.searchParams.append('record', config.record);
    const $menu = getRecordHomePageActionMenu();
    const icon = '<i class="fas fa-receipt"></i> ';
    $menu.append(createRecordHomePageActionListItem(url.toString(), icon + config.label));
}

/**
 * Executes a query in the Database Query Tool
 * @param {Object} config 
 */
function addQueryRecordExecute(config) {
    $(() => {
        $('#query').val(config.query);
        $('#form').append(`<input type="hidden" name="redcap_csrf_token" value="${config.csrfToken}">`);
        $('#form').trigger('submit');
    });
}

//#endregion

//#region Clipboard Helper

/**
 * Copies a string to the clipboard (fallback method for older browsers)
 * @param {string} text
 */
function fallbackCopyTextToClipboard(text) {
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
function copyTextToClipboard(text) {
    if (!navigator.clipboard) {
        fallbackCopyTextToClipboard(text);
        return;
    }
    navigator.clipboard.writeText(text).catch(function() {
        error('Failed to copy text to clipboard.')
    })
}

//#endregion

//#region Debug Logging

function getLineNumber() {
    try {
        const line = ((new Error).stack ?? '').split('\n')[3];
        const parts = line.split(':');
        return parts[parts.length - 2];
    }
    catch(err) {
        return '??';
    }
}
/**
 * Logs a message to the console when in debug mode
 */
function log() {
    if (!config.debug) return;
    log_print(getLineNumber(), 'log', arguments);
}
/**
 * Logs a warning to the console when in debug mode
 */
function warn() {
    if (!config.debug) return;
    log_print(getLineNumber(), 'warn', arguments);
}

/**
 * Logs an error to the console when in debug mode
 */
function error() {
    log_print(getLineNumber(), 'error', arguments);;
}

/**
 * Prints to the console
 * @param {string} ln Line number where log was called from
 * @param {'log'|'warn'|'error'} mode
 * @param {IArguments} args
 */
function log_print(ln, mode, args) {
    const prompt = moduleName + ' ' + config.version + ' [' + ln + ']';
    switch(args.length) {
        case 1:
            console[mode](prompt, args[0]);
            break;
        case 2:
            console[mode](prompt, args[0], args[1]);
            break;
        case 3:
            console[mode](prompt, args[0], args[1], args[2]);
            break;
        case 4:
            console[mode](prompt, args[0], args[1], args[2], args[3]);
            break;
        case 5:
            console[mode](prompt, args[0], args[1], args[2], args[3], args[4]);
            break;
        case 6:
            console[mode](prompt, args[0], args[1], args[2], args[3], args[4], args[5]);
            break;
        default:
            console[mode](prompt, args);
            break;
    }
}

//#endregion

})();