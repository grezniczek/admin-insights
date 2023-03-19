# Admin Insights

A REDCap external module giving some insights into projects.

## Requirements

- REDCAP 13.4.0 or newer.

## Installation

Automatic installation:

- Install this module from the REDCap External Module Repository and enable it.

Manual installation:

- Clone this repo into `<redcap-root>/modules/admin_insights_v<version-number>`.
- Go to _Control Center > Technical / Developer Tools > External Modules_ and enable 'Admin Insights'.

## Configuration and Effects

Make sure to **enable the module for all projects** (or for specific projects, e.g. during development). In any case, this module will be invisible to non-admin users.

Features provided are:

- **Record data query link** - Adds a shortcut link for to the _Record Actions_ menu on the _Record Home Page_ that opens the _Database Query Tool_ in a new browser tab, automatically performing a query for the record in the _redcap_data_ table.
  ![Screensnip: Record Action Menu](images/record-actions.png)
- **Record log query link** - Adds a shortcut link for to the _Record Actions_ menu on the _Record Home Page_ that opens the _Database Query Tool_ in a new browser tab, automatically performing a query for the record in the appropriate _redcap_log_event_ table.
- **Data Entry / Survey Annotations**  
  When turned on (via a link in the External Modules section of REDCap's main project-context menu), field annotations will be displayed on data entry forms and survey pages in the respective field's label. In case the field is embedded, the annotations will be appended to the embedding container.  
- **Online Designer Enhancements**
  When enabled, the Online Designer overview will have AI badges that show the field annotations when clicked, giving the option to copy or edit them.

## Changelog

Version | Description
------- | --------------------
v1.0.0  | Initial release.
