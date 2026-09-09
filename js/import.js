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
        var forms = document.querySelectorAll('form');
        var i, form;
        for (i = 0; i < forms.length; i++) {
            form = forms[i];
            if (form.querySelector('input[name="import_step"]')) {
                form.addEventListener('submit', this.onSubmit.bind(this));
            }
        }

        if (document.getElementById('turba-import-progress-bar')) {
            this.runProgress();
        }
    },

    onSubmit: function()
    {
        var overlay = document.createElement('div');
        overlay.id = 'turba-import-wait-overlay';
        overlay.className = 'turba-import-wait-overlay';
        overlay.setAttribute('role', 'status');
        var box = document.createElement('div');
        box.className = 'turba-import-wait';
        box.textContent = this.text.preparing || '';
        overlay.appendChild(box);
        document.body.appendChild(overlay);
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
        var self = this;
        if (!window.HordeCore || !HordeCore.doAction) {
            this.onAjaxFailure();
            return;
        }
        HordeCore.doAction('importContacts', {}, {
            callback: this.onChunk.bind(this),
            ajaxopts: {
                onFailure: function(t, o) {
                    HordeCore.onFailure(t, o);
                    self.onAjaxFailure();
                },
                onException: function(r, e) {
                    HordeCore.onException(r, e);
                    self.onAjaxFailure();
                }
            }
        });
    },

    onAjaxFailure: function()
    {
        var msg, err, done;
        if (!this.running) {
            return;
        }
        this.finish();
        msg = (window.HordeCore && HordeCore.text && HordeCore.text.ajax_error)
            ? HordeCore.text.ajax_error
            : 'Error when communicating with the server.';
        err = document.getElementById('turba-import-progress-error');
        if (err) {
            err.textContent = msg;
            err.style.display = '';
        }
        done = document.getElementById('turba-import-progress-done');
        if (done) {
            done.style.display = '';
        }
    },

    onChunk: function(r)
    {
        var bar = document.getElementById('turba-import-progress-bar'),
            text = document.getElementById('turba-import-progress-text'),
            err, summary, done;

        if (text && r.progress_text) {
            text.textContent = r.progress_text;
        }
        if (bar) {
            bar.value = r.processed || 0;
            if (r.total) {
                bar.max = r.total;
            }
        }

        if (r.error) {
            this.finish();
            err = document.getElementById('turba-import-progress-error');
            if (err) {
                err.textContent = r.progress_text || r.error;
                err.style.display = '';
            }
            done = document.getElementById('turba-import-progress-done');
            if (done) {
                done.style.display = '';
            }
            return;
        }

        if (r.done) {
            this.finish();
            if (r.summary) {
                summary = document.getElementById('turba-import-progress-summary');
                if (summary) {
                    summary.textContent = r.summary;
                    summary.style.display = '';
                }
            }
            done = document.getElementById('turba-import-progress-done');
            if (done) {
                done.style.display = '';
            }
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

document.addEventListener('DOMContentLoaded', function() {
    TurbaImport.onDomLoad();
});
