/**
 * Provides the javascript for the minisearch portal block.
 *
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.php.
 */

var TurbaMinisearch = {

    // Vars set by block code: abooks, URI_AJAX

    miniSearch: function(e)
    {
        ['turba-minisearch-searching', 'turba-minisearch-close', 'turba-minisearch-results'].forEach(function(id) {
            document.getElementById(id).style.display = '';
        });
        HordeCore.doAction('minisearch', {
            abooks: JSON.stringify(this.abooks),
            search: document.getElementById('turba-minisearch-search').value
        }, {
            callback: function(r) {
                document.getElementById('turba-minisearch-results').innerHTML = r.html;
                document.getElementById('turba-minisearch-searching').style.display = 'none';
            },
            uri: this.URI_AJAX
        });
        e.preventDefault();
    },

    hideMiniSearch: function(e)
    {
        ['turba-minisearch-searching', 'turba-minisearch-close'].forEach(function(id) {
            document.getElementById(id).style.display = 'none';
        });
        var results = document.getElementById('turba-minisearch-results');
        results.innerHTML = '';
        results.style.display = 'none';
        e.preventDefault();
    },

    onDomLoad: function()
    {
        document.getElementById('turba-minisearch').addEventListener('submit', this.miniSearch.bind(this));
        document.getElementById('turba-minisearch-close').addEventListener('click', this.hideMiniSearch.bind(this));
    }

};

document.addEventListener('DOMContentLoaded', TurbaMinisearch.onDomLoad.bind(TurbaMinisearch));
