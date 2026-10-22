<?php

namespace Jairgee\AutocompletePicklist;

use Form;

/**
 * Converts annotated text fields into autocomplete-style picklists.
 *
 * Suggestions are collected from existing project data, and users may enter
 * values that are not already in the suggestion list.
 */
class AutocompletePicklist extends \ExternalModules\AbstractExternalModule
{
    /**
     * Action tag for a single-value autocomplete picklist.
     */
    const ACTION_TAG = '@AUTOCOMPLETE_PICKLIST';

    /**
     * Action tag for a multiple-value autocomplete picklist.
     */
    const ACTION_TAG_MULTI = '@AUTOCOMPLETE_PICKLIST_MULTI';

    /**
     * Injects the autocomplete control into a data-entry form.
     *
     * @param int         $project_id     ID of the REDCap project.
     * @param string|null $record         Record currently being displayed, if any.
     * @param string      $instrument     Name of the instrument being displayed.
     * @param int         $event_id        ID of the event being displayed.
     * @param int|null    $group_id        ID of the DAG, if applicable.
     * @param int|null    $repeat_instance Repeating instance being displayed, if applicable.
     *
     * @return void
     */
    public function redcap_data_entry_form(
        int $project_id,
        ?string $record,
        string $instrument,
        int $event_id,
        ?int $group_id,
        ?int $repeat_instance
    ) {
        \REDCap::logEvent('AutocompletePicklist', "Hook fired for instrument $instrument");
        $this->injectAutocomplete($project_id, $instrument);
    }

    /**
     * Injects the autocomplete control at the top of a survey page.
     *
     * @param int         $project_id ID of the REDCap project.
     * @param string|null $record     Record currently being displayed, if any.
     * @param string      $instrument Name of the instrument being displayed.
     * @param int         $event_id   ID of the event being displayed.
     *
     * @return void
     */
    public function redcap_survey_page_top(
        int $project_id,
        ?string $record,
        string $instrument,
        int $event_id
    ) {
        \REDCap::logEvent('AutocompletePicklist', "Hook fired for instrument $instrument");
        $this->injectAutocomplete($project_id, $instrument);
    }

    /**
     * Finds tagged text fields on an instrument and emits their picklist data.
     *
     * @param int    $projectId ID of the REDCap project.
     * @param string $instrument Name of the instrument being displayed.
     *
     * @return void
     */
    protected function injectAutocomplete(int $projectId, string $instrument)
    {
        global $Proj;

        $this->includeJs();

        $fields = [];

        foreach ($Proj->metadata as $fieldName => $meta) {

            if ($meta['form_name'] !== $instrument) {
                continue;
            }

            if ($meta['element_type'] !== 'text') {
                continue;
            }

            $annotation = $meta['misc'];

            if (!$annotation) {
                continue;
            }

            $single =
                stripos($annotation, self::ACTION_TAG) !== false;

            $multi =
                stripos($annotation, self::ACTION_TAG_MULTI) !== false;

            if (!$single && !$multi) {
                continue;
            }

            $fields[$fieldName] = [
                'multiple' => $multi,
                'values' => $this->getPicklistValues(
                    $projectId,
                    $fieldName
                )
            ];
        }

        \REDCap::logEvent('AutocompletePicklist', 'Found ' . count($fields) . ' fields.');

        if (empty($fields)) {
            return;
        }

?>
        <script>
            window.AutocompletePicklistData =
                <?= json_encode(
                    $fields,
                    JSON_UNESCAPED_UNICODE
                        | JSON_HEX_TAG
                        | JSON_HEX_AMP
                        | JSON_HEX_APOS
                        | JSON_HEX_QUOT
                        | JSON_THROW_ON_ERROR
                ) ?>;
        </script>
    <?php
    }

    /**
     * Retrieves and sorts distinct suggestions for a field from project data.
     *
     * @param int    $projectId ID of the REDCap project.
     * @param string $fieldName Name of the field whose values will be collected.
     *
     * @return string[] Distinct field values, sorted without regard to case.
     */
    protected function getPicklistValues(int $projectId, string $fieldName)
    {
        $data = \REDCap::getData([
            'project_id' => $projectId,
            'return_format' => 'array',
            'fields' => [$fieldName]
        ]);

        $values = [];

        foreach ($data as $record) {

            $this->extractValues(
                $record,
                $fieldName,
                $values
            );
        }

        $values = array_unique($values);

        natcasesort($values);

        return array_values($values);
    }

    /**
     * Recursively finds values for a target field and appends their suggestions.
     *
     * Values are normalized and split on the pipe delimiter before being added.
     *
     * @param array    $node       Data node to inspect.
     * @param string   $targetField Field name whose values should be collected.
     * @param string[] $values     Suggestions collected so far, passed by reference.
     *
     * @return void
     */
    protected function extractValues(
        array $node,
        string $targetField,
        array &$values
    ) {
        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {

            if ($key === $targetField && !empty($value)) {

                $normalized = $this->normalize($value);

                if ($normalized !== '') {

                    foreach (explode('|', $normalized) as $tag) {

                        $tag = trim($tag);

                        if ($tag !== '') {
                            $values[] = $tag;
                        }
                    }
                }
            }

            if (is_array($value)) {
                $this->extractValues(
                    $value,
                    $targetField,
                    $values
                );
            }
        }
    }

    /**
     * Removes markup and normalizes whitespace in a field value.
     *
     * @param string $value Value to normalize.
     *
     * @return string Normalized value.
     */
    protected function normalize(string $value)
    {
        $value = strip_tags($value);
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value;
    }

    /**
     * Emits the JavaScript that initializes autocomplete picklist controls.
     *
     * @return void
     */
    protected function includeJs()
    {
    ?>
        <script>
            /**
             * Initializes Select2 controls for fields configured by the module.
             * The PHP hook provides each field's suggestions and multiplicity
             * through window.AutocompletePicklistData.
             */
            $(function() {

                if (typeof window.AutocompletePicklistData === "undefined") {
                    return;
                }

                /** @param {string} fieldName Name of the REDCap field to convert. */
                Object.keys(window.AutocompletePicklistData).forEach(function(fieldName) {

                    const config =
                        window.AutocompletePicklistData[fieldName];

                    const values = config.values || [];
                    const multiple = config.multiple === true;

                    const input =
                        $('input[name="' + fieldName + '"]');

                    if (input.length === 0) {
                        return;
                    }

                    const currentValue = input.val() || '';

                    // Hidden field that REDCap actually saves
                    const hidden = $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', fieldName)
                        .val(currentValue);

                    // Visible select2 control
                    const select = $('<select></select>')
                        .attr('name', fieldName)
                        .css('width', '100%');

                    if (multiple) {
                        select.attr('multiple', 'multiple');
                    }

                    if (!multiple) {

                        select.append(
                            $('<option></option>')
                            .val('')
                            .text('')
                        );
                    }

                    values.sort(function(a, b) {
                        return a.localeCompare(b);
                    });

                    values.forEach(function(v) {

                        select.append(
                            $('<option></option>')
                            .val(v)
                            .text(v)
                        );
                    });

                    let selectedValues = [];

                    if (multiple) {

                        selectedValues = currentValue ?
                            currentValue.split('|') : [];

                    } else {

                        selectedValues = currentValue ? [currentValue] : [];
                    }

                    selectedValues.forEach(function(v) {

                        v = $.trim(v);

                        if (!v) {
                            return;
                        }

                        if (
                            select.find(
                                'option[value="' +
                                v.replace(/"/g, '\\"') +
                                '"]'
                            ).length === 0
                        ) {

                            select.append(
                                $('<option></option>')
                                .val(v)
                                .text(v)
                            );
                        }
                    });

                    input.after(select);
                    select.after(hidden);

                    input.remove();

                    select.select2({
                        tags: true,
                        width: '100%',
                        placeholder: multiple ?
                            'Select or enter values' : 'Select or enter a value',
                        allowClear: !multiple,
                        /**
                         * Creates a Select2 tag for a value not in the suggestions.
                         *
                         * @param {{term: string}} params Search term entered by the user.
                         * @returns {{id: string, text: string, newTag: boolean}|null}
                         */
                        createTag: function(params) {

                            let term = $.trim(params.term);

                            if (term === '') {
                                return null;
                            }

                            return {
                                id: term,
                                text: term,
                                newTag: true
                            };
                        }
                    });

                    if (multiple) {

                        select
                            .val(selectedValues)
                            .trigger('change');

                    } else {

                        select
                            .val(currentValue)
                            .trigger('change');
                    }

                    /**
                     * Copies the current selection into REDCap's submitted field.
                     *
                     * Multiple selections are serialized with REDCap's pipe delimiter;
                     * change and blur events notify REDCap that the value was updated.
                     *
                     * @returns {void}
                     */
                    function syncHiddenField() {

                        let value;

                        if (multiple) {

                            value =
                                (select.val() || [])
                                .join('|');

                        } else {

                            value =
                                select.val() || '';
                        }

                        hidden.val(value);

                        hidden.trigger('change');
                        hidden.trigger('blur');
                    }

                    select.on('change', syncHiddenField);

                    syncHiddenField();
                });
            });
        </script>
<?php
    }
}
