/**
 * Address-book import overlay and chunked progress UI.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 */

var TurbaImport = {

    // Set by PHP: text.preparing
    text: {},
    running: false,

    onDomLoad: function()
    {
        $$('form').each(function(form) {
            if (form.down('input[name=import_step]')) {
                form.observe('submit', this.onSubmit.bindAsEventListener(this));
            }
        }, this);

        if ($('turba-import-progress-bar')) {
            this.runProgress();
        }
    },

    onSubmit: function()
    {
        if (window.RedBox) {
            RedBox.showHtml(
                '<div class="turba-import-wait">' +
                    this.text.preparing.escapeHTML() +
                    '</div>'
            );
        }
    },

    runProgress: function()
    {
        this.running = true;
        window.onbeforeunload = function() {
            return true;
        };
        this.nextChunk();
    },

    nextChunk: function()
    {
        HordeCore.doAction('importContacts', {}, {
            callback: this.onChunk.bind(this)
        });
    },

    onChunk: function(r)
    {
        var bar = $('turba-import-progress-bar'),
            text = $('turba-import-progress-text');

        if (text && r.progress_text) {
            text.update(r.progress_text.escapeHTML());
        }
        if (bar) {
            bar.writeAttribute('value', r.processed || 0);
            if (r.total) {
                bar.writeAttribute('max', r.total);
            }
        }

        if (r.error) {
            this.finish();
            $('turba-import-progress-error').update(r.progress_text.escapeHTML()).show();
            $('turba-import-progress-done').show();
            return;
        }

        if (r.done) {
            this.finish();
            if (r.summary) {
                $('turba-import-progress-summary').update(r.summary.escapeHTML()).show();
            }
            $('turba-import-progress-done').show();
            return;
        }

        this.nextChunk();
    },

    finish: function()
    {
        this.running = false;
        window.onbeforeunload = null;
    }

};

document.observe('dom:loaded', TurbaImport.onDomLoad.bind(TurbaImport));
