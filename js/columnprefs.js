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
        const p = panel.id.replace('turba-prefs-cols-panel-', '');
        this.cur_source = p;
        let columns = Array.from(panel.querySelectorAll('input[type=checkbox]:checked'))
            .map(this.updateColumnsPref_2.bind(this)).join("\t");
        if (columns) {
            columns = p + "\t" + columns;
        } else {
            columns = p;
        }
        return columns;
    },

    updateColumnsPref_2: function(checkbox)
    {
        return checkbox.id.replace('turba-prefs-cols-' + this.cur_source + '-', '');
    },

    clickHandler: function(e)
    {
        const elt = e.currentTarget;

        e.preventDefault();
        document.getElementById('turba-prefs-cols-list').querySelectorAll('.active').forEach(function(el) {
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
        const el = this;

        TurbaColumnPrefs.dragSrcEl = el;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', el.id);
        // Snapshot the drag image before fading the source row.
        setTimeout(function() {
            if (TurbaColumnPrefs.dragSrcEl === el) {
                el.style.opacity = '0.4';
            }
        }, 0);
    },

    handleDragOver: function(e)
    {
        const src = TurbaColumnPrefs.dragSrcEl;

        if (!src || src.parentNode !== this.parentNode || !e.dataTransfer) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    },

    handleDrop: function(e)
    {
        const src = TurbaColumnPrefs.dragSrcEl;

        if (!src || src === this || src.parentNode !== this.parentNode) {
            return;
        }
        e.preventDefault();

        const list = this.parentNode;
        const children = Array.from(list.children);
        const srcIndex = children.indexOf(src);
        const destIndex = children.indexOf(this);

        if (srcIndex < destIndex) {
            list.insertBefore(src, this.nextSibling);
        } else {
            list.insertBefore(src, this);
        }
        TurbaColumnPrefs.updateColumnsPref();
    },

    handleDragEnd: function()
    {
        this.style.opacity = '1';
        TurbaColumnPrefs.dragSrcEl = null;
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
        const container = document.getElementById('turba-prefs-cols-columns');

        container.querySelectorAll('ol').forEach(function(ol) {
            this.initSortable(ol);
        }, this);

        container.addEventListener('click', function(e) {
            if (e.target.matches('input[type=checkbox]')) {
                this.updateColumnsPref();
            }
        }.bind(this));

        document.getElementById('turba-prefs-cols-list').querySelectorAll('a').forEach(function(a) {
            a.addEventListener('click', this.clickHandler.bind(this));
        }, this);
    }

};

document.addEventListener('DOMContentLoaded', TurbaColumnPrefs.onDomLoad.bind(TurbaColumnPrefs));
