/**
 * Provides the javascript for the search.php script (basic view).
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Jan Schneider <jan@horde.org>
 */

var TurbaSearch = {

    // Vars set by calling code: advanced

    criteria: {},
    shareSources: {},

    updateCriteria: function()
    {
        var source_elt = document.getElementById('turbaSearchSource');

        if (!source_elt || !source_elt.options) {
            return;
        }

        var criteriaSelect = document.getElementById('turbaSearchCriteria');
        criteriaSelect.innerHTML = '';
        Object.entries(this.criteria[source_elt.value] || {}).forEach(function(entry) {
            var opt = document.createElement('option');
            opt.value = entry[0];
            opt.textContent = entry[1];
            criteriaSelect.appendChild(opt);
        });

        var vbookForm = document.getElementById('vbook-form');
        if (vbookForm) {
            vbookForm.style.display =
                (this.shareSources[source_elt.value] === true) ? '' : 'none';
        }
    },

    onDomLoad: function()
    {
        var form = document.getElementById('horde-content')
            .querySelector('form[name="directory_search"]');

        if (this.advanced) {
            var toggle = document.getElementById('advancedtoggle');
            toggle.style.display = '';
            toggle.addEventListener('click', function() {
                toggle.remove();
                form.style.display = '';
            });
        } else {
            form.style.display = '';
        }

        this.updateCriteria();
    }

};

document.addEventListener('DOMContentLoaded', TurbaSearch.onDomLoad.bind(TurbaSearch));
