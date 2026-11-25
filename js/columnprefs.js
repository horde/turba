/**
 * Provides the javascript for managing columns.
 *
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 */

var TurbaColumnPrefs = {

    // Vars defaulting to null: cur_source
    dragSrcEl: null,

    updateColumnsPref: function()
    {
        document.getElementById('columns').value =
            Array.from(document.querySelectorAll('.turba-prefs-cols-panel'))
                .map(this.updateColumnsPref_1.bind(this)).join("\n");
    },

    updateColumnsPref_1: function(panel)
    {
        var c, p = panel.id.replace('turba-prefs-cols-panel-', '');
        this.cur_source = p;
        c = Array.from(panel.querySelectorAll('input[type=checkbox]:checked'))
            .map(this.updateColumnsPref_2.bind(this)).join("\t");
        if (c) {
            p += "\t" + c;
        }
        return p;
    },

    updateColumnsPref_2: function(checkbox)
    {
        return checkbox.id.replace('turba-prefs-cols-' + this.cur_source + '-', '');
    },

    clickHandler: function(e)
    {
        var elt = e.target;
        Array.from(document.getElementById('turba-prefs-cols-list').querySelectorAll('.active')).forEach(function(el) {
            el.classList.remove('active');
        });
        elt.parentElement.classList.add('active');
        document.querySelectorAll('.turba-prefs-cols-panel').forEach(function(el) {
            el.style.display = 'none';
        });
        document.getElementById('turba-prefs-cols-panel-' + elt.getAttribute('sourcename')).style.display = 'block';
    },

    handleDragStart: function(e)
    {
        TurbaColumnPrefs.dragSrcEl = this;
        this.style.opacity = '0.4';
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
    },

    handleDragOver: function(e)
    {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    },

    handleDrop: function(e)
    {
        e.preventDefault();
        if (TurbaColumnPrefs.dragSrcEl && TurbaColumnPrefs.dragSrcEl !== this) {
            var list = this.parentNode,
                children = Array.from(list.children),
                srcIndex = children.indexOf(TurbaColumnPrefs.dragSrcEl),
                destIndex = children.indexOf(this);
            if (srcIndex < destIndex) {
                list.insertBefore(TurbaColumnPrefs.dragSrcEl, this.nextSibling);
            } else {
                list.insertBefore(TurbaColumnPrefs.dragSrcEl, this);
            }
            TurbaColumnPrefs.updateColumnsPref();
        }
    },

    handleDragEnd: function()
    {
        this.style.opacity = '1';
    },

    initSortable: function(ol)
    {
        Array.from(ol.children).forEach(function(li) {
            li.draggable = true;
            li.addEventListener('dragstart', TurbaColumnPrefs.handleDragStart);
            li.addEventListener('dragover', TurbaColumnPrefs.handleDragOver);
            li.addEventListener('drop', TurbaColumnPrefs.handleDrop);
            li.addEventListener('dragend', TurbaColumnPrefs.handleDragEnd);
        });
    },

    onDomLoad: function()
    {
        var container = document.getElementById('turba-prefs-cols-columns');

        container.querySelectorAll('ol').forEach(function(ol) {
            this.initSortable(ol);
        }, this);

        container.addEventListener('click', function(e) {
            if (e.target.matches('input[type=checkbox]')) {
                this.updateColumnsPref();
            }
        }.bind(this));

        Array.from(document.getElementById('turba-prefs-cols-list').querySelectorAll('a')).forEach(function(a) {
            a.addEventListener('click', this.clickHandler.bind(this));
        }, this);
    }

};

document.addEventListener('DOMContentLoaded', TurbaColumnPrefs.onDomLoad.bind(TurbaColumnPrefs));
