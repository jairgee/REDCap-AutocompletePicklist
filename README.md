# Autocomplete Picklist

A REDCap External Module that turns an existing text field into an autocomplete-style picklist using action tags. The available suggestions are collected from values already entered for that field in the project. Users can also enter new values.

## Features

- Adds suggestions to existing REDCap text fields without changing the project’s data dictionary.
- Provides single-value and multiple-value picklists.
- Allows users to search suggestions or enter a new value.
- Retains an existing field value even if it is not currently among the suggestions.
- Builds suggestions from values across the project, including repeating and longitudinal data.

## Requirements

- A REDCap project with the External Modules framework enabled.
- Permission to install or enable External Modules, as required by your REDCap administrator.

The module declares External Modules framework version 17 in `config.json`. Confirm compatibility with your REDCap installation and administrator before deployment.

## Installation

1. Install this module through your REDCap External Modules framework, following your institution’s module installation process. An administrator may need to install or approve it.
2. Enable **Autocomplete Picklist** for the project where you want to use it.
3. Add one of the action tags below to the annotation for an existing text field.

## Use

Add an action tag to the field’s **Action Tags / Field Annotation** setting in the REDCap Online Designer or data dictionary:

| Action tag | Behavior |
| --- | --- |
| `@AUTOCOMPLETE_PICKLIST` | Presents a single-value autocomplete picklist. |
| `@AUTOCOMPLETE_PICKLIST_MULTI` | Presents a multiple-value autocomplete picklist. Selected values are saved as a pipe-delimited string, matching REDCap’s usual multiple-value format. |

For example, to make a text field named `favorite_color` a single-value picklist, add:

```text
@AUTOCOMPLETE_PICKLIST
```

Use the tag on a **text** field; the module does not convert dropdown, checkbox, or other field types. The control is applied when that field’s instrument is displayed on a data entry form or survey page.

## How suggestions are populated

The module reads existing values for the tagged field from the current project and uses them as suggestions. It trims surrounding whitespace, collapses repeated whitespace, removes HTML tags, and splits values on `|` before building the list. Suggestions are deduplicated and sorted without regard to case.

The list is not a separately configured set of choices: it reflects data already entered in the project. A newly entered value can be selected and saved; it can then appear as a suggestion when the project’s data is read again. Existing values are preserved in the control even if they are not in the generated suggestion list.

## Notes

- The module does not alter the field definition or existing records in the project.
- For the multiple-value tag, values containing a literal pipe (`|`) cannot be distinguished from separate selections because the saved representation uses that character as its delimiter.
- Suggestions may include values from any event or repeating instance in the project for that field.

## Development

The module’s PHP entry point is `AutocompletePicklist.php`; its metadata is in `config.json`. The picklist control is initialized with Select2, which REDCap provides on the relevant pages.

## License

This project is distributed under the GNU General Public License version 3. See [LICENSE](LICENSE).
