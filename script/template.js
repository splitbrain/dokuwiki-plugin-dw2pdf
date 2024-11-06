/**
 * Template dialog for end users
 *
 * @author Eduardo Mozart de Oliveira <eduardomozart182@gmail.com>
 */
(function () {
    if (!JSINFO || !JSINFO.plugins.dw2pdf.showexporttemplate) return;


    // basic dialog template
    let $dialogSelect = '<select name="template" style="width:100%">';
    jQuery.each(JSON.parse(JSINFO.plugins.dw2pdf.templates),function(index, value){
        $dialogSelect += '<option value="' + value + '">' + value + '</option>'
    });
    $dialogSelect += '</select>';

    const $dialog = jQuery(
        '<div>' +
        '<form>' +
        '<label>' + LANG.plugins.dw2pdf.template + '<br>' +
        $dialogSelect +
        '</label>' +
        '</form>' +
        '</div>'
    );

    /**
     * Executes the renaming based on the form contents
     * @return {boolean}
     */
    const templateFN = function () {
        window.location.href = jQuery('#dokuwiki__pagetools .action.export_pdf a').attr("href") + "&tpl=" + $dialog.find('select').find(':selected').text();

        return true;
    };

    /**
     * Create the actual dialog modal and show it
     */
    const showDialog = function () {
        $dialog.dialog({
            title: LANG.plugins.dw2pdf.export_pdf_modal + ' ' + JSINFO.id,
            width: 800,
            height: 200,
            dialogClass: 'plugin_dw2pdf_dialog',
            modal: true,
            buttons: [
                {
                    text: LANG.plugins.dw2pdf.cancel,
                    click: function () {
                        $dialog.dialog("close");
                    }
                },
                {
                    text: LANG.plugins.dw2pdf.export,
                    click: templateFN
                }
            ],
            // remove HTML from DOM again
            close: function () {
                jQuery(this).remove();
            }
        });
    };

    /**
     * Bind an event handler as the first handler
     *
     * @param {jQuery} $owner
     * @param {string} event
     * @param {function} handler
     * @link https://stackoverflow.com/a/4700103
     */
    const bindFirst = function ($owner, event, handler) {
        $owner.unbind(event, handler);
        $owner.bind(event, handler);

        const events = jQuery._data($owner[0])['events'][event];
        events.unshift(events.pop());

        jQuery._data($owner[0])['events'][event] = events;
    };


    // attach handler to menu item
    jQuery('#dokuwiki__pagetools .action.export_pdf a')
        .show()
        .click(function (e) {
            e.preventDefault();
            showDialog();
        });

    // attach handler to mobile menu entry
    const $mobileMenuOption = jQuery('form select[name=do] option[value=export_pdf]');
    if ($mobileMenuOption.length === 1) {
        bindFirst($mobileMenuOption.closest('select[name=do]'), 'change', function (e) {
            const $select = jQuery(this);
            if ($select.val() !== 'export_pdf') return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            $select.val('');
            showDialog();
        });
    }

})();