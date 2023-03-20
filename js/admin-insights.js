// Admin Insights
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

const featureMapper = new Map();
featureMapper.set('reveal-hidden', { added: false, init: addRevealHidden });
featureMapper.set('survey-annotations', { added: false, init: addFormAnnotations });
featureMapper.set('data-entry-annotations', { added: false, init: addFormAnnotations });
featureMapper.set('designer-enhancements', { added: false, init: addDesignerEnhancements });
featureMapper.set('query-record-rhp', { added: false, init: addQueryRecordLinks });
featureMapper.set('query-record-dqt', { added: false, init: addQueryRecordExecute });

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
        let updated = false;
        for (const key of Object.keys(config_data)) {
            if (typeof config[key] == 'undefined') {
                config[key] = config_data[key];
                updated = true;
            }
        }
        if (updated) {
            log('Updated configuration', config);
        }
    }
    // Add features
    featureMapper.forEach((feature, name) => {
        if (!feature.added && typeof config[name] !== 'undefined') {
            feature.added = true;
            log('Adding feature "' + name + '"', config[name]);
            feature.init(config[name]);
        }
    });
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
    $(() => {
        const $afb = $('#auto-fill-btn');
        // Check for presence of the auto-fill button
        if ($afb.length == 0) {
            error('Could not find the auto-fill button. Aborting.');
            return;
        }
        // Add reveal link
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
        $link.html(config.linkLabel);
        $link.find('.badge').css('margin-left','-4px');
        $link.on('click', () => {
            let reveal = true;
            $('.ai-hidden-removed').each(function() {
                $(this).removeClass('ai-hidden-removed').addClass('@HIDDEN');
                reveal = false;
            })
            log('Toggled hidden fields: ' + (reveal ? 'Reveal' : 'Restore (Hide)'));
            if (reveal) {
                $('.\\@HIDDEN').css({
                    'outline': 'solid 2px',
                    'outline-offset': config.isSurvey ? '-3px' : '-1px',
                    'outline-color': 'argb(255,0,0,.25)'
                }).removeClass('@HIDDEN').addClass('ai-hidden-removed');
            }
        });
        $afb.after($link).after('<br>');
    });
}


function addFormAnnotations(config) {
    const isSurvey = config.isSurvey;
    if (typeof config.fields !== 'object') return;
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
        $annotation.prepend('<small><i> &ndash; ' + field + '</i></small>')
        const $badge = $badgeTemplate.clone();
        $annotation.prepend($badge);
        if (embedded) {
            $badge.removeClass('badge-info').addClass('badge-warning');
            const $embed = $('span.rc-field-embed[var="' + field + '"]')
            $embed.parents('tr[sq_id]').find('td').not('.questionnum').first().append($annotation);
            $badge.css('cursor', 'crosshair');
            $badge.on('mouseenter', function() {
                $embed.css('outline', 'red dotted 2px');
            });
            $badge.on('mouseleave', function() {
                $embed.css('outline','none');
            });
            $badge.on('click', function() {
                $embed.find('input').trigger('focus');
            });
        }
        else {
            $('div[data-mlm-field="' + field + '"]').after($annotation);
        }
    }
}


function addDesignerEnhancements(config) {
    $('span[data-kind="variable-name"]').each(function() {
        const $field = $(this);
        const fieldName = $field.text();
        const $variable = $field.prev('i');
        const $wrapper = $('<span class="copy-field-name"></span>');
        $variable.before($wrapper);
        $wrapper.append($variable);
        $wrapper.append('&nbsp;');
        $wrapper.append($field);
        $wrapper.on('mousedown', function(e) {
            e.stopImmediatePropagation();
        })
        $wrapper.on('click', function() {
            copyTextToClipboard(fieldName);
            $wrapper.addClass('clicked');
            setTimeout(() => {
                $wrapper.removeClass('clicked');
            }, 300);
            return false;
        })
        const text = '' + config.fields[fieldName] ?? '';
        const $badge = $('<span class="badge badge-info ai-badge" style="font-weight:normal;">AI</span>');
        if (text.length > 0) {
            const $code = $('<div><div class="ai-code-wrapper"><code class="ai-code"></code></div></div>');
            $code.find('code').text(text);
            // @ts-ignore
            const popover = new bootstrap.Popover($badge.get(0), {
                html: true,
                placement: 'bottom',
                title: config.codeTitle,
                content: $code.html(),
                trigger: 'click hover'
            });
            $badge.addClass('ai-badge-annotations');
            $badge.on('inserted.bs.popover', () => {
                const $tip = $(popover.tip);
                $tip.css('max-width', '500px');
                const $edit = $('<button class="btn btn-xs ai-code-edit">Edit</button>');
                $tip.find('.popover-header').append($edit);
            });
        }
        $('#design-' + fieldName + ' span.od-field-icons').append($badge);
    });
}

/**
 * Adds links to query a records data or logs to the action dropdown on the Record Home Page.
 * @param {Object} config 
 */
function addQueryRecordLinks(config) {
    $(() => {
        const url = new URL(config.dqtLink);
        url.searchParams.set('ai-query-pid', config.pid);
        url.searchParams.set('ai-query-id', config.record);
        const $ul = $('#recordActionDropdown');
        const $li = $('<li class="ui-menu-item"><a target="_blank" style="display:block;" tabindex="-1" role="menuitem" class="ui-menu-item-wrapper"><span style="vertical-align:middle;color:#065499;"><i class="fas fa-database"></i> <span data-ai-label></span></span></a></li>')
        // Query data
        url.searchParams.set('ai-query-for', 'data');
        $li.find('a').attr('href', url.toString());
        $li.find('[data-ai-label]').html(config.labelData);
        $ul.append($li.clone().on('click', () => $('#recordActionDropdownTrigger').trigger('click')));
        // Query logs
        url.searchParams.set('ai-query-for', 'logs');
        $li.find('a').attr('href', url.toString());
        $li.find('[data-ai-label]').html(config.labelLogs);
        $ul.append($li.clone().on('click', () => $('#recordActionDropdownTrigger').trigger('click')));
        $li.remove();
    });
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

//#region Clipboard

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