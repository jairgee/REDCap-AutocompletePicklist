$(document).ready(function () {

    if (
        typeof window.AutocompletePicklistData === "undefined"
    ) {
        return;
    }

    Object.keys(
        window.AutocompletePicklistData
    ).forEach(function(fieldName){

        let values =
            window.AutocompletePicklistData[fieldName];

        let input =
            $('input[name="' + fieldName + '"]');

        if (input.length === 0) {
            return;
        }

        let currentValue = input.val();

        let select = $('<select></select>')
            .attr('name', fieldName)
            .attr('id', input.attr('id'))
            .css('width', '100%');

        values.forEach(function(v){

            let option = $('<option></option>')
                .val(v)
                .text(v);

            if (v === currentValue) {
                option.prop('selected', true);
            }

            select.append(option);
        });

        if (
            currentValue &&
            !values.includes(currentValue)
        ) {
            select.append(
                $('<option></option>')
                    .val(currentValue)
                    .text(currentValue)
                    .prop('selected', true)
            );
        }

        input.replaceWith(select);

        select.select2({
            tags: true,
            width: 'resolve'
        });
    });
});
