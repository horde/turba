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
            document.observe('HordeCore:ajaxFailure', this.onAjaxFailure.bind(this));
            document.observe('HordeCore:ajaxException', this.onAjaxFailure.bind(this));
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
        if (!window.HordeCore || !HordeCore.doAction) {
            this.onAjaxFailure();
            return;
        }
        HordeCore.doAction('importContacts', {}, {
            callback: this.onChunk.bind(this)
        });
    },

    onAjaxFailure: function()
    {
        if (!this.running) {
            return;
        }
        this.finish();
        var msg = (window.HordeCore && HordeCore.text && HordeCore.text.ajax_error)
            ? HordeCore.text.ajax_error
            : 'Error when communicating with the server.';
        var err = $('turba-import-progress-error');
        if (err) {
            err.update(msg.escapeHTML()).show();
        }
        if ($('turba-import-progress-done')) {
            $('turba-import-progress-done').show();
        }
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
