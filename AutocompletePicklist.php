<?php

namespace Jairgee\AutocompletePicklist;

use Form;
use REDCap;

class AutocompletePicklist extends \ExternalModules\AbstractExternalModule
{
    const ACTION_TAG = '@AUTOCOMPLETE_PICKLIST';
    const ACTION_TAG_MULTI = '@AUTOCOMPLETE_PICKLIST_MULTI';

    public function redcap_data_entry_form(
        $project_id,
        $record,
        $instrument,
        $event_id,
        $group_id,
        $repeat_instance
    ) {
        \REDCap::logEvent('AutocompletePicklist', "Hook fired for instrument $instrument");
        $this->injectAutocomplete($project_id, $instrument);
    }

    public function redcap_survey_page_top(
        $project_id,
        $record,
        $instrument,
        $event_id
    ) {
        \REDCap::logEvent('AutocompletePicklist', "Hook fired for instrument $instrument");
        $this->injectAutocomplete($project_id, $instrument);
    }

    protected function injectAutocomplete($projectId, $instrument)
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

            /*            if (
                $annotation &&
                stripos($annotation, self::ACTION_TAG) !== false
            ) {

                $fields[$fieldName] = $this->getPicklistValues(
                    $projectId,
                    $fieldName
                );
            }
*/
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
                <?= json_encode($fields, JSON_UNESCAPED_UNICODE) ?>;
        </script>
    <?php
    }

    protected function getPicklistValues($projectId, $fieldName)
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

    protected function extractValues(
        $node,
        $targetField,
        &$values
    ) {
        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {

            if ($key === $targetField && !empty($value)) {

                $normalized = $this->normalize($value);

                /*                if ($normalized !== '') {
                    $values[] = $normalized;
                }
                    */
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

    protected function normalize($value)
    {
        $value = strip_tags($value);
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value;
    }

    protected function includeJs()
    {
    ?>
        <script>
            /*
            $(function() {

                if (typeof window.AutocompletePicklistData === "undefined") {
                    return;
                }

                Object.keys(window.AutocompletePicklistData).forEach(function(fieldName) {

                    const values = window.AutocompletePicklistData[fieldName];

                    const input = $('input[name="' + fieldName + '"]');

                    if (input.length === 0) {
                        return;
                    }

                    const currentValue = input.val() || '';

                    const select = $('<select></select>')
                        .attr('name', fieldName)
                        .attr('id', input.attr('id'))
                        .attr('data-field-name', fieldName)
                        .css('width', '100%');

                    // Blank option required for placeholder support
                    select.append(
                        $('<option></option>')
                        .val('')
                        .text('')
                    );

                    values.forEach(function(v) {

                        select.append(
                            $('<option></option>')
                            .val(v)
                            .text(v)
                        );
                    });

                    // Existing value not yet in the list
                    if (currentValue && !values.includes(currentValue)) {

                        select.append(
                            $('<option></option>')
                            .val(currentValue)
                            .text(currentValue)
                        );
                    }

                    // Replace textbox
                    input.after(select);
                    input.remove();

                    // Set current value or blank
                    select.val(currentValue);

                    // Initialize Select2
                    select.select2({
                        tags: true,
                        width: '100%',
                        placeholder: 'Select or enter a value',
                        allowClear: true,
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


                    // Trigger REDCap change events
                    select.on('change', function() {
                        $(this).trigger('blur');
                    });
                });
            });
*/

            $(function() {

                if (typeof window.AutocompletePicklistData === "undefined") {
                    return;
                }

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
                        .attr('id', input.attr('id'))
                        .val(currentValue);

                    // Visible select2 control
                    const select = $('<select></select>')
                        .attr('id', input.attr('id') + '_select2')
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
                            currentValue.split('|') :
                            [];

                    } else {

                        selectedValues = currentValue ?
                            [currentValue] :
                            [];
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

                    input.after(hidden);
                    hidden.after(select);

                    input.remove();

                    select.select2({
                        tags: true,
                        width: '100%',
                        placeholder: multiple ?
                            'Select or enter values' :
                            'Select or enter a value',
                        allowClear: !multiple,
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
